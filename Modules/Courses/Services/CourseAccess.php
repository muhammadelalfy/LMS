<?php

declare(strict_types=1);

namespace Modules\Courses\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;
use Modules\Courses\Enums\ContentStatus;
use Modules\Courses\Enums\SequencingMode;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseLesson;
use Modules\Courses\Models\Enrollment;
use Modules\Courses\Models\LessonProgress;
use Modules\Payments\Models\Payment;
use Modules\Students\Models\Student;

/** Who is in a course, what they have paid for, and how far they have got. */
final class CourseAccess
{
    /** The student a signed-in student or parent acts for. */
    public function studentOf(User $user): ?Student
    {
        return $user->isAnyRole('student', 'parent') ? $user->studentAccount?->student : null;
    }

    /**
     * Puts a student in a course. A paid course creates an ordinary payment for them, which the existing
     * online or staff-recorded payment flow settles; the course opens when that payment is paid.
     */
    public function enroll(Course $course, User $user, Student $student): Enrollment
    {
        $existing = Enrollment::query()->where(['course_id' => $course->id, 'student_id' => $student->id])->first();
        if ($existing) {
            return $existing->load('payment');
        }

        return DB::transaction(function () use ($course, $user, $student): Enrollment {
            $payment = null;
            if (($price = $course->finalPrice()) !== null) {
                $payment = Payment::create([
                    'student_id' => $student->id,
                    'amount' => $price,
                    'status' => 'pending',
                    'due_at' => now()->toDateString(),
                    'note' => 'اشتراك في دورة: '.$course->title,
                    'recorded_by' => $user->id,
                ]);
            }

            return Enrollment::create(['course_id' => $course->id, 'student_id' => $student->id, 'payment_id' => $payment?->id])->load('payment');
        });
    }

    /** The published lessons of published sections, in the order a student meets them. */
    public function orderedLessons(Course $course): Collection
    {
        $published = ContentStatus::Published->value;

        return CourseLesson::query()
            ->select('course_lessons.*')
            ->join('course_subjects', 'course_subjects.id', '=', 'course_lessons.subject_id')
            ->where('course_subjects.course_id', $course->id)
            ->where('course_subjects.status', $published)
            ->where('course_lessons.status', $published)
            ->orderBy('course_subjects.position')->orderBy('course_subjects.id')
            ->orderBy('course_lessons.position')->orderBy('course_lessons.id')
            ->get();
    }

    /** @return list<int> */
    public function completedLessonIds(Course $course, Student $student): array
    {
        return LessonProgress::query()
            ->where('student_id', $student->id)
            ->whereIn('lesson_id', $this->orderedLessons($course)->pluck('id'))
            ->pluck('lesson_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Lessons a student cannot open yet: with "after the previous one" sequencing, everything after the
     * first lesson they have not completed.
     *
     * @param  list<int>  $completed
     * @return list<int>
     */
    public function lockedLessonIds(Course $course, Collection $orderedLessons, array $completed): array
    {
        if ($course->sequencing_mode === SequencingMode::Open) {
            return [];
        }

        $locked = [];
        $previousDone = true;
        foreach ($orderedLessons as $lesson) {
            if (! $previousDone) {
                $locked[] = $lesson->id;
            }
            if (! in_array($lesson->id, $completed, true)) {
                $previousDone = false;
            }
        }

        return $locked;
    }

    /** Marks a lesson done, and the enrollment complete once every published lesson is. */
    public function complete(CourseLesson $lesson, Course $course, Enrollment $enrollment): void
    {
        LessonProgress::query()->firstOrCreate(
            ['lesson_id' => $lesson->id, 'student_id' => $enrollment->student_id],
            ['completed_at' => now()],
        );

        $remaining = $this->orderedLessons($course)->pluck('id')
            ->diff($this->completedLessonIds($course, $enrollment->student))->isNotEmpty();
        if (! $remaining && $enrollment->completed_at === null) {
            $enrollment->update(['completed_at' => now()]);
        }
    }
}
