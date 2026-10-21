<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

/** A verified phone a person can be reached on over one paid channel. */
class ContactChannel extends Model
{
    protected $fillable = ['user_id', 'channel', 'address', 'verified_at', 'opted_in_at', 'opted_out_at', 'source'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'opted_in_at' => 'datetime', 'opted_out_at' => 'datetime'];
    }

    /** Verified, switched on, and not stopped by the person. */
    public function canReceive(): bool
    {
        return $this->verified_at !== null && $this->opted_in_at !== null && $this->opted_out_at === null;
    }

    public function scopeReachable($query)
    {
        return $query->whereNotNull('verified_at')->whereNotNull('opted_in_at')->whereNull('opted_out_at');
    }
}
