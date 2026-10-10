<?php

namespace Modules\Calls\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Calls\Models\Call;
use Modules\Chat\Models\Conversation;
use Modules\Auth\Models\User;
use Modules\Calls\Services\CallService;
use Modules\Calls\Services\LiveKit;
use Modules\Chat\Services\ChatAccess;
use Illuminate\Http\Request;

/** Voice and video calls, and group online classes, on the built-in media server. */
class CallController extends Controller
{
    public function __construct(private readonly CallService $calls, private readonly ChatAccess $access)
    {
    }

    /** Whether calls are switched on, so the app can hide the buttons if not. */
    public function status(LiveKit $livekit)
    {
        return ['enabled' => $livekit->isConfigured()];
    }

    /** Starts a call (or returns the one already live) and gives the caller its join details. */
    public function store(Request $request)
    {
        $data = $request->validate(['conversation_id' => 'required|integer|exists:conversations,id', 'media' => 'required|in:audio,video']);
        $conversation = Conversation::query()->findOrFail($data['conversation_id']);
        abort_unless($this->access->canAccess($request->user(), $conversation), 403, 'لا تملك صلاحية هذه المحادثة.');

        $call = $this->calls->start($request->user(), $conversation, $data['media']);
        $created = $call->wasRecentlyCreated;

        return response()->json($this->joined($request, $call), $created ? 201 : 200);
    }

    public function join(Request $request, Call $call)
    {
        return $this->joined($request, $call);
    }

    /** The call ringing or running in a conversation, for a "Join" button; null when none. */
    public function active(Request $request, Conversation $conversation)
    {
        abort_unless($this->access->canAccess($request->user(), $conversation), 403, 'لا تملك صلاحية هذه المحادثة.');
        $call = $this->calls->liveCall($conversation);

        return ['call' => $call?->load('starter', 'conversation')->present()];
    }

    /** Joins first, then describes the call, so the state shown is the one after joining. */
    private function joined(Request $request, Call $call): array
    {
        $join = $this->calls->join($request->user(), $call->load('conversation'));

        return ['call' => $call->fresh(['starter', 'conversation'])->present()] + $join;
    }

    public function decline(Request $request, Call $call)
    {
        $this->calls->decline($request->user(), $call->load('conversation'));

        return response()->noContent();
    }

    public function leave(Request $request, Call $call)
    {
        $this->calls->leave($request->user(), $call->load('conversation'));

        return response()->noContent();
    }

    public function end(Request $request, Call $call)
    {
        $this->calls->end($request->user(), $call->load('conversation'));

        return response()->noContent();
    }

    /** Teacher lets a class-room listener speak, or stops them. */
    public function speak(Request $request, Call $call, User $user)
    {
        $allowed = $request->validate(['allowed' => 'required|boolean'])['allowed'];
        $this->calls->allowSpeaking($request->user(), $call->load('conversation'), $user, (bool) $allowed);

        return response()->noContent();
    }

    public function remove(Request $request, Call $call, User $user)
    {
        $this->calls->removeParticipant($request->user(), $call->load('conversation'), $user);

        return response()->noContent();
    }

    /** The caller's recent calls, newest first. */
    public function index(Request $request)
    {
        $me = $request->user()->id;
        $calls = Call::query()->with('starter', 'conversation')
            ->whereIn('id', \Modules\Calls\Models\CallParticipant::query()->where('user_id', $me)->select('call_id'))
            ->latest('id')->limit(50)->get();

        return ['data' => $calls->map(fn (Call $call) => $call->present() + [
            'direction' => $call->started_by === $me ? 'outgoing' : 'incoming',
            'missed' => $call->state === 'missed' && $call->started_by !== $me,
        ])->values()];
    }
}
