<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\FawryClient;
use App\Services\Payments\OnlinePayments;
use Illuminate\Http\Request;

/**
 * FawryPay's Server To Server Notification V2. Only a notification whose
 * `messageSignature` matches our secure key is believed. Fawry wants an empty
 * 200 to mark it delivered and retries on anything else.
 */
class FawryWebhookController extends Controller
{
    public function __invoke(Request $request, FawryClient $fawry, OnlinePayments $online)
    {
        abort_unless($fawry->isConfigured(), 404);
        $notification = $request->json()->all() ?: $request->all();
        abort_unless($fawry->signer()->isValidNotification($notification), 403);

        $online->applyNotification($notification);

        return response('', 200);
    }
}
