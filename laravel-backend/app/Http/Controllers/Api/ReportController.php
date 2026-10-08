<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\ExamResult;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** How many days the attendance trend covers, ending today. */
    public const TREND_DAYS = 7;

    /**
     * School-wide figures for staff: totals, a day-by-day attendance trend
     * and a row per group, so the app can draw its statistics from one call.
     */
    public function summary(Request $request)
    {
        abort_unless($request->user()->isAnyRole('admin', 'teacher'), 403);

        $attendance = AttendanceRecord::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $exam = ExamResult::selectRaw('sum(score) as score, sum(max_score) as max_score')->first();
        $payments = Payment::selectRaw('status, sum(amount) as amount, count(*) as total')->groupBy('status')->get();

        return [
            'students' => Student::count(),
            'attendance' => $attendance,
            'exams' => ['score' => (int) ($exam->score ?? 0), 'max_score' => (int) ($exam->max_score ?? 0)],
            'payments' => $payments,
            'attendance_daily' => $this->attendanceDaily(),
            'groups' => $this->groups(),
        ];
    }

    /** One entry per day of the last [TREND_DAYS], oldest first, zero-filled. */
    private function attendanceDaily(): array
    {
        $days = collect(range(self::TREND_DAYS - 1, 0))->map(fn (int $ago) => now()->subDays($ago)->toDateString());
        $counts = AttendanceRecord::query()
            ->selectRaw('attendance_date, status, count(*) as total')
            ->whereBetween('attendance_date', [$days->first(), $days->last()])
            ->groupBy('attendance_date', 'status')
            ->get()
            ->groupBy(fn (AttendanceRecord $row) => substr((string) $row->attendance_date, 0, 10));

        return $days->map(function (string $day) use ($counts): array {
            $byStatus = ($counts[$day] ?? collect())->pluck('total', 'status');

            return [
                'date' => $day,
                'present' => (int) ($byStatus['present'] ?? 0),
                'late' => (int) ($byStatus['late'] ?? 0),
                'absent' => (int) ($byStatus['absent'] ?? 0),
            ];
        })->values()->all();
    }

    /** Students, attendance and exam totals for each group. */
    private function groups(): array
    {
        $groupOf = Student::query()->pluck('group', 'id');
        $attendance = AttendanceRecord::query()
            ->selectRaw('student_id, status, count(*) as total')->groupBy('student_id', 'status')->get();
        $exams = ExamResult::query()
            ->selectRaw('student_id, sum(score) as score, sum(max_score) as max_score')->groupBy('student_id')->get();

        $rows = $groupOf->countBy()->map(fn (int $students, string $group) => [
            'group' => $group, 'students' => $students,
            'present' => 0, 'late' => 0, 'absent' => 0, 'exam_score' => 0, 'exam_max_score' => 0,
        ]);
        foreach ($attendance as $row) {
            $group = $groupOf[$row->student_id] ?? null;
            if ($group !== null && isset($rows[$group][$row->status])) {
                $rows[$group] = [...$rows[$group], $row->status => $rows[$group][$row->status] + (int) $row->total];
            }
        }
        foreach ($exams as $row) {
            $group = $groupOf[$row->student_id] ?? null;
            if ($group !== null) {
                $rows[$group] = [
                    ...$rows[$group],
                    'exam_score' => $rows[$group]['exam_score'] + (int) $row->score,
                    'exam_max_score' => $rows[$group]['exam_max_score'] + (int) $row->max_score,
                ];
            }
        }

        return $rows->sortKeys()->values()->all();
    }
}
