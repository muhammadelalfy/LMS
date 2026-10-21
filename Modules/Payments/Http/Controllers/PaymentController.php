<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Concerns\AuthorizesStaff;
use Modules\Groups\Models\ClassGroup;
use Modules\Notifications\Services\StudentNotifier;
use Modules\Payments\Models\Payment;

class PaymentController extends Controller
{
    use AuthorizesStaff;

    public function index(Request $request)
    {
        $query = Payment::with('student')->latest('due_at');
        $this->scope($query, $request);

        return $query->paginate(50);
    }

    public function store(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['student_id' => 'required|exists:students,id', 'amount' => 'required|integer|min:0', 'status' => 'required|in:pending,paid,overdue', 'due_at' => 'required|date', 'paid_at' => 'nullable|date', 'note' => 'nullable|string']);
        abort_unless($request->user()->can('create', [Payment::class, $data]), 403, 'لا تملك صلاحية هذا الطالب.');
        $payment = Payment::create([...$data, 'recorded_by' => $request->user()->id]);
        app(StudentNotifier::class)->notify([$payment->student_id], 'تحديث مالي', "تم تسجيل دفعة بقيمة {$payment->amount}.", 'payment');

        return response()->json($payment->load('student'), 201);
    }

    public function update(Request $request, Payment $payment)
    {
        $this->authorizeStaff($request);
        abort_unless($request->user()->can('update', $payment), 403, 'لا تملك صلاحية هذا الطالب.');
        $payment->update($request->validate(['amount' => 'sometimes|integer|min:0', 'status' => 'sometimes|in:pending,paid,overdue', 'due_at' => 'sometimes|date', 'paid_at' => 'nullable|date', 'note' => 'nullable|string']));

        return $payment->fresh('student');
    }

    public function destroy(Request $request, Payment $payment)
    {
        $this->authorizeStaff($request);
        abort_unless($request->user()->can('delete', $payment), 403, 'حذف الدفعة متاح للمشرف فقط.');
        $payment->delete();

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
