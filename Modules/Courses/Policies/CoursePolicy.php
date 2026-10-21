<?php

declare(strict_types=1);

namespace Modules\Courses\Policies;

use Modules\Auth\Models\User;
use Modules\Courses\Models\Course;

/** An admin runs every course; a teacher runs the courses they teach (ADR 0002). */
final class CoursePolicy
{
    public function create(User $user): bool
    {
        return $user->isAnyRole('admin', 'teacher');
    }

    public function view(User $user, Course $course): bool
    {
        return $this->manages($user, $course);
    }

    public function update(User $user, Course $course): bool
    {
        return $this->manages($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->manages($user, $course);
    }

    public function publish(User $user, Course $course): bool
    {
        return $this->manages($user, $course);
    }

    /** Naming who teaches a course is management's call, as with a group's teacher. */
    public function assignInstructors(User $user, Course $course): bool
    {
        return $user->isAnyRole('admin');
    }

    private function manages(User $user, Course $course): bool
    {
        return $user->isAnyRole('admin') || ($user->isAnyRole('teacher') && $course->isTaughtBy($user));
    }
}
