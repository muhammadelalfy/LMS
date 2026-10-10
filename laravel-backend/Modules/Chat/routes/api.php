<?php

use Illuminate\Support\Facades\Route;
use Modules\Chat\Http\Controllers\ChatController;
use Modules\Chat\Http\Controllers\RealtimeConfigController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/realtime/config', RealtimeConfigController::class);
    Route::get('/chat/contacts', [ChatController::class, 'contacts']);
    Route::get('/conversations', [ChatController::class, 'index']);
    Route::post('/conversations', [ChatController::class, 'open']);
    Route::put('/conversations/{conversation}', [ChatController::class, 'update']);
    Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:chat');
    Route::post('/conversations/{conversation}/read', [ChatController::class, 'read']);
    Route::delete('/messages/{message}', [ChatController::class, 'destroyMessage']);
    Route::post('/messages/{message}/report', [ChatController::class, 'report']);
    Route::post('/users/{user}/block', [ChatController::class, 'block']);
    Route::delete('/users/{user}/block', [ChatController::class, 'unblock']);
    Route::get('/admin/conversations', [ChatController::class, 'adminIndex']);
    Route::get('/admin/conversations/{conversation}/messages', [ChatController::class, 'adminMessages']);
});
