<?php

namespace Modules\Calls\Models;

use Illuminate\Database\Eloquent\Model;

class CallParticipant extends Model
{
    protected $fillable = ['call_id', 'user_id', 'state', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }
}
