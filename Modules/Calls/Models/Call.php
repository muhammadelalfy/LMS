<?php

namespace Modules\Calls\Models;

use Modules\Auth\Models\User;
use Modules\Chat\Models\Conversation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A voice or video call, or a group's online class, on the built-in media server. */
class Call extends Model
{
    public const LIVE_STATES = ['ringing', 'active'];

    protected $fillable = ['conversation_id', 'started_by', 'media', 'room', 'state', 'answered_at', 'ended_at'];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }

    public function isLive(): bool
    {
        return in_array($this->state, self::LIVE_STATES, true);
    }

    public function present(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'started_by' => $this->started_by,
            'starter_name' => $this->starter?->name,
            'media' => $this->media,
            'state' => $this->state,
            'class_room' => $this->conversation?->isGroup() ?? false,
            'started_at' => $this->created_at?->toISOString(),
            'answered_at' => $this->answered_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'duration_seconds' => $this->answered_at && $this->ended_at ? $this->answered_at->diffInSeconds($this->ended_at, true) : null,
        ];
    }
}
