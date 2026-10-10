<?php

namespace Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;

/** Management reading a conversation it is not part of, for child safety. */
class ChatAuditLog extends Model
{
    protected $fillable = ['staff_id', 'conversation_id', 'action'];
}
