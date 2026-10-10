<?php

namespace Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;

/** A person flagging a message for management to review. */
class MessageReport extends Model
{
    protected $fillable = ['message_id', 'reporter_id', 'reason'];
}
