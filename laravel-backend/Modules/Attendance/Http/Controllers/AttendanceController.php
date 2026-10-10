<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Core\Http\Concerns\AuthorizesStaff;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Services\AbsenceSweeper;
use Modules\Groups\Services\SessionReminders;
use Modules\Core\Support\Singleflight;
use Modules\Payments\Services\StudentLedger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'confirm_outside_session' => 'nullable|boolean',
        ]);
        // A scan the phone kept while offline and is now sending (see
        // IdempotentRequests): its own time is the only record of the arrival.
        $replayed = $request->header('X-Offline-Replay') === '1';
        $result = $this->attendance->scan(
            $data['payload'],
            $request->user()->id,
            $this->deviceTime($data['scanned_at'] ?? null, $replayed),
            (bool) ($data['confirm_outside_session'] ?? false),
            $replayed,
        );
        if ($result['requires_confirmation'] ?? false) {
            return response()->json($result);
        }
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

        $time = $this->deviceTime($data['now'] ?? null);

        return ['marked_absent' => Singleflight::run('sweep', 60, fn () => app(AbsenceSweeper::class)->sweep($time), 0)];
    }

    /** Tells students and teachers a session starts soon; the scheduler does the same every five minutes. */
    public function remindSessions(Request $request, SessionReminders $reminders)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['now' => 'nullable|date']);

        $time = $this->deviceTime($data['now'] ?? null);

        return ['reminded' => Singleflight::run('session-reminders', 60, fn () => $reminders->send($time), 0)];
    }

    /** The device's wall-clock time with its UTC offset, as sent by the app. */
    private function deviceTime(?string $value, bool $replayed = false): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }
        $time = CarbonImmutable::parse($value);
        if ($replayed) {
            // A scan kept offline is trusted up to a week back. Beyond that it
            // is refused rather than quietly dated today: the person records
            // it by hand with the right day.
            if ($time->lessThan(now()->subDays(AttendanceDomainService::REPLAY_DAYS))) {
                throw ValidationException::withMessages([
                    'scanned_at' => 'مرّ وقت طويل على هذا المسح. سجّل الحضور يدوياً بالتاريخ الصحيح.',
                ]);
            }

            return $time->greaterThan(now()->addHours(36)) ? null : $time;
        }

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
