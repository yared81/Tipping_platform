<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    public function sendTip(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:10|max:50000',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $sender = $request->user();
        $receiver = User::findOrFail($request->receiver_id);

        if (!$receiver->isCreator()) {
            return response()->json([
                'message' => 'You can only tip creators',
                'error_code' => 'INVALID_RECEIVER'
            ], 400);
        }

        if ($sender->id === $receiver->id) {
            return response()->json([
                'message' => 'You cannot tip yourself',
                'error_code' => 'CANNOT_TIP_SELF'
            ], 400);
        }

        if (!$sender->canSendTip($request->amount)) {
            return response()->json([
                'message' => 'Insufficient balance',
                'error_code' => 'INSUFFICIENT_BALANCE',
                'current_balance' => $sender->balance,
                'required_amount' => $request->amount
            ], 400);
        }

        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'amount' => $request->amount,
                'transaction_type' => 'tip',
                'status' => 'pending',
                'description' => $request->description,
            ]);

            if ($transaction->process()) {
                DB::commit();

                return response()->json([
                    'message' => 'Tip sent successfully',
                    'transaction' => [
                        'id' => $transaction->id,
                        'amount' => $transaction->formatted_amount,
                        'platform_fee' => $transaction->formatted_platform_fee,
                        'net_amount' => $transaction->formatted_net_amount,
                        'status' => $transaction->status,
                        'description' => $transaction->description,
                        'receiver' => [
                            'id' => $receiver->id,
                            'name' => $receiver->name,
                            'avatar_url' => $receiver->avatar_url,
                        ],
                        'created_at' => $transaction->created_at->toISOString(),
                    ],
                    'sender_balance' => $sender->fresh()->formatted_balance,
                ], 201);
            } else {
                DB::rollBack();
                
                return response()->json([
                    'message' => 'Failed to process tip',
                    'error_code' => 'PROCESSING_FAILED'
                ], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'An error occurred while processing the tip',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 20);
        $status = $request->get('status');
        $type = $request->get('type');

        $query = $user->allTransactions()
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('transaction_type', $type);
        }

        $transactions = $query->paginate($perPage);

        return response()->json([
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'has_more' => $transactions->hasMorePages(),
            ]
        ]);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if ($transaction->sender_id !== $user->id && $transaction->receiver_id !== $user->id) {
            return response()->json([
                'message' => 'Transaction not found',
                'error_code' => 'TRANSACTION_NOT_FOUND'
            ], 404);
        }

        $transaction->load(['sender', 'receiver', 'fees']);

        return response()->json([
            'data' => [
                'id' => $transaction->id,
                'amount' => $transaction->formatted_amount,
                'platform_fee' => $transaction->formatted_platform_fee,
                'net_amount' => $transaction->formatted_net_amount,
                'status' => $transaction->status,
                'status_label' => $transaction->status_label,
                'transaction_type' => $transaction->transaction_type,
                'description' => $transaction->description,
                'sender' => [
                    'id' => $transaction->sender->id,
                    'name' => $transaction->sender->name,
                    'avatar_url' => $transaction->sender->avatar_url,
                ],
                'receiver' => [
                    'id' => $transaction->receiver->id,
                    'name' => $transaction->receiver->name,
                    'avatar_url' => $transaction->receiver->avatar_url,
                ],
                'fees' => $transaction->fees->map(function ($fee) {
                    return [
                        'type' => $fee->fee_type,
                        'amount' => $fee->formatted_amount,
                        'percentage' => $fee->formatted_percentage,
                        'description' => $fee->description,
                    ];
                }),
                'is_refundable' => $transaction->isRefundable(),
                'created_at' => $transaction->created_at->toISOString(),
                'processed_at' => $transaction->processed_at?->toISOString(),
            ]
        ]);
    }

    public function refund(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if ($transaction->sender_id !== $user->id) {
            return response()->json([
                'message' => 'You can only refund your own transactions',
                'error_code' => 'UNAUTHORIZED_REFUND'
            ], 403);
        }

        if (!$transaction->isRefundable()) {
            return response()->json([
                'message' => 'This transaction cannot be refunded',
                'error_code' => 'NOT_REFUNDABLE',
                'reason' => $transaction->status !== 'completed' ? 'Transaction not completed' : 'Refund period expired (24 hours)'
            ], 400);
        }

        try {
            DB::beginTransaction();

            if ($transaction->refund()) {
                DB::commit();

                return response()->json([
                    'message' => 'Transaction refunded successfully',
                    'transaction' => [
                        'id' => $transaction->id,
                        'status' => $transaction->status,
                        'refunded_amount' => $transaction->formatted_amount,
                        'refunded_at' => $transaction->processed_at->toISOString(),
                    ]
                ]);
            } else {
                DB::rollBack();
                
                return response()->json([
                    'message' => 'Failed to refund transaction',
                    'error_code' => 'REFUND_FAILED'
                ], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'An error occurred while processing the refund',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    public function sentTips(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 20);

        $tips = $user->sentTransactions()
            ->tips()
            ->with('receiver')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $tips->items(),
            'pagination' => [
                'current_page' => $tips->currentPage(),
                'last_page' => $tips->lastPage(),
                'per_page' => $tips->perPage(),
                'total' => $tips->total(),
                'has_more' => $tips->hasMorePages(),
            ],
            'summary' => [
                'total_tips_sent' => $user->getTotalTipsSent(),
                'total_amount_sent' => number_format($user->getTotalTipsSent(), 2) . ' ETB',
            ]
        ]);
    }

    public function receivedTips(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 20);

        $tips = $user->receivedTransactions()
            ->tips()
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $tips->items(),
            'pagination' => [
                'current_page' => $tips->currentPage(),
                'last_page' => $tips->lastPage(),
                'per_page' => $tips->perPage(),
                'total' => $tips->total(),
                'has_more' => $tips->hasMorePages(),
            ],
            'summary' => [
                'total_tips_received' => $user->getTotalTipsReceived(),
                'total_amount_received' => number_format($user->getTotalTipsReceived(), 2) . ' ETB',
            ]
        ]);
    }
}
