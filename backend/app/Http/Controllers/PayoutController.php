<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    /**
     * Creator requests payout → balance deducted immediately.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'note'   => 'nullable|string|max:500',
        ]);

        $amount = number_format($validated['amount'], 2, '.', '');

        $payout = DB::transaction(function () use ($user, $amount, $validated) {
            $creator = User::where('id', $user->id)->lockForUpdate()->first();

            if (bccomp((string)$creator->balance, $amount, 2) < 0) {
                abort(422, 'Insufficient balance');
            }

            $creator->balance = bcsub((string)$creator->balance, $amount, 2);
            $creator->save();

            return Payout::create([
                'creator_id' => $creator->id,
                'amount'     => $amount,
                'status'     => 'pending',
                'reference'  => 'payout_' . (string) Str::uuid(),
                'note'       => $validated['note'] ?? null,
            ]);
        });

        return response()->json(['message' => 'Payout requested', 'payout' => $payout], 201);
    }

    /**
     * Admin: list payouts
     */
    public function index()
    {
        $payouts = Payout::with('creator:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($payouts);
    }

    /**
     * Admin: approve payout (no balance change)
     */
    public function approve($id, Request $request)
    {
        $payout = DB::transaction(function () use ($id, $request) {
            $payout = Payout::lockForUpdate()->findOrFail($id);

            if ($payout->status !== 'pending') {
                abort(400, 'Payout not pending');
            }

            $payout->status = 'approved';
            $payout->admin_id = $request->user()->id;
            $payout->processed_at = now();
            $payout->save();

            return $payout;
        });

        return response()->json(['message' => 'Payout approved', 'payout' => $payout]);
    }

    /**
     * Admin: reject payout → refund creator
     */
    public function reject($id, Request $request)
    {
        $payout = DB::transaction(function () use ($id, $request) {
            $payout = Payout::lockForUpdate()->findOrFail($id);

            if ($payout->status !== 'pending') {
                abort(400, 'Payout not pending');
            }

            $creator = User::where('id', $payout->creator_id)->lockForUpdate()->first();
            $creator->balance = bcadd((string)$creator->balance, (string)$payout->amount, 2);
            $creator->save();

            $payout->status = 'rejected';
            $payout->admin_id = $request->user()->id;
            $payout->note = $request->input('reason', $payout->note);
            $payout->processed_at = now();
            $payout->save();

            return $payout;
        });

        return response()->json(['message' => 'Payout rejected + refunded', 'payout' => $payout]);
    }

    /**
     * Admin: mark payout as paid (manual transfer done)
     */
    public function markPaid($id, Request $request)
    {
        $payout = Payout::findOrFail($id);

        if ($payout->status !== 'approved') {
            return response()->json(['message' => 'Must be approved first'], 400);
        }

        $payout->status = 'paid';
        $payout->admin_id = $request->user()->id;
        $payout->processed_at = now();
        $payout->save();

        return response()->json(['message' => 'Payout marked paid', 'payout' => $payout]);
    }
}
