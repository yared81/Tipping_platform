<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name', 'Tipping Platform API'),
        'status' => 'ok',
        'version' => Illuminate\Foundation\Application::VERSION,
        'php' => PHP_VERSION,
        'health' => url('/api/health'),
        'docs' => url('/api'),
    ]);
});

Route::get('/api', function () {
    return response()->json([
        'baseUrl' => url('/api'),
        'endpoints' => [
            ['GET', '/api/health', 'Service and DB health'],
            ['POST', '/api/register', 'Register user'],
            ['POST', '/api/login', 'Login user'],
            ['POST', '/api/logout', 'Logout (auth:sanctum)'],
            ['GET', '/api/user', 'Get profile (auth:sanctum)'],
            ['PUT', '/api/user', 'Update profile (auth:sanctum)'],
            ['POST', '/api/forgot-password', 'Send reset link'],
            ['POST', '/api/reset-password', 'Reset password'],
            ['GET', '/api/creator/{id}', 'Public creator profile'],
            ['POST', '/api/creator/{id}/tips', 'Create tip (auth:sanctum)'],
            ['GET', '/api/tips/{tx_ref}/status', 'Tip/status polling'],
            ['POST', '/api/chapa/webhook', 'Chapa webhook'],
            ['GET', '/api/payment-result', 'Payment result'],
        ],
    ]);
});
