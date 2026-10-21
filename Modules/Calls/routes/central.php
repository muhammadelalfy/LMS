<?php

use Illuminate\Support\Facades\Route;
use Modules\Calls\Http\Controllers\IntegrationController;
use Modules\Calls\Http\Controllers\LiveKitWebhookController;

// Other companies call these, not a school's people, so they live on the
// central address and find the school from what they carry. Each proves
// itself some other way than a bearer token.
//  - LiveKit's webhook is signed, and the room name carries the school.
//  - Google and Zoom send the browser back after a teacher connects an account;
//    the encrypted state carries the school and is the credential.
Route::post('/webhooks/livekit', LiveKitWebhookController::class)->middleware('throttle:600,1');
Route::get('/integrations/{provider}/callback', [IntegrationController::class, 'callback']);
