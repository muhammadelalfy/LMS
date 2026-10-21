<?php

namespace Modules\Learning\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Homework for one group, due on a lesson day. Staff tick it done by hand;
 * the group is reminded once, on the day it is due.
 */
class Duty extends Model
{
    protected $fillable = ['group', 'title', 'details', 'due_on', 'done_at', 'reminded_at', 'created_by'];

    protected function casts(): array
    {
        return ['done_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    protected $appends = ['done'];

    public function getDoneAttribute(): bool
    {
        return $this->done_at !== null;
    }
}
