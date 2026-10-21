<?php

declare(strict_types=1);

namespace Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Courses\Enums\ContentStatus;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseLesson;
use Modules\Courses\Models\Enrollment;
use Modules\Courses\Services\CourseAccess;
use Modules\Courses\Services\CoursePresenter;
use Modules\Students\Models\Student;

/** A student's own courses: the list, one course with their progress, and finishing lessons. */
class MyCoursesController extends Controller
{
    public function __construct(private readonly CourseAccess $access) {}

    public function index(Request $request)
    {
        $student = $this->student($request);

        $enrollments = Enrollment::query()->where('student_id', $student->id)->with(['course.instructors:id,name', 'payment'])->latest('id')->get()
            ->filter(fn (Enrollment $e) => $e->course->isOpenToStudents());

        return ['data' => $enrollments->values()->map(function (Enrollment $e) {
            $total = $this->access->orderedLessons($e->course)->count();
            $done = $e->isActive() ? count($this->access->completedLessonIds($e->course, $e->student)) : 0;

            return CoursePresenter::summary($e->course) + [
                'enrollment' => ['status' => $e->isActive() ? 'active' : 'awaiting_payment', 'payment_id' => $e->payment_id, 'completed' => $e->completed_at !== null],
                'progress' => ['completed_lessons' => $done, 'total_lessons' => $total],
            ];
        })->all()];
    }

    public function show(Request $request, Course $course)
    {
        [$student, $enrollment] = $this->enrollmentFor($request, $course);
        $course->load(['subjects.lessons', 'instructors:id,name']);

        if (! $enrollment->isActive()) {
            // Joined but not yet paid: the outline is shown, the lessons' content is not.
            return CoursePresenter::outline($course, protected: false) + [
                'enrollment' => ['status' => 'awaiting_payment', 'payment_id' => $enrollment->payment_id, 'amount' => $enrollment->payment?->amount],
            ];
        }

        $ordered = $this->access->orderedLessons($course);
        $completed = $this->access->completedLessonIds($course, $student);
        $locked = $this->access->lockedLessonIds($course, $ordered, $completed);

        return CoursePresenter::outline($course, protected: true, completed: $completed, locked: $locked) + [
            'enrollment' => ['status' => 'active', 'payment_id' => $enrollment->payment_id, 'completed' => $enrollment->completed_at !== null],
            'progress' => ['completed_lessons' => count($completed), 'total_lessons' => $ordered->count()],
        ];
    }

    public function complete(Request $request, CourseLesson $lesson)
    {
        $course = $lesson->subject->course;
        [$student, $enrollment] = $this->enrollmentFor($request, $course);
        abort_unless($enrollment->isActive(), 402, 'أكمل سداد الدورة لتفتح دروسها.');
        abort_unless(
            $lesson->status === ContentStatus::Published && $lesson->subject->status === ContentStatus::Published,
            404,
        );

        $ordered = $this->access->orderedLessons($course);
        $locked = $this->access->lockedLessonIds($course, $ordered, $this->access->completedLessonIds($course, $student));
        abort_if(in_array($lesson->id, $locked, true), 403, 'أكمل الدرس السابق أولاً.');

        $this->access->complete($lesson, $course, $enrollment);
        $done = count($this->access->completedLessonIds($course, $student));

        return ['completed_lessons' => $done, 'total_lessons' => $ordered->count(), 'course_completed' => $enrollment->fresh()->completed_at !== null];
    }

    private function student(Request $request): Student
    {
        $student = $this->access->studentOf($request->user());
        abort_unless($student, 403, 'هذه الصفحة لحساب الطالب أو ولي الأمر.');

        return $student;
    }

    /** @return array{0: Student, 1: Enrollment} */
    private function enrollmentFor(Request $request, Course $course): array
    {
        $student = $this->student($request);
        $enrollment = Enrollment::query()->where(['course_id' => $course->id, 'student_id' => $student->id])->with('payment')->first();
        abort_unless($enrollment && $course->isOpenToStudents(), 404);

        return [$student, $enrollment];
    }
}
