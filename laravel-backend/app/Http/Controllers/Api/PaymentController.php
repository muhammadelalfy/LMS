<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
class PaymentController extends Controller {
    public function index(Request $request) { $query=Payment::with('student')->latest('due_at'); $this->scope($query,$request); return $query->paginate(50); }
    public function store(Request $request) { $this->staff($request); $data=$request->validate(['student_id'=>'required|exists:students,id','amount'=>'required|integer|min:0','status'=>'required|in:pending,paid,overdue','due_at'=>'required|date','paid_at'=>'nullable|date','note'=>'nullable|string']); return response()->json(Payment::create([...$data,'recorded_by'=>$request->user()->id]),201); }
    public function update(Request $request, Payment $payment) { $this->staff($request); $payment->update($request->validate(['amount'=>'sometimes|integer|min:0','status'=>'sometimes|in:pending,paid,overdue','due_at'=>'sometimes|date','paid_at'=>'nullable|date','note'=>'nullable|string'])); return $payment->fresh('student'); }
    public function destroy(Request $request, Payment $payment) { $this->staff($request); $payment->delete(); return response()->noContent(); }
    private function staff(Request $request): void { abort_unless($request->user()->isAnyRole('admin','teacher'),403); }
    private function scope($query, Request $request): void { $account=$request->user()->studentAccount; if ($request->user()->isAnyRole('student','parent')) { abort_unless($account,403); $query->where('student_id',$account->student_id); } }
}
