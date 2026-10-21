<?php

declare(strict_types=1);

namespace Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Http\Concerns\AuthorizesStaff;
use Modules\Courses\Enums\ContentStatus;
use Modules\Courses\Http\Concerns\ConvertsToSchoolTime;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseLesson;
use Modules\Courses\Models\CourseSubject;
use Modules\Courses\Services\CourseOutline;

/** The lessons of a section, including live ones that meet by a Meet or Zoom link. */
class CourseLessonController extends Controller
{
    use AuthorizesStaff;
    use ConvertsToSchoolTime;

    public function __construct(private readonly CourseOutline $outline) {}

    public function store(Request $request, CourseSubject $subject)
    {
        $this->authorizeFor($request, $subject->course);

        return response()->json($this->shape($this->outline->addLesson($subject, $this->validated($request))), 201);
    }

    public function update(Request $request, CourseLesson $lesson)
    {
        $this->authorizeFor($request, $lesson->subject->course);
        $lesson->update($this->validated($request, $lesson));

        return $this->shape($lesson->fresh());
    }

    public function destroy(Request $request, CourseLesson $lesson)
    {
        $this->authorizeFor($request, $lesson->subject->course);
        $this->outline->delete($lesson);

        return response()->noContent();
    }

    public function status(Request $request, CourseLesson $lesson)
    {
        $this->authorizeFor($request, $lesson->subject->course);
        $data = $request->validate(['status' => ['required', Rule::enum(ContentStatus::class)]]);

        return $this->shape($this->outline->changeStatus($lesson, ContentStatus::from($data['status'])));
    }

    public function order(Request $request, CourseSubject $subject)
    {
        $this->authorizeFor($request, $subject->course);
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'distinct']])['ids'];
        $this->outline->reorder($subject->lessons(), $ids);

        return response()->noContent();
    }

    private function authorizeFor(Request $request, Course $course): void
    {
        $this->authorizeStaff($request);
        abort_unless($request->user()->can('update', $course), 403, 'لا تملك صلاحية هذه الدورة.');
    }

    private function validated(Request $request, ?CourseLesson $lesson = null): array
    {
        $data = $request->validate([
            'title' => [$lesson ? 'sometimes' : 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'key_points' => ['nullable', 'array', 'max:30'],
            'key_points.*' => ['string', 'max:300'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'live_at' => ['nullable', 'date'],
            'live_provider' => ['nullable', Rule::in(['meet', 'zoom', 'other'])],
            'live_link' => ['nullable', 'url', 'max:500', 'required_with:live_at'],
        ]);
        // A live lesson without a stated provider is simply "another" kind of link.
        if (! empty($data['live_link']) && empty($data['live_provider'])) {
            $data['live_provider'] = 'other';
        }

        return $this->inSchoolTime($data, ['live_at']);
    }

    private function shape(CourseLesson $lesson): array
    {
        return ['id' => $lesson->id, 'subject_id' => $lesson->subject_id, 'title' => $lesson->title, 'position' => $lesson->position,
            'status' => $lesson->status->value, 'live_at' => $lesson->live_at?->toIso8601String(), 'live_provider' => $lesson->live_provider,
            'live_link' => $lesson->live_link, 'video_url' => $lesson->video_url];
    }
}
