<?php

namespace Modules\Calls\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Calls\Services\CallService;
use Modules\Calls\Services\LiveKit;
use Modules\Calls\Services\RoomName;
use Modules\Tenancy\Support\SchoolContext;

/**
 * LiveKit reports who joined and left, and when a room closed. It calls one
 * central address for every school; the room name says which school.
 */
class LiveKitWebhookController extends Controller
{
    public function __invoke(Request $request, LiveKit $livekit)
    {
        abort_unless($livekit->isConfigured(), 404);
        abort_unless($livekit->verifyWebhook((string) $request->header('Authorization'), $request->getContent()), 403);

        // Signed by LiveKit, so the room name can be trusted to pick the school.
        $event = $request->json()->all();
        SchoolContext::enter(RoomName::schoolOf((string) ($event['room']['name'] ?? '')));

        app(CallService::class)->applyWebhook($event);

        return response('', 200);
    }
}
