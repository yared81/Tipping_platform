<?php

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TipController;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\CreatorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ChapaWebhookController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\TransactionController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json([
            'status' => 'ok',
            'database' => true,
            'timestamp' => now(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'database' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
});




Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [LogoutController::class, 'logout']);


// Verify email
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified'], 200);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->json(['message' => 'Email verified successfully']);
    })->middleware(['signed'])->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {

    // Resend verification email
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'Verification link sent!']);
    })->middleware(['throttle:6,1']);
});


Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [ProfileController::class, 'show']);
    Route::put('/user', [ProfileController::class, 'update']);
    
  /*  Route::post('/tips', [TransactionController::class, 'sendTip']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('/transactions/{transaction}/refund', [TransactionController::class, 'refund']);
    Route::get('/tips/sent', [TransactionController::class, 'sentTips']);
    Route::get('/tips/received', [TransactionController::class, 'receivedTips']); */
});



Route::get('/tips/{tx_ref}/status', [TipController::class, 'status']); // polling status
Route::post('/chapa/webhook', [ChapaWebhookController::class, 'handle']); // Chapa webhook (public)
Route::middleware('auth:sanctum')->post('/creator/{id}/tips', [TipController::class, 'store']);// create tip + init checkout
Route::get('/payment-result', function () {
    return response()->json([
        'message' => 'Payment completed.'
    ]);
});

// Creator routes
Route::middleware(['auth:sanctum', 'role:creator'])->group(function () {
    Route::post('/payouts', [PayoutController::class, 'store']);
    Route::get('/creator/analytics', [CreatorController::class, 'analytics']);
});
Route::get('/creator/{id}', [CreatorController::class, 'show']); // public creator profile

// Admin routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/payouts', [PayoutController::class, 'index']);
    Route::put('/payouts/{id}/approve', [PayoutController::class, 'approve']);
    Route::put('/payouts/{id}/reject', [PayoutController::class, 'reject']);
    Route::put('/payouts/{id}/mark-paid', [PayoutController::class, 'markPaid']);
});


