<?php

namespace Modules\Learning\Http\Controllers;

use Modules\Core\Http\Concerns\AuthorizesStaff;
use App\Http\Controllers\Controller;
use Modules\Learning\Models\Duty;
use Modules\Learning\Services\DutyReminders;
use Modules\Core\Support\Singleflight;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Daily duties per group. Staff write them (typed or dictated in the app),
 * tick them done by hand and delete them; students and parents see only
 * their own group's.
 */
class DutyController extends Controller
{
    use AuthorizesStaff;

    public function index(Request $request)
    {
        $query = Duty::query()
            ->whereDate('due_on', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('due_on')
            ->orderByDesc('id');

        if ($request->user()->isAnyRole('student', 'parent')) {
            $student = $request->user()->studentAccount?->student;
            abort_unless($student, 403);
            $query->where('group', $student->group);
        }

        return $query->paginate(100);
    }

    public function store(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $this->validated($request);

        return response()->json(Duty::create([...$data, 'created_by' => $request->user()->id]), 201);
    }

    public function update(Request $request, Duty $duty)
    {
        $this->authorizeStaff($request);
        $data = $this->validated($request, partial: true);
        if (array_key_exists('done', $data)) {
            $data['done_at'] = $data['done'] ? ($duty->done_at ?? now()) : null;
            unset($data['done']);
        }
        $duty->update($data);

        return $duty->fresh();
    }

    public function destroy(Request $request, Duty $duty)
    {
        $this->authorizeStaff($request);
        $duty->delete();

        return response()->noContent();
    }

    /** Sends the day's reminders now; the scheduler does the same every five minutes. */
    public function remind(Request $request, DutyReminders $reminders)
    {
        $this->authorizeStaff($request);
        $value = $request->validate(['now' => 'nullable|date'])['now'] ?? null;
        $time = $value === null ? null : CarbonImmutable::parse($value);
        if ($time !== null && abs($time->diffInHours(now(), true)) > 36) {
            $time = null;
        }

        return ['reminded' => Singleflight::run('duty-reminders', 60, fn () => $reminders->send($time), 0)];
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'group' => [$required, 'string', 'max:32'],
            'title' => [$required, 'string', 'max:160'],
            'details' => ['nullable', 'string', 'max:2000'],
            'due_on' => [$required, 'date_format:Y-m-d'],
            'done' => ['sometimes', 'boolean'],
        ], [
            'group.required' => 'اختر المجموعة.',
            'title.required' => 'اكتب الواجب.',
            'due_on.required' => 'حدد يوم الحصة.',
        ]);
    }
}
