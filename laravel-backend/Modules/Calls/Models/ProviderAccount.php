<?php

namespace Modules\Calls\Models;

use Illuminate\Database\Eloquent\Model;

/** A connected Google or Zoom account; tokens are encrypted at rest. */
class ProviderAccount extends Model
{
    protected $fillable = ['user_id', 'provider', 'access_token', 'refresh_token', 'expires_at'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'expires_at' => 'datetime'];
    }
}
