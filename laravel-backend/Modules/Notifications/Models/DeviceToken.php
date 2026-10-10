<?php

namespace Modules\Notifications\Models;

use Modules\Auth\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A push-notification registration of one signed-in device. */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'platform'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
