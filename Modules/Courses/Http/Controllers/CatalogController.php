<?php

declare(strict_types=1);

namespace Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Courses\Enums\ContentStatus;
use Modules\Courses\Enums\CourseKind;
use Modules\Courses\Enums\Pricing;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\Enrollment;
use Modules\Courses\Services\CourseAccess;
use Modules\Courses\Services\CoursePresenter;

/** What the school offers: browse the published courses, read one, and join it. */
class CatalogController extends Controller
{
    public function __construct(private readonly CourseAccess $access) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'kind' => ['nullable', Rule::enum(CourseKind::class)],
            'pricing' => ['nullable', Rule::enum(Pricing::class)],
            'featured' => ['nullable', 'boolean'],
        ]);

        $page = Course::query()
            ->publiclyVisible()
            ->withCount(['lessons as published_lessons_count' => fn ($q) => $q
                ->where('course_lessons.status', ContentStatus::Published->value)
                ->where('course_subjects.status', ContentStatus::Published->value)])
            ->with('instructors:id,name')
            ->when($filters['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($filters['pricing'] ?? null, fn ($q, $pricing) => $q->where('pricing', $pricing))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->orderByDesc('id')
            ->paginate(12);

        $mine = $this->enrollmentsOf($request)->keyBy('course_id');
        $page->getCollection()->transform(fn (Course $course) => CoursePresenter::summary($course)
            + ['enrollment' => $this->stateOf($mine->get($course->id))]);

        return $page;
    }

    public function show(Request $request, string $slug)
    {
        $course = $this->visible($slug)->load(['subjects.lessons', 'instructors:id,name']);
        $enrollment = $this->enrollmentsOf($request)->firstWhere('course_id', $course->id);

        return CoursePresenter::outline($course, protected: false) + ['enrollment' => $this->stateOf($enrollment)];
    }

    public function enroll(Request $request, string $slug)
    {
        $user = $request->user();
        $student = $this->access->studentOf($user);
        abort_unless($student, 403, 'التسجيل في الدورات متاح لحساب الطالب أو ولي الأمر.');

        $course = $this->visible($slug);
        $enrollment = $this->access->enroll($course, $user, $student);

        return response()->json(['enrollment' => $this->stateOf($enrollment), 'course_id' => $course->id], $enrollment->wasRecentlyCreated ? 201 : 200);
    }

    private function visible(string $slug): Course
    {
        return Course::query()->publiclyVisible()->where('slug', $slug)->firstOrFail();
    }

    private function enrollmentsOf(Request $request)
    {
        $student = $this->access->studentOf($request->user());

        return $student ? Enrollment::query()->where('student_id', $student->id)->with('payment')->get() : collect();
    }

    private function stateOf(?Enrollment $enrollment): ?array
    {
        if (! $enrollment) {
            return null;
        }

        return [
            'status' => $enrollment->isActive() ? 'active' : 'awaiting_payment',
            'payment_id' => $enrollment->payment_id,
            'amount' => $enrollment->payment?->amount,
            'completed' => $enrollment->completed_at !== null,
        ];
    }
}
