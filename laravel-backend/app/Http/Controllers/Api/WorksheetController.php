<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Worksheet;
use App\Models\WorksheetAssignment;
use Illuminate\Http\Request;
class WorksheetController extends Controller {
    public function index(Request $request) { return Worksheet::withCount(['assignments','assignments as submitted_count'=>fn($q)=>$q->where('status','submitted')])->latest()->paginate(25); }
    public function store(Request $request) { abort_unless($request->user()->isAnyRole('admin','teacher'),403); $data=$request->validate(['title'=>'required|string|max:180','subject'=>'required|string|max:80','grade'=>'required|string|max:80','instructions'=>'nullable|string','due_at'=>'nullable|date','status'=>'nullable|in:draft,published']); return response()->json(Worksheet::create([...$data,'created_by'=>$request->user()->id]),201); }
    public function show(Worksheet $worksheet) { return $worksheet->load('assignments.student'); }
    public function assign(Request $request, Worksheet $worksheet) { abort_unless($request->user()->isAnyRole('admin','teacher'),403); $data=$request->validate(['student_ids'=>'required|array|min:1','student_ids.*'=>'integer|exists:students,id']); $now=now(); $rows=collect($data['student_ids'])->map(fn($studentId)=>['worksheet_id'=>$worksheet->id,'student_id'=>$studentId,'assigned_at'=>$now,'created_at'=>$now,'updated_at'=>$now])->all(); WorksheetAssignment::upsert($rows,['worksheet_id','student_id'],['updated_at']); return $worksheet->load('assignments.student'); }
    public function submit(Request $request, WorksheetAssignment $assignment) { $user=$request->user(); $account=$user->studentAccount; abort_unless($user->isAnyRole('admin','teacher') || ($account && $account->student_id === $assignment->student_id),403); $data=$request->validate(['score'=>'nullable|integer|min:0','max_score'=>'nullable|integer|min:1','feedback'=>'nullable|string']); $assignment->update([...$data,'status'=>'submitted','submitted_at'=>now()]); return $assignment->fresh(); }
}
