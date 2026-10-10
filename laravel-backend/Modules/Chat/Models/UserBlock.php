<?php

namespace Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;

/** [blocker] does not want to hear from [blocked] in direct conversations. */
class UserBlock extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];
}
