<?php

namespace Modules\Learning\Policies;

use Modules\Auth\Models\User;
use Modules\Groups\Models\ClassGroup;
use Modules\Learning\Models\Duty;

/**
 * Who may write a group's homework (ADR 0002) — the same defect shape found
 * in Groups/Exams/Attendance: a teacher must be confined to the group(s)
 * they teach, or an unclaimed one, not any group string in the school.
 */
class DutyPolicy
{
    public function create(User $user, array $data = []): bool
    {
        if ($user->isAnyRole('admin')) {
            return true;
        }

        return $user->isAnyRole('teacher') && $this->groupOwned($user, $data['group'] ?? null);
    }

    /** A teacher may move a duty between groups only if they own both ends. */
    public function update(User $user, Duty $duty, array $data = []): bool
    {
        if ($user->isAnyRole('admin')) {
            return true;
        }
        if (! $user->isAnyRole('teacher') || ! $this->groupOwned($user, $duty->group)) {
            return false;
        }

        return ! array_key_exists('group', $data) || $this->groupOwned($user, $data['group']);
    }

    public function delete(User $user, Duty $duty): bool
    {
        if ($user->isAnyRole('admin')) {
            return true;
        }

        return $user->isAnyRole('teacher') && $this->groupOwned($user, $duty->group);
    }

    /**
     * A group name a teacher may act on: theirs, unassigned, or — since a
     * duty's `group` is a free string with no foreign key (same fragility as
     * `Student.group`, ADR 0002) — one that does not (yet) match any real
     * `ClassGroup` row at all, which is treated the same as "unassigned".
     */
    private function groupOwned(User $user, ?string $groupName): bool
    {
        if ($groupName === null) {
            return false;
        }
        $group = ClassGroup::query()->where('name', $groupName)->first();

        return $group === null || $group->isOpenTo($user);
    }
}
