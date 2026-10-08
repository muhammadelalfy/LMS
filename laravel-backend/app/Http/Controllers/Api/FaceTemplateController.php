<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesStaff;
use App\Http\Controllers\Controller;
use App\Models\FaceTemplate;
use App\Models\Student;
use Illuminate\Http\Request;

/**
 * Enrolled faces for face attendance, shared by every staff device. Staff
 * only; saving needs the parent's consent, confirmed by the staff member.
 */
class FaceTemplateController extends Controller
{
    use AuthorizesStaff;

    public function index(Request $request)
    {
        $this->authorizeStaff($request);

        return [
            'data' => FaceTemplate::query()
                ->with('student')
                ->get()
                ->map(fn (FaceTemplate $template) => $template->toPayload())
                ->values(),
        ];
    }

    /** Enrols the student's face, replacing any earlier one. */
    public function update(Request $request, Student $student)
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'consent' => 'accepted',
            'model' => 'required|string|max:64',
            'embeddings' => 'required|array|min:1|max:'.FaceTemplate::MAX_EMBEDDINGS,
            'embeddings.*' => 'required|string|max:'.FaceTemplate::MAX_EMBEDDING_LENGTH,
        ], [
            'consent.accepted' => 'يجب تأكيد موافقة ولي الأمر قبل حفظ بصمة الوجه.',
        ]);

        $template = FaceTemplate::query()->updateOrCreate(
            ['student_id' => $student->id],
            [
                'model' => $data['model'],
                'embeddings' => array_values($data['embeddings']),
                'consent_by' => $request->user()->id,
                'enrolled_at' => now(),
            ],
        );

        return response()->json($template->load('student')->toPayload());
    }

    public function destroy(Request $request, Student $student)
    {
        $this->authorizeStaff($request);
        FaceTemplate::query()->where('student_id', $student->id)->delete();

        return response()->noContent();
    }
}
