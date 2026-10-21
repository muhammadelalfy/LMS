<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

/** The code sent to a phone number to prove it belongs to the person. */
class PhoneVerification extends Model
{
    protected $fillable = ['user_id', 'phone', 'code_hash', 'attempts', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
