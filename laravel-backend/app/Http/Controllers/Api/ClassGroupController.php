<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesStaff;
use App\Http\Controllers\Controller;
use App\Models\ClassGroup;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Group timetables that decide on-time, late and absent automatically. */
class ClassGroupController extends Controller
{
    use AuthorizesStaff;

    public function index(Request $request)
    {
        $this->authorizeStaff($request);
        $counts = Student::query()->select('group')->selectRaw('count(*) as total')->groupBy('group')->pluck('total', 'group');

        return ['data' => ClassGroup::query()->orderBy('start_time')->get()
            ->map(fn (ClassGroup $group) => [...$group->toArray(), 'students_count' => (int) ($counts[$group->name] ?? 0)])];
    }

    public function store(Request $request)
    {
        $this->authorizeStaff($request);

        return response()->json(ClassGroup::create($this->validated($request)), 201);
    }

    public function update(Request $request, ClassGroup $group)
    {
        $this->authorizeStaff($request);
        $group->update($this->validated($request, $group));

        return $group->fresh();
    }

    public function destroy(Request $request, ClassGroup $group)
    {
        $this->authorizeStaff($request);
        $group->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?ClassGroup $group = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:32', Rule::unique('class_groups', 'name')->ignore($group?->id)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7'],
            'late_after_minutes' => ['nullable', 'integer', 'between:0,180'],
        ], [
            'name.unique' => 'توجد مجموعة بهذا الاسم.',
            'end_time.after' => 'يجب أن يكون وقت النهاية بعد وقت البداية.',
            'days.min' => 'اختر يوماً واحداً على الأقل.',
        ]);
        $data['days'] = array_values(array_unique(array_map('intval', $data['days'])));
        $data['late_after_minutes'] ??= 10;

        return $data;
    }
}
