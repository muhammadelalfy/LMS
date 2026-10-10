<?php

namespace Modules\Chat\Models;

use Modules\Auth\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = ['conversation_id', 'sender_id', 'body', 'kind', 'client_id'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** The API shape; a deleted message keeps its place but loses its text. */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->sender?->name,
            'body' => $this->trashed() ? '' : $this->body,
            'kind' => $this->kind,
            'deleted' => $this->trashed(),
            'client_id' => $this->client_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
