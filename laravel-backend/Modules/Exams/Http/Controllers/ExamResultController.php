<?php

namespace Modules\Exams\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Concerns\AuthorizesStaff;
use Modules\Exams\Models\ExamResult;
use Modules\Groups\Models\ClassGroup;
use Modules\Notifications\Services\StudentNotifier;

class ExamResultController extends Controller
{
    use AuthorizesStaff;

    public function index(Request $request)
    {
        $query = ExamResult::with('student')->latest('taken_at');
        $this->scope($query, $request);

        return $query->paginate(50);
    }

    public function store(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['student_id' => 'required|exists:students,id', 'title' => 'required|string|max:180', 'score' => 'required|integer|min:0', 'max_score' => 'required|integer|min:1', 'taken_at' => 'required|date']);
        abort_unless($request->user()->can('create', [ExamResult::class, $data]), 403, 'لا تملك صلاحية هذا الطالب.');
        $result = ExamResult::create([...$data, 'recorded_by' => $request->user()->id]);
        app(StudentNotifier::class)->notify([$result->student_id], 'نتيجة اختبار جديدة', "نتيجة «{$result->title}»: {$result->score} من {$result->max_score}.", 'exam_result');

        return response()->json($result->load('student'), 201);
    }

    public function update(Request $request, ExamResult $exam)
    {
        $this->authorizeStaff($request);
        abort_unless($request->user()->can('update', $exam), 403, 'لا تملك صلاحية هذا الطالب.');
        $exam->update($request->validate(['title' => 'sometimes|string|max:180', 'score' => 'sometimes|integer|min:0', 'max_score' => 'sometimes|integer|min:1', 'taken_at' => 'sometimes|date']));

        return $exam->fresh('student');
    }

    public function destroy(Request $request, ExamResult $exam)
    {
        $this->authorizeStaff($request);
        abort_unless($request->user()->can('delete', $exam), 403, 'لا تملك صلاحية هذا الطالب.');
        $exam->delete();

        return response()->noContent();
    }

    private function scope($query, Request $request): void
    {
        $user = $request->user();
        $account = $user->studentAccount;
        if ($user->isAnyRole('student', 'parent')) {
            abort_unless($account, 403);
            $query->where('student_id', $account->student_id);

            return;
        }
        if ($user->isAnyRole('teacher')) {
            $excluded = ClassGroup::namesClaimedByOthers($user);
            $query->whereHas('student', fn ($q) => $q->whereNotIn('group', $excluded));
        }
    }
}
