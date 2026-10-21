<?php

declare(strict_types=1);

namespace Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Http\Concerns\AuthorizesStaff;
use Modules\Courses\Enums\ContentStatus;
use Modules\Courses\Enums\CourseKind;
use Modules\Courses\Enums\OutlineStyle;
use Modules\Courses\Enums\Pricing;
use Modules\Courses\Enums\SequencingMode;
use Modules\Courses\Http\Concerns\ConvertsToSchoolTime;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\LessonProgress;
use Modules\Courses\Services\CourseOutline;
use Modules\Courses\Services\CoursePresenter;
use Modules\Courses\Services\CourseSlugs;

/** Running courses: create, edit, publish, name the teachers, and see who is enrolled. */
class CourseController extends Controller
{
    use AuthorizesStaff;
    use ConvertsToSchoolTime;

    public function __construct(private readonly CourseOutline $outline, private readonly CourseSlugs $slugs) {}

    public function index(Request $request)
    {
        $this->authorizeStaff($request);
        $user = $request->user();

        $query = Course::query()
            ->withCount(['lessons as published_lessons_count' => fn ($q) => $q->where('course_lessons.status', ContentStatus::Published->value)])
            ->with('instructors:id,name')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id');
        if (! $user->isAnyRole('admin')) {
            $query->whereHas('instructors', fn ($q) => $q->where('users.id', $user->id));
        }

        $page = $query->paginate(25);
        $page->getCollection()->transform(fn (Course $course) => CoursePresenter::summary($course));

        return $page;
    }

    public function store(Request $request)
    {
        $this->authorizeStaff($request);
        abort_unless($request->user()->can('create', Course::class), 403);

        $data = $this->validated($request);
        $data = $this->settlePricing($data);
        $instructorIds = $this->instructorIds($request);

        $course = Course::create($data + ['slug' => $this->slugs->uniqueFrom($data['title']), 'created_by' => $request->user()->id]);
        // A teacher who creates a course teaches it; management may name others.
        $course->instructors()->sync($this->positions($instructorIds ?? [$request->user()->id]));

        return response()->json(CoursePresenter::staff($this->loaded($course)), 201);
    }

    public function show(Request $request, Course $course)
    {
        abort_unless($request->user()->can('view', $course), 403, 'لا تملك صلاحية هذه الدورة.');

        return CoursePresenter::staff($this->loaded($course));
    }

    public function update(Request $request, Course $course)
    {
        abort_unless($request->user()->can('update', $course), 403, 'لا تملك صلاحية هذه الدورة.');

        $data = $this->validated($request, $course);
        $data = $this->settlePricing($data, $course);
        if (($data['outline_style'] ?? null) === OutlineStyle::Lessons->value && $course->subjects()->count() > 1) {
            throw ValidationException::withMessages(['outline_style' => 'لا يصلح مخطط الدروس المباشر لدورة فيها أكثر من قسم.']);
        }
        $course->update($data);

        return CoursePresenter::staff($this->loaded($course));
    }

    public function destroy(Request $request, Course $course)
    {
        abort_unless($request->user()->can('delete', $course), 403, 'لا تملك صلاحية هذه الدورة.');
        $this->outline->delete($course);

        return response()->noContent();
    }

    public function instructors(Request $request, Course $course)
    {
        abort_unless($request->user()->can('assignInstructors', $course), 403, 'تعيين المعلمين متاح للمشرف فقط.');
        $data = $request->validate([
            'instructor_ids' => ['required', 'array', 'min:1'],
            'instructor_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('role', 'teacher')],
        ]);
        $course->instructors()->sync($this->positions($data['instructor_ids']));

        return CoursePresenter::staff($this->loaded($course));
    }

    public function status(Request $request, Course $course)
    {
        abort_unless($request->user()->can('publish', $course), 403, 'لا تملك صلاحية نشر هذه الدورة.');
        $data = $request->validate(['status' => ['required', Rule::enum(ContentStatus::class)]]);
        $this->outline->changeStatus($course, ContentStatus::from($data['status']));

        return CoursePresenter::staff($this->loaded($course));
    }

