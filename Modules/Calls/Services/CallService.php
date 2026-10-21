<?php

namespace Modules\Calls\Services;

use Modules\Calls\Events\CallEnded;
use Modules\Calls\Events\CallInvited;
use Modules\Calls\Models\Call;
use Modules\Calls\Models\CallParticipant;
use Modules\Chat\Models\Conversation;
use Modules\Auth\Models\User;
use Modules\Chat\Services\ChatAccess;
use Modules\Notifications\Services\StudentNotifier;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Voice and video calls on the built-in media server: ringing the other
 * side, joining, declining, ending, and expiring calls nobody answered.
 * A direct call rings one person; a group's class room invites the group.
 */
class CallService
{
    /** Seconds a call rings before it counts as missed. */
    public const RING_SECONDS = 45;

    public function __construct(
        private readonly ChatAccess $access,
        private readonly StudentNotifier $notifier,
        private readonly LiveKit $livekit,
    ) {
    }

    /** Starts a call in a conversation, or returns the one already live there. */
    public function start(User $caller, Conversation $conversation, string $media): Call
    {
        if (! $this->livekit->isConfigured()) {
            throw new HttpException(503, 'المكالمات غير مفعّلة على الخادم بعد.');
        }
        if (! $this->access->canAccess($caller, $conversation)) {
            throw new HttpException(403, 'لا تملك صلاحية هذه المحادثة.');
        }

        // Someone already started one here: "starting" is joining it.
        $live = $this->liveCall($conversation);
        if ($live !== null) {
            return $live;
        }

        if (! $this->access->canPost($caller, $conversation)) {
            throw new HttpException(403, 'لا يمكنك بدء مكالمة هنا.');
        }
        if ($conversation->isGroup() && ! $caller->isAnyRole('admin', 'teacher')) {
            throw new HttpException(403, 'المعلم هو من يبدأ حصة الإنترنت.');
        }

        $call = Call::query()->create([
            'conversation_id' => $conversation->id, 'started_by' => $caller->id,
            'media' => $media, 'room' => RoomName::make(), 'state' => 'ringing',
        ]);
        CallParticipant::query()->create(['call_id' => $call->id, 'user_id' => $caller->id, 'state' => 'joined', 'joined_at' => now()]);

        $invitees = $this->access->participants($conversation)->reject(fn (User $user) => $user->id === $caller->id);
        foreach ($invitees as $invitee) {
            CallParticipant::query()->create(['call_id' => $call->id, 'user_id' => $invitee->id]);
            CallInvited::dispatch($invitee->id, $call->load('starter')->present());
        }
        $this->notifier->pushOnly(
            $invitees,
            $conversation->isGroup() ? "حصة عبر الإنترنت — {$conversation->group->name}" : "مكالمة من {$caller->name}",
            $media === 'video' ? 'مكالمة فيديو. اضغط للرد.' : 'مكالمة صوتية. اضغط للرد.',
            'call',
            ['type' => 'call_invite', 'call_id' => (string) $call->id, 'conversation_id' => (string) $conversation->id],
        );

        return $call;
    }

    /** The call ringing or running in a conversation, if any. */
    public function liveCall(Conversation $conversation): ?Call
    {
        return Call::query()->where('conversation_id', $conversation->id)->whereIn('state', Call::LIVE_STATES)->latest('id')->first();
    }

    /** @return array{url: string, token: string, can_publish: bool} what the app needs to connect */
    public function join(User $user, Call $call): array
    {
        if (! $call->isLive()) {
            throw new HttpException(409, 'انتهت هذه المكالمة.');
        }
        $participant = CallParticipant::query()->where(['call_id' => $call->id, 'user_id' => $user->id])->first();
        if ($participant === null) {
            // The class room is open to anyone the group's chat is open to.
            if (! $call->conversation->isGroup() || ! $this->access->canAccess($user, $call->conversation)) {
                throw new HttpException(403, 'لست مدعواً لهذه المكالمة.');
            }
            $participant = CallParticipant::query()->create(['call_id' => $call->id, 'user_id' => $user->id]);
        }

        $participant->update(['state' => 'joined', 'joined_at' => $participant->joined_at ?? now(), 'left_at' => null]);
        if ($call->state === 'ringing' && $user->id !== $call->started_by) {
            $call->update(['state' => 'active', 'answered_at' => now()]);
        }

        $isStaff = $user->isAnyRole('admin', 'teacher');
        $canPublish = ! $call->conversation->isGroup() || $isStaff;

        return [
            'url' => $this->livekit->url(),
            'token' => $this->livekit->joinToken($call->room, (string) $user->id, $user->name, $canPublish),
            'can_publish' => $canPublish,
        ];
    }

