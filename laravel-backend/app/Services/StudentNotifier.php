<?php

namespace App\Services;

use App\Models\StudentAccount;
use App\Models\User;
use App\Notifications\SchoolUpdateNotification;
use Illuminate\Support\Facades\Notification;

class StudentNotifier
{
    public function __construct(private readonly FcmPushSender $push)
    {
    }

    /**
     * Notify the accounts linked to the given students: their inbox always,
     * and their phones by push when Firebase is configured.
     *
     * @param  array<int, int>  $studentIds
     * @param  array<int, string>|null  $relationships  limit to 'parent' and/or 'student' accounts
     * @return int  the number of accounts notified
     */
    public function notify(
        array $studentIds,
        string $title,
        string $body,
        string $category,
        ?array $relationships = null,
    ): int {
        $userIds = StudentAccount::query()
            ->whereIn('student_id', $studentIds)
            ->when($relationships !== null, fn ($query) => $query->whereIn('relationship', $relationships))
            ->pluck('user_id')
            ->unique();
        if ($userIds->isEmpty()) {
            return 0;
        }

        $users = User::query()->whereKey($userIds)->get();
        Notification::send($users, new SchoolUpdateNotification($title, $body, $category));
        $this->push->send($users, $title, $body, ['category' => $category]);

        return $users->count();
    }
}
