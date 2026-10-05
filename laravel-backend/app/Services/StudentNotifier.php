<?php

namespace App\Services;

use App\Models\StudentAccount;
use App\Models\User;
use App\Notifications\SchoolUpdateNotification;
use Illuminate\Support\Facades\Notification;

class StudentNotifier
{
    /**
     * Notify every student and parent account linked to the given students.
     *
     * @param  array<int, int>  $studentIds
     */
    public function notify(array $studentIds, string $title, string $body, string $category): void
    {
        $userIds = StudentAccount::query()->whereIn('student_id', $studentIds)->pluck('user_id')->unique();
        if ($userIds->isEmpty()) {
            return;
        }

        Notification::send(
            User::query()->whereKey($userIds)->get(),
            new SchoolUpdateNotification($title, $body, $category),
        );
    }
}
