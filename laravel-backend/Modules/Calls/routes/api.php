<?php

use Illuminate\Support\Facades\Route;
use Modules\Calls\Http\Controllers\CallController;
use Modules\Calls\Http\Controllers\IntegrationController;
use Modules\Calls\Http\Controllers\OnlineSessionController;

// The LiveKit webhook and the Google/Zoom redirect are in central.php.

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::post('/integrations/{provider}/connect', [IntegrationController::class, 'connect']);
    Route::delete('/integrations/{provider}', [IntegrationController::class, 'disconnect']);
    Route::get('/groups/{group}/online-session', [OnlineSessionController::class, 'show']);
    Route::put('/groups/{group}/online-session', [OnlineSessionController::class, 'update']);
    Route::delete('/groups/{group}/online-session', [OnlineSessionController::class, 'destroy']);
    Route::get('/calls/status', [CallController::class, 'status']);
    Route::get('/calls', [CallController::class, 'index']);
    Route::get('/conversations/{conversation}/call', [CallController::class, 'active']);
    Route::post('/calls', [CallController::class, 'store']);
    Route::post('/calls/{call}/join', [CallController::class, 'join']);
    Route::post('/calls/{call}/decline', [CallController::class, 'decline']);
    Route::post('/calls/{call}/leave', [CallController::class, 'leave']);
    Route::post('/calls/{call}/end', [CallController::class, 'end']);
    Route::post('/calls/{call}/participants/{user}/speak', [CallController::class, 'speak']);
    Route::delete('/calls/{call}/participants/{user}', [CallController::class, 'remove']);
});
