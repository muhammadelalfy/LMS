<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AttendanceController extends Controller {
    public function index(Request $request) { $query=AttendanceRecord::with('student')->latest('date_at'); $this->scope($query,$request); return $query->paginate(50); }
    public function store(Request $request) { $this->staff($request); $data=$request->validate(['student_id'=>'required|exists:students,id','date_at'=>'required|date','status'=>'required|in:present,absent,late','note'=>'nullable|string']); return response()->json(AttendanceRecord::create([...$data,'attendance_date'=>now()->toDateString(),'recorded_by'=>$request->user()->id]),201); }
    public function scan(Request $request) { $this->staff($request); $payload = $request->validate(['payload'=>'required|string|min:32|max:96'])['payload']; $student = Student::where('qr_token', $payload)->first(); abort_unless($student, 422, 'Invalid student QR code.'); return DB::transaction(function () use ($student, $request) { $lockedStudent = Student::whereKey($student->id)->lockForUpdate()->firstOrFail(); $today = now()->toDateString(); $existing = AttendanceRecord::where('student_id', $lockedStudent->id)->where('attendance_date', $today)->first(); if ($existing) return response()->json(['already_recorded'=>true,'attendance'=>$existing->load('student')]); $attendance = AttendanceRecord::create(['student_id'=>$lockedStudent->id,'attendance_date'=>$today,'date_at'=>now(),'status'=>'present','note'=>'QR scan','recorded_by'=>$request->user()->id]); return response()->json(['already_recorded'=>false,'attendance'=>$attendance->load('student')], 201); }); }
    public function update(Request $request, AttendanceRecord $attendance) { $this->staff($request); $attendance->update([...$request->validate(['date_at'=>'sometimes|date','status'=>'sometimes|in:present,absent,late','note'=>'nullable|string']), 'attendance_date' => $attendance->attendance_date ?? now()->toDateString()]); return $attendance->fresh('student'); }
    public function destroy(Request $request, AttendanceRecord $attendance) { $this->staff($request); $attendance->delete(); return response()->noContent(); }
    private function staff(Request $request): void { abort_unless($request->user()->isAnyRole('admin','teacher'),403); }
    private function scope($query, Request $request): void { $account=$request->user()->studentAccount; if ($request->user()->isAnyRole('student','parent')) { abort_unless($account,403); $query->where('student_id',$account->student_id); } }
}
