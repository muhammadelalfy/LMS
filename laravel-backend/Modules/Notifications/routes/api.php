<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\ChannelWebhookController;
use Modules\Notifications\Http\Controllers\NotificationInboxController;
use Modules\Notifications\Http\Controllers\NotificationPreferenceController;
use Modules\Notifications\Http\Controllers\PhoneController;
use Modules\Notifications\Http\Controllers\SchoolMessageController;

// Public endpoints: each proves itself some other way than a bearer token.
// WhatsApp and the SMS gateway report delivery and replies here; each proves itself by signature or shared secret.
Route::get('/webhooks/whatsapp', [ChannelWebhookController::class, 'whatsappVerify']);
Route::post('/webhooks/whatsapp', [ChannelWebhookController::class, 'whatsapp'])->middleware('throttle:600,1');
Route::post('/webhooks/sms', [ChannelWebhookController::class, 'sms'])->middleware('throttle:600,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationInboxController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationInboxController::class, 'markRead']);
    Route::post('/me/phone', [PhoneController::class, 'send']);
    Route::post('/me/phone/verify', [PhoneController::class, 'verify']);
    Route::delete('/me/phone', [PhoneController::class, 'destroy']);
    Route::get('/me/notification-preferences', [NotificationPreferenceController::class, 'show']);
    Route::put('/me/notification-preferences', [NotificationPreferenceController::class, 'update']);
    Route::post('/notifications/send', [SchoolMessageController::class, 'send']);
    Route::post('/devices', [SchoolMessageController::class, 'registerDevice']);
    Route::post('/devices/remove', [SchoolMessageController::class, 'unregisterDevice']);
});
