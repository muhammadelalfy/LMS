<?php

namespace Modules\Calls\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Modules\Tenancy\Support\SchoolChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** Someone is calling you: the app shows its incoming-call screen. */
class CallInvited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    /** @param  array<string, mixed>  $call  Call::present() */
    public function __construct(public int $userId, public array $call)
    {
    }

    public function broadcastQueue(): string
    {
        return 'critical';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(SchoolChannel::name('user.'.$this->userId))];
    }

    public function broadcastAs(): string
    {
        return 'call.invited';
    }

    public function broadcastWith(): array
    {
        return ['call' => $this->call];
    }
}
