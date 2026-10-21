<?php

namespace Modules\Chat\Models;

use Modules\Auth\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's place in a conversation: how far they have read, and mute. */
class ConversationMember extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'last_read_message_id', 'muted_until'];

    protected function casts(): array
    {
        return ['muted_until' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