    public function decline(User $user, Call $call): void
    {
        $participant = CallParticipant::query()->where(['call_id' => $call->id, 'user_id' => $user->id])->firstOrFail();
        if ($participant->state === 'invited') {
            $participant->update(['state' => 'declined']);
        }
        // A direct call has nobody else to wait for; a class room carries on.
        if ($call->isLive() && ! $call->conversation->isGroup()) {
            $this->finish($call, 'declined');
        }
    }

    /** Leaves a call; the last person out (or either side of a direct call) ends it. */
    public function leave(User $user, Call $call): void
    {
        CallParticipant::query()->where(['call_id' => $call->id, 'user_id' => $user->id])
            ->update(['state' => 'left', 'left_at' => now()]);

        $remaining = CallParticipant::query()->where(['call_id' => $call->id, 'state' => 'joined'])->count();
        if ($call->isLive() && (! $call->conversation->isGroup() || $remaining === 0)) {
            $this->finish($call, 'ended');
        }
    }

    /** The caller, or staff for a class room, closes the call for everyone. */
    public function end(User $user, Call $call): void
    {
        $mayEnd = $user->id === $call->started_by
            || ($call->conversation->isGroup() && $user->isAnyRole('admin', 'teacher') && $this->access->canAccess($user, $call->conversation));
        if (! $mayEnd) {
            throw new HttpException(403, 'لا يمكنك إنهاء هذه المكالمة.');
        }
        if ($call->isLive()) {
            $this->finish($call, 'ended');
            $this->livekit->closeRoom($call->room);
        }
    }

    /** Staff only: lets a class-room listener speak, or stops them. */
    public function allowSpeaking(User $staff, Call $call, User $target, bool $allowed): void
    {
        $this->requireClassStaff($staff, $call);
        $this->livekit->setCanPublish($call->room, (string) $target->id, $allowed);
    }

    public function removeParticipant(User $staff, Call $call, User $target): void
    {
        $this->requireClassStaff($staff, $call);
        $this->livekit->remove($call->room, (string) $target->id);
        CallParticipant::query()->where(['call_id' => $call->id, 'user_id' => $target->id])
            ->update(['state' => 'left', 'left_at' => now()]);
    }

    /** Calls nobody answered in time become missed, and the callee is told. */
    public function expireUnanswered(): int
    {
        $missed = 0;
        $stale = Call::query()->where('state', 'ringing')->where('created_at', '<=', now()->subSeconds(self::RING_SECONDS))->get();
        foreach ($stale as $call) {
            $callees = CallParticipant::query()->where(['call_id' => $call->id, 'state' => 'invited'])->pluck('user_id');
            CallParticipant::query()->where(['call_id' => $call->id, 'state' => 'invited'])->update(['state' => 'missed']);
            $this->finish($call, 'missed');
            if (! $call->conversation->isGroup()) {
                $this->notifier->notifyUsers(
                    User::query()->whereKey($callees)->get(),
                    'مكالمة فائتة',
                    "مكالمة فائتة من {$call->starter->name}.",
                    'call',
                );
            }
            $missed++;
        }

        return $missed;
    }

    /** LiveKit says a participant connected or dropped, or the room closed. */
    public function applyWebhook(array $event): void
    {
        $call = Call::query()->where('room', $event['room']['name'] ?? '')->first();
        if ($call === null) {
            return;
        }
        $identity = (int) ($event['participant']['identity'] ?? 0);
        match ($event['event'] ?? '') {
            'participant_joined' => CallParticipant::query()->where(['call_id' => $call->id, 'user_id' => $identity])
                ->update(['state' => 'joined', 'joined_at' => now(), 'left_at' => null]),
            'participant_left' => CallParticipant::query()->where(['call_id' => $call->id, 'user_id' => $identity])
                ->update(['state' => 'left', 'left_at' => now()]),
            'room_finished' => $call->isLive() ? $this->finish($call, 'ended') : null,
            default => null,
        };
    }

    private function finish(Call $call, string $state): void
    {
        $call->update(['state' => $state, 'ended_at' => now()]);
        $this->tellOthers($call);
    }

    /** Tells everyone's app the call is over (their ringing or call screen closes). */
    private function tellOthers(Call $call): void
    {
        $call->loadMissing('starter', 'conversation');
        $payload = $call->present();
        CallParticipant::query()->where('call_id', $call->id)
            ->pluck('user_id')
            ->each(fn (int $userId) => CallEnded::dispatch($userId, $payload));
    }

    private function requireClassStaff(User $staff, Call $call): void
    {
        if (! $call->conversation->isGroup() || ! $staff->isAnyRole('admin', 'teacher') || ! $this->access->canAccess($staff, $call->conversation)) {
            throw new HttpException(403, 'هذا الإجراء للمعلم في حصص الإنترنت.');
        }
    }
}
