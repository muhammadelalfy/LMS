<?php

namespace Modules\Chat\Services;

use Modules\Chat\Events\ConversationOpened;
use Modules\Chat\Events\MessageSent;
use Modules\Groups\Models\ClassGroup;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\ConversationMember;
use Modules\Chat\Models\Message;
use Modules\Auth\Models\User;
use Modules\Notifications\Services\StudentNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** Opening conversations, sending messages, and keeping read positions. */
class ChatService
{
    public function __construct(
        private readonly ChatAccess $access,
        private readonly StudentNotifier $notifier,
    ) {
    }

    /** The one direct conversation between [$me] and [$other]; made on first use. */
    public function openDirect(User $me, User $other): Conversation
    {
        $key = Conversation::directKey($me->id, $other->id);
        $conversation = Conversation::query()->where('direct_key', $key)->first();
        if ($conversation !== null) {
            return $conversation;
        }

        try {
            $conversation = Conversation::query()->create([
                'type' => Conversation::DIRECT, 'direct_key' => $key, 'created_by' => $me->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Both sides tapped "message" at once: the other one won.
            return Conversation::query()->where('direct_key', $key)->firstOrFail();
        }
        foreach ([$me, $other] as $member) {
            $this->member($conversation, $member);
        }
        ConversationOpened::dispatch($other->id, $conversation->id);

        return $conversation;
    }

    public function openGroup(User $me, ClassGroup $group): Conversation
    {
        $conversation = Conversation::query()->firstOrCreate(
            ['group_id' => $group->id],
            ['type' => Conversation::GROUP, 'created_by' => $me->id],
        );
        if ($conversation->wasRecentlyCreated) {
            foreach ($this->access->participants($conversation) as $participant) {
                if ($participant->id !== $me->id) {
                    ConversationOpened::dispatch($participant->id, $conversation->id);
                }
            }
        }

        return $conversation;
    }

    /**
     * Stores a message and tells everyone. Sending the same [$clientId]
     * again returns the stored message instead of a second copy, so a retry
     * after a dropped connection never duplicates.
     */
    public function send(User $sender, Conversation $conversation, string $body, ?string $clientId): Message
    {
        if ($clientId !== null) {
            $existing = Message::query()->withTrashed()
                ->where(['conversation_id' => $conversation->id, 'client_id' => $clientId])->first();
            if ($existing !== null) {
                return $existing->load('sender');
            }
        }

        try {
            $message = DB::transaction(function () use ($sender, $conversation, $body, $clientId) {
                $message = Message::query()->create([
                    'conversation_id' => $conversation->id, 'sender_id' => $sender->id,
                    'body' => $body, 'client_id' => $clientId,
                ]);
                $conversation->update(['last_message_id' => $message->id, 'last_message_at' => $message->created_at]);
                $this->member($conversation, $sender)->update(['last_read_message_id' => $message->id]);

                return $message;
            });
        } catch (UniqueConstraintViolationException) {
            // Two sends of the same client id raced; the first one stands.
            return Message::query()->withTrashed()
                ->where(['conversation_id' => $conversation->id, 'client_id' => $clientId])->firstOrFail()->load('sender');
        }

        $message->setRelation('sender', $sender);
        MessageSent::dispatch($message);
        $this->pushToOthers($sender, $conversation, $message);

        return $message;
    }

    public function markRead(User $user, Conversation $conversation, int $messageId): void
    {
        $member = $this->member($conversation, $user);
        // Reading only ever moves forward, and never past the last message.
        $upTo = min($messageId, (int) $conversation->last_message_id);
        if ($upTo > (int) $member->last_read_message_id) {
            $member->update(['last_read_message_id' => $upTo]);
        }
    }

    public function member(Conversation $conversation, User $user): ConversationMember
    {
        return ConversationMember::query()->firstOrCreate(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    }

    /** Phones of people who are not looking at the app: push, unless muted or blocked. */
    private function pushToOthers(User $sender, Conversation $conversation, Message $message): void
    {
        $muted = ConversationMember::query()->where('conversation_id', $conversation->id)
            ->where('muted_until', '>', now())->pluck('user_id')->all();
        $recipients = $this->access->participants($conversation)->reject(
            fn (User $user) => $user->id === $sender->id
                || in_array($user->id, $muted, true)
                || (! $conversation->isGroup() && $this->access->blocked($sender, $user)),
        );
        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifier->pushOnly(
            $recipients,
            $conversation->isGroup() ? "{$sender->name} · {$conversation->group->name}" : $sender->name,
            mb_strimwidth($message->body, 0, 140, '…'),
            'chat',
            ['conversation_id' => (string) $conversation->id],
        );
    }
}
