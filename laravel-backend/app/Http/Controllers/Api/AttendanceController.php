<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesStaff;
use App\Models\AttendanceRecord;
use App\Services\AbsenceSweeper;
use App\Services\StudentLedger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Modules\Attendance\Services\AttendanceDomainService;

class AttendanceController extends Controller
{
    use AuthorizesStaff;

    public function __construct(private readonly AttendanceDomainService $attendance)
    {
    }

    public function index(Request $request)
    {
        $query = $this->attendance->query();
        $this->scope($query, $request);

        return $query->paginate(50);
    }

    public function store(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'date_at' => 'required|date',
            'status' => 'required|in:present,absent,late',
            'note' => 'nullable|string',
        ]);

        $record = $this->attendance->create($data, $request->user()->id);

        return response()->json($record, $record->wasRecentlyCreated ? 201 : 200);
    }

    public function scan(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'payload' => 'required|string|min:32|max:96',
            'scanned_at' => 'nullable|date',
        ]);
        $result = $this->attendance->scan(
            $data['payload'],
            $request->user()->id,
            $this->deviceTime($data['scanned_at'] ?? null),
        );
        $result += app(StudentLedger::class)->outstanding($result['attendance']->student);

        return response()->json($result, $result['already_recorded'] ? 200 : 201);
    }

    /**
     * Marks absent every student whose group session has ended today without
     * a check-in, and notifies their parents. Runs on the scheduler too; the
     * app calls it so absences are recorded without anyone pressing a button.
     */
    public function sweep(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['now' => 'nullable|date']);

        return ['marked_absent' => app(AbsenceSweeper::class)->sweep($this->deviceTime($data['now'] ?? null))];
    }

    /** The device's wall-clock time with its UTC offset, as sent by the app. */
    private function deviceTime(?string $value): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }
        $time = CarbonImmutable::parse($value);

        return abs($time->diffInHours(now(), true)) > 36 ? null : $time;
    }

    public function update(Request $request, AttendanceRecord $attendance)
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'date_at' => 'sometimes|date',
            'status' => 'sometimes|in:present,absent,late',
            'note' => 'nullable|string',
        ]);

        return $this->attendance->update($attendance, $data);
    }

    public function destroy(Request $request, AttendanceRecord $attendance)
    {
        $this->authorizeStaff($request);
        $this->attendance->delete($attendance);

        return response()->noContent();
    }

    private function scope($query, Request $request): void
    {
        $account = $request->user()->studentAccount;
        if ($request->user()->isAnyRole('student', 'parent')) {
            abort_unless($account, 403);
            $query->where('student_id', $account->student_id);
        }
    }
}
