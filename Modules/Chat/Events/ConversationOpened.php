<?php

namespace Modules\Chat\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Modules\Tenancy\Support\SchoolChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** Someone started a conversation with you: your app should subscribe to it. */
class ConversationOpened implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(public int $userId, public int $conversationId)
    {
    }

    public function broadcastQueue(): string
    {
        return 'chat';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(SchoolChannel::name('user.'.$this->userId))];
    }

    public function broadcastAs(): string
    {
        return 'conversation.opened';
    }

    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversationId];
    }
}
