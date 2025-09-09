<?php

namespace App\Http\Controllers;

use App\Models\Tip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChapaWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $secret = env('CHAPA_WEBHOOK_SECRET');
        $sig = $request->header('x-chapa-signature') ?? $request->header('Chapa-Signature') ?? null;

        // Check signature presence
        if (!$secret || !$sig) {
            Log::warning('Missing signature or secret for Chapa webhook');
            return response()->json(['message' => 'Missing signature'], 403);
        }

        // Verify webhook signature
        $expected = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expected, $sig)) {
            Log::warning('Chapa webhook signature mismatch', ['expected' => $expected, 'received' => $sig]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        // Decode JSON payload
        $body = $request->json()->all();

        // Support both payload formats: top-level tx_ref or inside data
        $tx_ref = $body['tx_ref'] ?? $body['reference'] ?? ($body['data']['tx_ref'] ?? $body['data']['reference'] ?? null);

        if (!$tx_ref) {
            Log::warning('Chapa webhook payload missing tx_ref', $body);
            return response()->json(['message' => 'tx_ref missing'], 400);
        }

        // Verify transaction with Chapa API
        $verify = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('CHAPA_SECRET_KEY'),
        ])->get("https://api.chapa.co/v1/transaction/verify/{$tx_ref}");

        if (!$verify->successful()) {
            Log::error('Chapa verify failed', ['tx_ref' => $tx_ref, 'response' => $verify->body()]);
            return response()->json(['message' => 'Verification failed'], 500);
        }

        $status = strtolower($verify['data']['status'] ?? ($verify['status'] ?? ''));

        // Transaction-safe update of tip and creator balance
        DB::transaction(function () use ($tx_ref, $verify, $status) {
            $tip = Tip::where('tx_ref', $tx_ref)->lockForUpdate()->first();

            if (!$tip) {
                Log::warning('Tip not found for tx_ref', ['tx_ref' => $tx_ref]);
                return;
            }

            // Skip if already succeeded
            if ($tip->status === 'succeeded') {
                Log::info('Tip already succeeded (idempotent)', ['tx_ref' => $tx_ref]);
                return;
            }

            if (in_array($status, ['success', 'successful'])) {
                $tip->status = 'succeeded';
                $tip->gateway_response = $verify->json();
                $tip->save();

                $creator = User::where('id', $tip->creator_id)->lockForUpdate()->first();
                if ($creator) {
                    $creator->balance = bcadd((string)$creator->balance, (string)$tip->amount, 2);
                    $creator->save();
                }
            } else {
                $tip->status = 'failed';
                $tip->gateway_response = $verify->json();
                $tip->save();
            }
        });

        return response()->json(['message' => 'ok']);
    }
}
