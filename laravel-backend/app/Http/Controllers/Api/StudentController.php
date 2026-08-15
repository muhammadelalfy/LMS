<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
class StudentController extends Controller {
    public function index(Request $request) { $query = Student::query()->when($request->string('grade')->isNotEmpty(), fn($q) => $q->where('grade', $request->string('grade'))); if ($request->user()->isAnyRole('student', 'parent')) { $account = $request->user()->studentAccount; abort_unless($account, 403); $query->whereKey($account->student_id); } return $query->withCount(['assignments','attendanceRecords','examResults','payments'])->latest()->paginate(25); }
    public function store(Request $request) { abort_unless($request->user()->isAnyRole('admin','teacher'), 403); $student = Student::create($request->validate(['name'=>'required|string|max:160','group'=>'required|string|max:32','grade'=>'required|string|max:80','phone'=>'required|string|max:32','parent_phone'=>'nullable|string|max:32','status'=>'nullable|in:excellent,average,weak'])); return response()->json($student, 201); }
    public function show(Request $request, Student $student) { if ($request->user()->isAnyRole('student', 'parent')) { $account = $request->user()->studentAccount; abort_unless($account && $account->student_id === $student->id, 403); } return $student->load(['assignments.worksheet','attendanceRecords','examResults','payments']); }
    public function qr(Request $request, Student $student) { if ($request->user()->isAnyRole('student', 'parent')) { $account = $request->user()->studentAccount; abort_unless($account && $account->student_id === $student->id, 403); } abort_unless($request->user()->isAnyRole('admin','teacher','parent','student'), 403); return ['student_id' => $student->id, 'payload' => $student->ensureQrToken(), 'generated_at' => now()->toISOString()]; }
}
