<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesStaff;
use App\Http\Controllers\Controller;
use App\Models\QuestionBankQuestion;
use Illuminate\Http\Request;

class QuestionBankController extends Controller
{
    use AuthorizesStaff;

    public function index(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['search' => 'nullable|string|max:120', 'type' => 'nullable|in:mcq,true_false,essay,math,geometry', 'grade' => 'nullable|string|max:255', 'active' => 'nullable|boolean']);
        $query = QuestionBankQuestion::with('department')->latest();
        if (!empty($data['search'])) $query->where(fn ($builder) => $builder->where('title', 'like', '%' . $data['search'] . '%')->orWhere('prompt_html', 'like', '%' . $data['search'] . '%')->orWhere('tags', 'like', '%' . $data['search'] . '%'));
        if (!empty($data['type'])) $query->where('type', $data['type']);
        if (!empty($data['grade'])) $query->where('grade', $data['grade']);
        if (array_key_exists('active', $data)) $query->where('is_active', $data['active']);
        return $query->paginate(50);
    }

    public function store(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $this->validated($request);
        return response()->json(QuestionBankQuestion::create([...$data, 'created_by' => $request->user()->id])->load('department'), 201);
    }

    public function show(Request $request, QuestionBankQuestion $questionBankQuestion)
    {
        $this->authorizeStaff($request);
        return $questionBankQuestion->load('department');
    }

    public function update(Request $request, QuestionBankQuestion $questionBankQuestion)
    {
        $this->authorizeStaff($request);
        $questionBankQuestion->update($this->validated($request, true));
        return $questionBankQuestion->fresh('department');
    }

    public function destroy(Request $request, QuestionBankQuestion $questionBankQuestion)
    {
        $this->authorizeStaff($request);
        $questionBankQuestion->delete();
        return response()->noContent();
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes|' : '';
        return $request->validate([
            'department_id' => $rule . 'nullable|exists:exam_departments,id',
            'type' => $rule . 'required|in:mcq,true_false,essay,math,geometry',
            'title' => $rule . 'nullable|string|max:255',
            'grade' => $rule . 'nullable|string|max:255',
            'prompt_html' => $rule . 'required|string',
            'options' => $rule . 'nullable|array',
            'correct_answer' => $rule . 'nullable|string',
            'points' => $rule . 'required|integer|min:1|max:100',
            'tags' => $rule . 'nullable|string|max:500',
            'is_active' => $rule . 'boolean',
        ]);
    }
}