    public function enrollments(Request $request, Course $course)
    {
        abort_unless($request->user()->can('view', $course), 403, 'لا تملك صلاحية هذه الدورة.');

        $lessonIds = $course->lessons()->where('course_lessons.status', ContentStatus::Published->value)->pluck('course_lessons.id');
        $done = LessonProgress::query()->whereIn('lesson_id', $lessonIds)->selectRaw('student_id, count(*) as total')->groupBy('student_id')->pluck('total', 'student_id');

        return ['data' => $course->enrollments()->with(['student:id,name', 'payment:id,status,amount'])->latest('id')->get()->map(fn ($e) => [
            'id' => $e->id,
            'student' => ['id' => $e->student->id, 'name' => $e->student->name],
            'status' => $e->isActive() ? 'active' : 'awaiting_payment',
            'payment' => $e->payment ? ['id' => $e->payment->id, 'status' => $e->payment->status, 'amount' => $e->payment->amount] : null,
            'completed_lessons' => $e->isActive() ? (int) ($done[$e->student_id] ?? 0) : 0,
            'published_lessons' => $lessonIds->count(),
            'completed_at' => $e->completed_at?->toIso8601String(),
        ])->all()];
    }

    private function loaded(Course $course): Course
    {
        return $course->fresh()->load(['subjects.lessons', 'instructors:id,name'])
            ->loadCount(['lessons as published_lessons_count' => fn ($q) => $q->where('course_lessons.status', ContentStatus::Published->value)]);
    }

    /** @param  list<int>  $ids */
    private function positions(array $ids): array
    {
        return collect($ids)->values()->mapWithKeys(fn (int $id, int $i) => [$id => ['position' => $i]])->all();
    }

    /** @return list<int>|null null when the request names no instructors, or the caller may not (teachers teach their own) */
    private function instructorIds(Request $request): ?array
    {
        if (! $request->user()->isAnyRole('admin') || ! $request->has('instructor_ids')) {
            return null;
        }

        return $request->validate([
            'instructor_ids' => ['array', 'min:1'],
            'instructor_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('role', 'teacher')],
        ])['instructor_ids'];
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        $required = $course ? 'sometimes' : 'required';

        return $this->inSchoolTime($request->validate([
            'title' => [$required, 'string', 'max:200'],
            'kind' => ['sometimes', Rule::enum(CourseKind::class)],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'goals' => ['nullable', 'array', 'max:20'],
            'goals.*' => ['string', 'max:300'],
            'target_audience' => ['nullable', 'string', 'max:500'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'visible_from' => ['nullable', 'date'],
            'visible_until' => ['nullable', 'date', 'after:visible_from'],
            'is_featured' => ['sometimes', 'boolean'],
            'season_name' => ['nullable', 'string', 'max:100'],
            'sequencing_mode' => ['sometimes', Rule::enum(SequencingMode::class)],
            'outline_style' => ['sometimes', Rule::enum(OutlineStyle::class)],
            'pricing' => ['sometimes', Rule::enum(Pricing::class)],
            'price_amount' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'discounted_price_amount' => ['nullable', 'integer', 'min:1', 'lt:price_amount'],
        ]), ['starts_at', 'ends_at', 'visible_from', 'visible_until']);
    }

    /** A paid course needs a price; a free one carries none. */
    private function settlePricing(array $data, ?Course $course = null): array
    {
        $pricing = $data['pricing'] ?? $course?->pricing->value ?? Pricing::Free->value;
        if ($pricing === Pricing::Free->value) {
            return $data + ['price_amount' => null, 'discounted_price_amount' => null];
        }

        $price = array_key_exists('price_amount', $data) ? $data['price_amount'] : $course?->price_amount;
        if ($price === null) {
            throw ValidationException::withMessages(['price_amount' => 'الدورة المدفوعة تحتاج سعراً.']);
        }
        $discount = array_key_exists('discounted_price_amount', $data) ? $data['discounted_price_amount'] : $course?->discounted_price_amount;
        if ($discount !== null && $discount >= $price) {
            throw ValidationException::withMessages(['discounted_price_amount' => 'السعر بعد الخصم يجب أن يكون أقل من السعر.']);
        }

        return $data;
    }
}
