<?php

namespace Modules\Groups\Models;

use Illuminate\Database\Eloquent\Model;

/** Marks that a group's "session starts soon" reminder was sent for a day. */
class SessionReminder extends Model
{
    protected $fillable = ['class_group_id', 'day'];
}
