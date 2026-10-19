<?php

namespace Modules\Attendance\Policies;

use Modules\Attendance\Models\AttendanceRecord;
use Modules\Auth\Models\User;
use Modules\Groups\Models\ClassGroup;
use Modules\Students\Models\Student;

/**
 * Who may write a student's manual attendance record (ADR 0002): admin, or
 * a teacher whose groups include the record's student. `scan`/`sweep`/
 * `remindSessions` are unaffected — they stay gated by `authorizeStaff()`
 * alone, by design (front-desk/gate check-in and batch triggers).
 */
class AttendanceRecordPolicy
{
    public function create(User $user, array $data = []): bool
    {
        if ($user->isAnyRole('admin')) {
            return true;
        }
        if (! $user->isAnyRole('teacher')) {
            return false;
        }
        $student = Student::query()->find($data['student_id'] ?? null);

        return $student !== null && $this->groupOwned($user, $student->group);
    }

    public function update(User $user, AttendanceRecord $attendance): bool
    {
        return $this->owns($user, $attendance);
    }

    public function delete(User $user, AttendanceRecord $attendance): bool
    {
        return $this->owns($user, $attendance);
    }

    private function owns(User $user, AttendanceRecord $attendance): bool
    {
        if ($user->isAnyRole('admin')) {
            return true;
        }

        return $user->isAnyRole('teacher') && $this->groupOwned($user, $attendance->student?->group);
    }

    /** A group name a teacher may act on: theirs, unassigned, or unknown to Groups at all. */
    private function groupOwned(User $user, ?string $groupName): bool
    {
        return $groupName !== null && ! in_array($groupName, ClassGroup::namesClaimedByOthers($user), true);
    }
}
