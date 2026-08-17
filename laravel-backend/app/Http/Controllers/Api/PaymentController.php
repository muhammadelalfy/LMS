<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesStaff;
use App\Models\Payment;
use Illuminate\Http\Request;
class PaymentController extends Controller {
    use AuthorizesStaff;
    public function index(Request $request) { $query=Payment::with('student')->latest('due_at'); $this->scope($query,$request); return $query->paginate(50); }
    public function store(Request $request) { $this->authorizeStaff($request); $data=$request->validate(['student_id'=>'required|exists:students,id','amount'=>'required|integer|min:0','status'=>'required|in:pending,paid,overdue','due_at'=>'required|date','paid_at'=>'nullable|date','note'=>'nullable|string']); return response()->json(Payment::create([...$data,'recorded_by'=>$request->user()->id]),201); }
    public function update(Request $request, Payment $payment) { $this->authorizeStaff($request); $payment->update($request->validate(['amount'=>'sometimes|integer|min:0','status'=>'sometimes|in:pending,paid,overdue','due_at'=>'sometimes|date','paid_at'=>'nullable|date','note'=>'nullable|string'])); return $payment->fresh('student'); }
    public function destroy(Request $request, Payment $payment) { $this->authorizeStaff($request); $payment->delete(); return response()->noContent(); }
    private function scope($query, Request $request): void { $account=$request->user()->studentAccount; if ($request->user()->isAnyRole('student','parent')) { abort_unless($account,403); $query->where('student_id',$account->student_id); } }
}
