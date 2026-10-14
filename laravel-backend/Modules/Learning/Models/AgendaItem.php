<?php

namespace Modules\Learning\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A teacher's note or task to do with one group on one day. Same shape as a
 * duty (homework) without the reminder: it is for the teacher, so nobody is
 * notified and learners never see it.
 */
class AgendaItem extends Model
{
    protected $fillable = ['group', 'title', 'details', 'due_on', 'done_at', 'created_by'];

    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }

    protected $appends = ['done'];

    public function getDoneAttribute(): bool
    {
        return $this->done_at !== null;
    }
}
