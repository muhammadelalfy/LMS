<?php

namespace Modules\Notifications\Services;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Auth\Models\User;
use Modules\Notifications\Models\NotificationPreference;
use Modules\Notifications\Notifications\SchoolUpdateNotification;
use Modules\Students\Models\StudentAccount;

class StudentNotifier
{
    public function __construct(
        private readonly FcmPushSender $push,
        private readonly FallbackScheduler $fallbacks,
    ) {}

    /**
     * Notify the accounts linked to the given students: their inbox always,
     * and their phones by push when Firebase is configured.
     *
     * @param  array<int, int>  $studentIds
     * @param  array<int, string>|null  $relationships  limit to 'parent' and/or 'student' accounts
     * @return int the number of accounts notified
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

        return $this->notifyUsers(User::query()->whereKey($userIds)->get(), $title, $body, $category);
    }

    /**
     * Notify the given accounts directly (staff, for example): their inbox
     * always, their phones by push when Firebase is configured (unless they
     * turned push off for a non-critical category), and, for categories with a
     * fallback chain, WhatsApp and SMS for those who switched them on.
     *
     * @param  Collection<int, User>  $users
     * @return int the number of accounts notified
     */
    public function notifyUsers(Collection $users, string $title, string $body, string $category): int
    {
        if ($users->isEmpty()) {
            return 0;
        }
        $ref = (string) Str::uuid();
        $this->writeInbox($users, new SchoolUpdateNotification($title, $body, $category, $ref));
        $this->push->send($this->pushRecipients($users, $category), $title, $body, ['category' => $category]);
        $this->fallbacks->schedule($users, $ref, $title, $body, $category);

        return $users->count();
    }

    /** One insert for every inbox entry (Notification::send would run one query per person). */
    private function writeInbox(Collection $users, SchoolUpdateNotification $notification): void
    {
        $now = now();
        $data = json_encode($notification->toArray($users->first()), JSON_UNESCAPED_UNICODE);

        DatabaseNotification::query()->insert($users->map(fn (User $user) => [
            'id' => (string) Str::uuid(),
            'type' => $notification::class,
            'data' => $data,
            'read_at' => null,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    /**
     * Push only, with no inbox entry: for chatty events such as a chat
     * message. People who turned push off for [$category] are skipped.
     *
     * @param  Collection<int, User>  $users
     * @param  array<string, string>  $data
     */
    public function pushOnly(Collection $users, string $title, string $body, string $category, array $data = []): void
    {
        $this->push->send($this->pushRecipients($users, $category), $title, $body, ['category' => $category, ...$data]);
    }

    /** @return Collection<int, User> */
    private function pushRecipients(Collection $users, string $category): Collection
    {
        if (config("notifications.categories.{$category}.critical", false)) {
            return $users;
        }
        $off = NotificationPreference::disabledUsers($category, 'push', $users->modelKeys());

        return $users->reject(fn (User $user) => $off->contains($user->id));
    }
}
