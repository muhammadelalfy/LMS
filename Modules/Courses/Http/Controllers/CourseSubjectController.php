<?php

declare(strict_types=1);

namespace Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Http\Concerns\AuthorizesStaff;
use Modules\Courses\Enums\ContentStatus;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseSubject;
use Modules\Courses\Services\CourseOutline;

/** The sections of a course. */
class CourseSubjectController extends Controller
{
    use AuthorizesStaff;

    public function __construct(private readonly CourseOutline $outline) {}

    public function store(Request $request, Course $course)
    {
        $this->authorizeFor($request, $course);

        return response()->json($this->shape($this->outline->addSubject($course, $this->validated($request))), 201);
    }

    public function update(Request $request, CourseSubject $subject)
    {
        $this->authorizeFor($request, $subject->course);
        $subject->update($this->validated($request, partial: true));

        return $this->shape($subject->fresh());
    }

    public function destroy(Request $request, CourseSubject $subject)
    {
        $this->authorizeFor($request, $subject->course);
        $this->outline->delete($subject);

        return response()->noContent();
    }

    public function status(Request $request, CourseSubject $subject)
    {
        $this->authorizeFor($request, $subject->course);
        $data = $request->validate(['status' => ['required', Rule::enum(ContentStatus::class)]]);

        return $this->shape($this->outline->changeStatus($subject, ContentStatus::from($data['status'])));
    }

    public function order(Request $request, Course $course)
    {
        $this->authorizeFor($request, $course);
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'distinct']])['ids'];
        $this->outline->reorder($course->subjects(), $ids);

        return response()->noContent();
    }

    private function authorizeFor(Request $request, Course $course): void
    {
        $this->authorizeStaff($request);
        abort_unless($request->user()->can('update', $course), 403, 'لا تملك صلاحية هذه الدورة.');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function shape(CourseSubject $subject): array
    {
        return ['id' => $subject->id, 'course_id' => $subject->course_id, 'title' => $subject->title, 'description' => $subject->description,
            'position' => $subject->position, 'status' => $subject->status->value];
    }
}
