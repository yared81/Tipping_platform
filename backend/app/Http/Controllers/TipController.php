<?php

namespace App\Http\Controllers;

use App\Models\Tip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TipController extends Controller
{
    protected $presets = [20, 50, 100, 150, 200, 500];

    // POST /api/creator/{id}/tips
    public function store(Request $request, $id)
    {
        $creator = User::where('id', $id)
            ->where('role', 'creator')
            ->firstOrFail();

        $validated = $request->validate([
            'amount'    => 'required|numeric',
            'message'   => 'sometimes|string|max:500',
            'anonymous' => 'sometimes|boolean',
        ]);

        $amount = (float) $validated['amount'];

        if (!in_array($amount, $this->presets) && $amount < 10) {
            return response()->json([
                'message' => 'Invalid amount. Must be one of presets or >= 10 ETB'
            ], 422);
        }

        $tx_ref = 'tip_' . (string) Str::uuid();

        $tip = Tip::create([
            'tipper_id'  => $request->user()->id,
            'creator_id' => $creator->id,
            'amount'     => $amount,
            'currency'   => 'ETB',
            'status'     => 'pending',
            'message'    => $validated['message'] ?? null,
            'tx_ref'     => $tx_ref,
            'anonymous'  => $validated['anonymous'] ?? false,
        ]);

        // --- Sanitize inputs for Chapa ---
        $safeEmail       = trim((string) $request->user()->email);
        $safeFirstName   = trim((string) $request->user()->name);
        $safeTitle       = trim((string) $creator->name);
        $safeDescription = preg_replace(
            '/[^A-Za-z0-9\-\_\.\s]/',
            '',
            $tip->message ?? "Tip to {$creator->name}"
        );

        $payload = [
            'amount'       => (string) $amount,
            'currency'     => 'ETB',
            'tx_ref'       => (string) $tx_ref,
            'callback_url' => env('CHAPA_WEBHOOK_URL'),
            'return_url'   => env('CHAPA_RETURN_URL'),
            'email'        => $safeEmail,
            'first_name'   => $safeFirstName,
            'last_name'    => '',
            'customization'=> [
                'title'       => $safeTitle,
                'description' => $safeDescription,
            ],
        ];

        // --- Log payload for debugging ---
        Log::info('Chapa initialize request', $payload);

        // --- Initialize payment via Chapa ---
        $resp = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('CHAPA_SECRET_KEY'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.chapa.co/v1/transaction/initialize', $payload);

        // --- Log full response ---
        Log::info('Chapa initialize response', [
            'tx_ref' => $tx_ref,
            'response' => $resp->json()
        ]);

        if (!$resp->successful() || ($resp['status'] ?? '') !== 'success') {
            $tip->gateway_response = $resp->json();
            $tip->save();

            return response()->json([
                'message' => 'Failed to initialize payment',
                'error'   => $resp->json(),
            ], 500);
        }

        $checkoutUrl = $resp['data']['checkout_url'] ?? null;

        $tip->gateway_response = $resp->json();
        $tip->save();

        return response()->json([
            'message'      => 'Checkout initialized',
            'checkout_url' => $checkoutUrl,
            'tx_ref'       => $tx_ref,
            'tip_id'       => $tip->id,
        ], 201);
    }

    // GET /api/tips/{tx_ref}/status
    public function status($tx_ref)
    {
        $tip = Tip::where('tx_ref', $tx_ref)->firstOrFail();

        return response()->json([
            'tx_ref'  => $tx_ref,
            'status'  => $tip->status,
            'amount'  => $tip->amount,
            'message' => $tip->message,
        ]);
    }
}
