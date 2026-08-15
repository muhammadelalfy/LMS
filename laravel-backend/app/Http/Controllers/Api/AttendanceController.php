<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
class AttendanceController extends Controller {
    public function index(Request $request) { $query=AttendanceRecord::with('student')->latest('date_at'); $this->scope($query,$request); return $query->paginate(50); }
    public function store(Request $request) { $this->staff($request); $data=$request->validate(['student_id'=>'required|exists:students,id','date_at'=>'required|date','status'=>'required|in:present,absent,late','note'=>'nullable|string']); return response()->json(AttendanceRecord::create([...$data,'recorded_by'=>$request->user()->id]),201); }
    public function update(Request $request, AttendanceRecord $attendance) { $this->staff($request); $attendance->update($request->validate(['date_at'=>'sometimes|date','status'=>'sometimes|in:present,absent,late','note'=>'nullable|string'])); return $attendance->fresh('student'); }
    public function destroy(Request $request, AttendanceRecord $attendance) { $this->staff($request); $attendance->delete(); return response()->noContent(); }
    private function staff(Request $request): void { abort_unless($request->user()->isAnyRole('admin','teacher'),403); }
    private function scope($query, Request $request): void { $account=$request->user()->studentAccount; if ($request->user()->isAnyRole('student','parent')) { abort_unless($account,403); $query->where('student_id',$account->student_id); } }
}
