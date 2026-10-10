<?php

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\FawryWebhookController;
use Modules\Payments\Http\Controllers\OnlinePaymentController;
use Modules\Payments\Http\Controllers\PaymentController;
use Modules\Payments\Http\Controllers\QrCheckinController;

// Public endpoints: each proves itself some other way than a bearer token.
// FawryPay calls this itself; the notification's signature is the credential.
Route::post('/webhooks/fawry', FawryWebhookController::class)->middleware('throttle:120,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/payments/{payment}/online', [OnlinePaymentController::class, 'store']);
    Route::get('/payments/{payment}/online', [OnlinePaymentController::class, 'show']);
    Route::post('/payments/qr-lookup', [QrCheckinController::class, 'lookup']);
    Route::post('/payments/qr-checkin', [QrCheckinController::class, 'payment']);
    Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'update', 'destroy']);
});
