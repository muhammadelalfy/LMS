<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesStaff;
use App\Http\Controllers\Controller;
use App\Models\ExamDepartment;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ExamSessionEvent;
use App\Models\ExamTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ExamPaperPdfService;

class ExamManagementController extends Controller
{
    use AuthorizesStaff;

    public function departments(Request $request)
    {
        $this->authorizeStaff($request);
        return ExamDepartment::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function storeDepartment(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['name' => 'required|string|max:255', 'slug' => 'required|string|max:255|alpha_dash|unique:exam_departments,slug', 'description' => 'nullable|string']);
        return response()->json(ExamDepartment::create($data), 201);
    }

    public function updateDepartment(Request $request, ExamDepartment $department)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['name' => 'sometimes|string|max:255', 'slug' => 'sometimes|string|max:255|alpha_dash|unique:exam_departments,slug,' . $department->id, 'description' => 'nullable|string', 'is_active' => 'sometimes|boolean']);
        $department->update($data);
        return $department->fresh();
    }

    public function destroyDepartment(Request $request, ExamDepartment $department)
    {
        $this->authorizeStaff($request);
        if ($department->templates()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف قسم مرتبط بقوالب امتحانات. عطّل القسم بدلاً من ذلك.'], 422);
        }
        $department->delete();
        return response()->noContent();
    }

    public function templates(Request $request)
    {
        if ($request->user()->isAnyRole('student', 'parent')) {
            return ExamTemplate::with(['department', 'questions'])->where('status', 'published')->latest()->paginate(50);
        }
        $this->authorizeStaff($request);
        return ExamTemplate::with(['department', 'questions'])->latest()->paginate(50);
    }

    public function storeTemplate(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'department_id' => 'nullable|exists:exam_departments,id', 'title' => 'required|string|max:255', 'grade' => 'nullable|string|max:255',
            'duration_minutes' => 'required|integer|min:1|max:600', 'instructions' => 'nullable|string', 'watermark_text' => 'nullable|string|max:255',
            'watermark_opacity' => 'nullable|integer|min:0|max:50', 'status' => 'nullable|in:draft,published,archived',
            'questions' => 'array', 'questions.*.type' => 'required|in:mcq,true_false,essay,math', 'questions.*.prompt_html' => 'required|string',
            'questions.*.options' => 'nullable|array', 'questions.*.correct_answer' => 'nullable|string', 'questions.*.points' => 'required|integer|min:1|max:100',
        ]);
        return DB::transaction(function () use ($data, $request) {
            $questions = $data['questions'] ?? [];
            unset($data['questions']);
            $template = ExamTemplate::create([...$data, 'created_by' => $request->user()->id]);
            foreach ($questions as $index => $question) $template->questions()->create([...$question, 'sort_order' => $index]);
            return response()->json($template->load(['department', 'questions']), 201);
        });
    }

    public function updateTemplate(Request $request, ExamTemplate $template)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['department_id' => 'nullable|exists:exam_departments,id', 'title' => 'sometimes|string|max:255', 'grade' => 'nullable|string|max:255', 'duration_minutes' => 'sometimes|integer|min:1|max:600', 'instructions' => 'nullable|string', 'watermark_text' => 'nullable|string|max:255', 'watermark_opacity' => 'nullable|integer|min:0|max:50', 'status' => 'sometimes|in:draft,published,archived']);
        $template->update($data);
        return $template->fresh(['department', 'questions']);
    }

    public function destroyTemplate(Request $request, ExamTemplate $template)
    {
        $this->authorizeStaff($request);
        $template->delete();
        return response()->noContent();
    }

    public function downloadPdf(Request $request, ExamTemplate $template, ExamPaperPdfService $pdfService)
    {
        if ($request->user()->isAnyRole('student', 'parent')) {
            abort_unless($template->status === 'published', 404);
        } else {
            $this->authorizeStaff($request);
        }

        $filename = str($template->title)->slug('-')->append('.pdf')->toString() ?: 'exam-paper.pdf';
        return response($pdfService->render($template), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function startSession(Request $request, ExamTemplate $template)
    {
        $user = $request->user();
        abort_unless($user->isAnyRole('student', 'parent'), 403);
        $account = $user->studentAccount;
        abort_unless($account, 403);
        abort_unless($template->status === 'published', 422, 'الامتحان غير منشور.');
        $session = ExamSession::firstOrCreate(['template_id' => $template->id, 'student_id' => $account->student_id], ['camera_required' => true, 'fullscreen_required' => true]);
        return $session->load(['template.questions', 'answers']);
    }

    public function event(Request $request, ExamSession $session)
    {
        $this->authorizeSessionOwner($request, $session);
        $data = $request->validate(['type' => 'required|in:camera_granted,camera_denied,fullscreen_entered,fullscreen_exited,focus_lost,focus_restored,visibility_hidden,visibility_visible,submitted,heartbeat', 'metadata' => 'nullable|array']);
        return DB::transaction(function () use ($session, $data) {
            $session->update(['last_event_at' => now(), 'focus_loss_count' => $data['type'] === 'focus_lost' || $data['type'] === 'visibility_hidden' ? $session->focus_loss_count + 1 : $session->focus_loss_count, 'status' => in_array($data['type'], ['focus_lost', 'visibility_hidden']) ? 'flagged' : ($session->status === 'ready' ? 'active' : $session->status), 'started_at' => $session->started_at ?: now()]);
            return $session->events()->create([...$data, 'occurred_at' => now()]);
        });
    }

    public function answer(Request $request, ExamSession $session)
    {
        $this->authorizeSessionOwner($request, $session);
        abort_if(in_array($session->status, ['submitted', 'expired']), 422, 'لا يمكن تعديل إجابة هذا الامتحان.');
        $data = $request->validate(['question_id' => 'required|exists:exam_questions,id', 'answer' => 'nullable|string']);
        abort_unless($session->template->questions()->whereKey($data['question_id'])->exists(), 422);
        $answer = ExamSessionAnswer::updateOrCreate(['session_id' => $session->id, 'question_id' => $data['question_id']], ['answer' => $data['answer'] ?? null, 'answered_at' => now()]);
        $session->update(['status' => $session->status === 'ready' ? 'active' : $session->status, 'started_at' => $session->started_at ?: now()]);
        return $answer;
    }

    public function submit(Request $request, ExamSession $session)
    {
        $this->authorizeSessionOwner($request, $session);
        abort_if(in_array($session->status, ['submitted', 'expired']), 422, 'تم إنهاء هذا الامتحان بالفعل.');
        $session->update(['status' => 'submitted', 'submitted_at' => now(), 'last_event_at' => now()]);
        $session->events()->create(['type' => 'submitted', 'occurred_at' => now()]);
        return $session->fresh(['template.questions', 'answers', 'events']);
    }

    private function authorizeSessionOwner(Request $request, ExamSession $session): void
    {
        $account = $request->user()->studentAccount;
        abort_unless($account && $account->student_id === $session->student_id, 403);
    }
}
