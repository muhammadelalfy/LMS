<?php

namespace Modules\Chat\Events;

use Modules\Chat\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Modules\Tenancy\Support\SchoolChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A new message, pushed to everyone who has the conversation open. */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Message $message)
    {
    }

    /** Chat fan-out has its own queue so it never waits behind paid channels. */
    public function broadcastQueue(): string
    {
        return 'chat';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(SchoolChannel::name('conversation.'.$this->message->conversation_id))];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message->loadMissing('sender')->present()];
    }
}
