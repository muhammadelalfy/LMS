<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SchoolUpdateNotification extends Notification
{
    use Queueable;

    /** @param  string|null  $ref  ties the inbox entry to its channel deliveries */
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $category,
        private readonly ?string $ref = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_filter(
            ['title' => $this->title, 'body' => $this->body, 'category' => $this->category, 'ref' => $this->ref],
            fn ($value) => $value !== null,
        );
    }
}
