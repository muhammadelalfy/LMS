<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Services\ChatAccess;

// All schools share one realtime server, so every channel name starts with the
// school ("school.alnour.user.5") and a rule only admits people of that school.

// Your own channel: conversations opened with you, incoming calls.
Broadcast::channel('school.{school}.user.{id}', fn ($user, $school, $id) => $school === (string) tenant('id')
    && (int) $user->id === (int) $id);

// A conversation's messages: only people the chat rules let in.
Broadcast::channel('school.{school}.conversation.{id}', function ($user, $school, $id) {
    if ($school !== (string) tenant('id')) {
        return false;
    }
    $conversation = Conversation::query()->find($id);

    return $conversation !== null && app(ChatAccess::class)->canAccess($user, $conversation);
});
