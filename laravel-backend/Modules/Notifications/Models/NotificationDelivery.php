<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

/** What happened to one notice for one person on one channel. */
class NotificationDelivery extends Model
{
    protected $fillable = [
        'ref', 'user_id', 'channel', 'category', 'title', 'body', 'state', 'reason', 'provider_id',
        'attempts', 'send_after', 'sent_at', 'delivered_at', 'read_at',
    ];

    protected function casts(): array
    {
        return ['send_after' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
