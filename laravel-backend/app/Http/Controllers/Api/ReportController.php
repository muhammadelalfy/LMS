<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\ExamResult;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Http\Request;
class ReportController extends Controller {
    public function summary(Request $request) { abort_unless($request->user()->isAnyRole('admin','teacher'),403); $attendance=AttendanceRecord::selectRaw("status, count(*) as total")->groupBy('status')->pluck('total','status'); $exam=ExamResult::selectRaw('sum(score) as score, sum(max_score) as max_score')->first(); $payments=Payment::selectRaw('status, sum(amount) as amount, count(*) as total')->groupBy('status')->get(); return ['students'=>Student::count(),'attendance'=>$attendance,'exams'=>['score'=>(int)($exam->score ?? 0),'max_score'=>(int)($exam->max_score ?? 0)],'payments'=>$payments]; }
}
