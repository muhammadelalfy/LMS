<?php

namespace Modules\Groups\Policies;

use Modules\Auth\Models\User;
use Modules\Groups\Models\ClassGroup;

/**
 * Who may create, update or delete a group's timetable (ADR 0002). Reading
 * the list is scoped directly in the controller (own + unassigned for a
 * teacher), since a Policy answers one-resource questions, not "which rows".
 */
class ClassGroupPolicy
{
    /** A teacher may only name themselves as `teacher_id`, or leave it unset; an admin may name anyone. */
    public function create(User $user, array $data = []): bool
    {
        if ($user->isAnyRole('admin')) {
            return true;
        }
        if (! $user->isAnyRole('teacher')) {
            return false;
        }
        $teacherId = $data['teacher_id'] ?? null;

        return $teacherId === null || (int) $teacherId === $user->id;
    }

    /**
     * A teacher may update a group that is theirs or unassigned, but never
     * reassign it: "edit my own group's schedule" and "reassign this group
     * to someone else" are two different operations (ADR 0002), so any
     * `teacher_id` key in the request is rejected for a non-admin outright.
     */
    public function update(User $user, ClassGroup $group, array $data = []): bool
    {
        if ($user->isAnyRole('admin')) {
            return true;
        }
        if (! $user->isAnyRole('teacher') || array_key_exists('teacher_id', $data)) {
            return false;
        }

        return $group->isOpenTo($user);
    }

    /** Deleting a group is structural, not scheduling — admin-only (ADR 0002). */
    public function delete(User $user, ClassGroup $group): bool
    {
        return $user->isAnyRole('admin');
    }
}
