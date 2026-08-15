<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
class StudentController extends Controller {
    public function index(Request $request) { return Student::query()->when($request->string('grade')->isNotEmpty(), fn($q)=>$q->where('grade',$request->string('grade')))->withCount(['assignments','attendanceRecords','examResults','payments'])->latest()->paginate(25); }
    public function store(Request $request) { abort_unless($request->user()->isAnyRole('admin','teacher'), 403); $student = Student::create($request->validate(['name'=>'required|string|max:160','group'=>'required|string|max:32','grade'=>'required|string|max:80','phone'=>'required|string|max:32','parent_phone'=>'nullable|string|max:32','status'=>'nullable|in:excellent,average,weak'])); return response()->json($student, 201); }
    public function show(Student $student) { return $student->load(['assignments.worksheet','attendanceRecords','examResults','payments']); }
}
