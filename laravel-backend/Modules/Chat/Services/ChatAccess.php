<?php

namespace Modules\Chat\Services;

use Modules\Groups\Models\ClassGroup;
use Modules\Chat\Models\Conversation;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentAccount;
use Modules\Auth\Models\User;
use Modules\Chat\Models\UserBlock;
use Illuminate\Support\Collection;

/**
 * Who may talk to whom. The one place these rules live; the REST endpoints
 * and the WebSocket channel authorisation both ask it.
 *
 *  - management ↔ teachers;
 *  - a teacher ↔ the students of the groups that teacher runs (a group with
 *    no teacher named is open to every teacher);
 *  - a group channel: management, the group's teacher and its students.
 *
 * Parents, and students among themselves, never chat.
 */
class ChatAccess
{
    public function canChat(User $a, User $b): bool
    {
        if ($a->id === $b->id) {
            return false;
        }
        [$staff, $other] = $a->isAnyRole('admin', 'teacher') ? [$a, $b] : [$b, $a];
        if (! $staff->isAnyRole('admin', 'teacher')) {
            return false;
        }

        return match (true) {
            $staff->isAnyRole('admin') => $other->isAnyRole('teacher'),
            $other->isAnyRole('admin') => true,
            $other->isAnyRole('student') => $this->teachesStudent($staff, $other),
            default => false,
        };
    }

    /** Whether [$teacher] runs the group [$studentUser] belongs to. */
    public function teachesStudent(User $teacher, User $studentUser): bool
    {
        $group = $this->groupOf($studentUser);

        return $group !== null && ($group->teacher_id === null || $group->teacher_id === $teacher->id);
    }

    public function groupOf(User $studentUser): ?ClassGroup
    {
        $name = Student::query()
            ->whereIn('id', StudentAccount::query()->where(['user_id' => $studentUser->id, 'relationship' => 'student'])->select('student_id'))
            ->value('group');

        return $name === null ? null : ClassGroup::query()->where('name', $name)->first();
    }

    /** Whether [$user] may read a conversation (and, via canPost, write). */
    public function canAccess(User $user, Conversation $conversation): bool
    {
        if ($conversation->isGroup()) {
            return $this->canAccessGroup($user, $conversation->group);
        }
        $other = $this->otherParty($conversation, $user);

        return $other !== null && $this->canChat($user, $other);
    }

    public function canPost(User $user, Conversation $conversation): bool
    {
        if (! $this->canAccess($user, $conversation)) {
            return false;
        }
        if ($conversation->isGroup()) {
            return $user->isAnyRole('admin', 'teacher') || ! $conversation->announce_only;
        }

        $other = $this->otherParty($conversation, $user);

        return ! $this->blocked($user, $other);
    }

    /** Whether either has blocked the other. */
    public function blocked(User $a, User $b): bool
    {
        return UserBlock::query()
            ->where(fn ($query) => $query->where(['blocker_id' => $a->id, 'blocked_id' => $b->id]))
            ->orWhere(fn ($query) => $query->where(['blocker_id' => $b->id, 'blocked_id' => $a->id]))
            ->exists();
    }

    /** Everyone who may read the conversation now, for broadcasting and push. */
    public function participants(Conversation $conversation): Collection
    {
        if (! $conversation->isGroup()) {
            return User::query()->whereKey($this->directIds($conversation))->get();
        }
        $group = $conversation->group;
        $students = User::query()->whereIn('id', StudentAccount::query()
            ->where('relationship', 'student')
            ->whereIn('student_id', Student::query()->where('group', $group->name)->select('id'))
            ->select('user_id'))->get();
        $staff = User::query()->where(
            fn ($query) => $query->where('role', 'admin')
                ->orWhere(fn ($teachers) => $teachers->where('role', 'teacher')
                    ->when($group->teacher_id, fn ($inner) => $inner->where('id', $group->teacher_id))),
        )->get();

        return $staff->merge($students)->unique('id')->values();
    }

    /** Who a person may start a conversation with. */
    public function contacts(User $user): Collection
    {
        if ($user->isAnyRole('admin')) {
            return User::query()->where('role', 'teacher')->orderBy('name')->get();
        }
        if ($user->isAnyRole('teacher')) {
            $groupNames = ClassGroup::query()
                ->where(fn ($query) => $query->whereNull('teacher_id')->orWhere('teacher_id', $user->id))
                ->pluck('name');
            $students = User::query()->whereIn('id', StudentAccount::query()
                ->where('relationship', 'student')
                ->whereIn('student_id', Student::query()->whereIn('group', $groupNames)->select('id'))
                ->select('user_id'))->orderBy('name')->get();

            return User::query()->where('role', 'admin')->orderBy('name')->get()->merge($students);
        }
        if ($user->isAnyRole('student')) {
            $group = $this->groupOf($user);
            if ($group === null) {
                return collect();
            }

            return User::query()->where('role', 'teacher')
                ->when($group->teacher_id, fn ($query) => $query->where('id', $group->teacher_id))
                ->orderBy('name')->get();
        }

        return collect();
    }

    public function canAccessGroup(User $user, ?ClassGroup $group): bool
    {
        if ($group === null) {
            return false;
        }
        if ($user->isAnyRole('admin')) {
            return true;
        }
        if ($user->isAnyRole('teacher')) {
            return $group->teacher_id === null || $group->teacher_id === $user->id;
        }

        return $user->isAnyRole('student') && $this->groupOf($user)?->id === $group->id;
    }

    public function otherParty(Conversation $conversation, User $user): ?User
    {
        $otherId = collect($this->directIds($conversation))->first(fn (int $id) => $id !== $user->id);
        if ($otherId === null || ! in_array($user->id, $this->directIds($conversation), true)) {
            return null;
        }

        return User::query()->find($otherId);
    }

    /** @return array<int, int> */
    private function directIds(Conversation $conversation): array
    {
        return array_map('intval', explode(':', (string) $conversation->direct_key));
    }
}
