<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationInboxController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->notifications()->latest()->paginate(50)
            ->through(fn (DatabaseNotification $notification) => $this->present($notification));
    }

    public function markRead(Request $request, string $notification)
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return $this->present($record->fresh());
    }

    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? 'إشعار',
            'body' => $notification->data['body'] ?? '',
            'category' => $notification->data['category'] ?? 'general',
            'created_at' => $notification->created_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
        ];
    }
}
