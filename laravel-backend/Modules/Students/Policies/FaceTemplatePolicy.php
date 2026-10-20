<?php

namespace Modules\Students\Policies;

use Modules\Auth\Models\User;
use Modules\Groups\Models\ClassGroup;
use Modules\Students\Models\Student;

/**
 * Who may read, enrol or remove a student's face template (ADR 0002):
 * admin, or a teacher whose groups include the student. Biometric data
 * (§13.1 + §23), so the same "own groups only" rule as Payments/Exams/
 * Attendance applies, resolved via the student's group string.
 *
 * Registered against `FaceTemplate::class`, not `Student::class`: the dead
 * `StudentPolicy` stub already claims `Student::class` (and unconditionally
 * denies), so this takes the related-model-as-resource shape instead —
 * `$user->can('update', [FaceTemplate::class, $student])` — rather than
 * colliding with it.
 */
class FaceTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAnyRole('admin', 'teacher');
    }

    public function update(User $user, Student $student): bool
    {
        if ($user->isAnyRole('admin')) {
            return true;
        }

        return $user->isAnyRole('teacher') && $this->groupOwned($user, $student->group);
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->update($user, $student);
    }

    /** A group name a teacher may act on: theirs, unassigned, or unknown to Groups at all. */
    private function groupOwned(User $user, ?string $groupName): bool
    {
        return $groupName !== null && ! in_array($groupName, ClassGroup::namesClaimedByOthers($user), true);
    }
}
