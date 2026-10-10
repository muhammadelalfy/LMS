<?php

namespace Modules\Chat\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Modules\Tenancy\Support\SchoolChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** A message was removed; clients blank it in place. */
class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(public int $conversationId, public int $messageId)
    {
    }

    public function broadcastQueue(): string
    {
        return 'chat';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(SchoolChannel::name('conversation.'.$this->conversationId))];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversationId, 'message_id' => $this->messageId];
    }
}
