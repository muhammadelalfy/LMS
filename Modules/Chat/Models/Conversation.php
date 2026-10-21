<?php

namespace Modules\Chat\Models;

use Modules\Groups\Models\ClassGroup;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A direct conversation between two people, or the channel of one group.
 * Who may be in it is decided by Modules\Chat\Services\ChatAccess, not stored.
 */
class Conversation extends Model
{
    public const DIRECT = 'direct';

    public const GROUP = 'group';

    protected $fillable = ['type', 'group_id', 'direct_key', 'announce_only', 'created_by', 'last_message_id', 'last_message_at'];

    protected function casts(): array
    {
        return ['announce_only' => 'boolean', 'last_message_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class, 'group_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isGroup(): bool
    {
        return $this->type === self::GROUP;
    }

    /** "smaller-id:larger-id" so a pair never gets two conversations. */
    public static function directKey(int $a, int $b): string
    {
        return min($a, $b).':'.max($a, $b);
    }
}
