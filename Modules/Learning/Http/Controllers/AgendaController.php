<?php

namespace Modules\Learning\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Concerns\AuthorizesStaff;
use Modules\Learning\Models\AgendaItem;

/**
 * The teacher's agenda: notes and tasks to do with a group on a given day.
 * Staff only. A teacher sees and changes their own; an administrator sees all.
 */
class AgendaController extends Controller
{
    use AuthorizesStaff;

    public function index(Request $request)
    {
        $this->authorizeStaff($request);

        return $this->visibleTo($request)
            ->whereDate('due_on', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('due_on')
            ->orderByDesc('id')
            ->paginate(100);
    }

    public function store(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $this->validated($request);

        return response()->json(AgendaItem::create([...$data, 'created_by' => $request->user()->id]), 201);
    }

    public function update(Request $request, int $item)
    {
        $this->authorizeStaff($request);
        $agenda = $this->visibleTo($request)->findOrFail($item);
        $data = $this->validated($request, partial: true);
        if (array_key_exists('done', $data)) {
            $data['done_at'] = $data['done'] ? ($agenda->done_at ?? now()) : null;
            unset($data['done']);
        }
        $agenda->update($data);

        return $agenda->fresh();
    }

    public function destroy(Request $request, int $item)
    {
        $this->authorizeStaff($request);
        $this->visibleTo($request)->findOrFail($item)->delete();

        return response()->noContent();
    }

    /** Teachers get their own items; administrators every item. */
    private function visibleTo(Request $request)
    {
        $query = AgendaItem::query();
        if (! $request->user()->isAnyRole('admin')) {
            $query->where('created_by', $request->user()->id);
        }

        return $query;
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
            'title.required' => 'اكتب الملاحظة.',
            'due_on.required' => 'حدد اليوم.',
        ]);
    }
}
