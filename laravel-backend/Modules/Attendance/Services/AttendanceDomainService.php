<?php

namespace Modules\Attendance\Services;

use App\Models\AttendanceRecord;
use App\Models\ClassGroup;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceDomainService
{
    public function query(): Builder
    {
        return AttendanceRecord::query()->with('student')->latest('date_at');
    }

    public function forStudent(int $studentId): Builder
    {
        return $this->query()->where('student_id', $studentId);
    }

    /**
     * Records manual attendance. A student has one record per calendar day, so
     * recording the same day again corrects the existing record.
     */
    public function create(array $attributes, int $recordedBy): AttendanceRecord
    {
        $date = Carbon::parse($attributes['date_at'])->toDateString();

        $record = AttendanceRecord::updateOrCreate(
            ['student_id' => $attributes['student_id'], 'attendance_date' => $date],
            [...$attributes, 'recorded_by' => $recordedBy],
        );

        return $record->load('student');
    }

    /**
     * Records today's attendance for a QR card. [$localTime] is the scanning
     * device's wall-clock time; it decides the day and, against the student's
     * group timetable, whether the arrival is on time or late.
     *
     * A student arriving outside their group's time is not recorded: the
     * result asks for confirmation ('requires_confirmation') until the desk
     * repeats the scan with [$confirmedOutsideSession].
     */
    public function scan(
        string $payload,
        int $recordedBy,
        ?CarbonInterface $localTime = null,
        bool $confirmedOutsideSession = false,
    ): array
    {
        $student = Student::where('qr_token', $payload)->first();
        if (!$student) {
            throw ValidationException::withMessages(['payload' => 'رمز QR غير صالح لهذا الطالب.']);
        }
        // A wildly wrong device clock must not move attendance to another day.
        if ($localTime === null || abs($localTime->diffInHours(now(), true)) > 36) {
            $localTime = now();
        }
        $group = ClassGroup::query()->where('name', $student->group)->first();
        $outsideSession = $group !== null && ! $group->acceptsArrivalAt($localTime);
        // Late-vs-on-time is about the session, so an off-schedule arrival is just present.
        $status = $outsideSession ? 'present' : ($group?->statusAt($localTime) ?? 'present');

        return DB::transaction(function () use ($student, $recordedBy, $localTime, $status, $group, $outsideSession, $confirmedOutsideSession): array {
            $lockedStudent = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $today = $localTime->toDateString();
            $existing = AttendanceRecord::where('student_id', $lockedStudent->id)
                ->where('attendance_date', $today)
                ->first();

            if ($existing) {
                return ['already_recorded' => true, 'attendance' => $existing->load('student')];
            }

            if ($outsideSession && ! $confirmedOutsideSession) {
                return [
                    'requires_confirmation' => true,
                    'reason' => 'outside_session',
                    'student' => ['id' => $lockedStudent->id, 'name' => $lockedStudent->name, 'group' => $lockedStudent->group],
                    'group' => $group->only(['name', 'start_time', 'end_time', 'days']),
                ];
            }

            $attendance = AttendanceRecord::create([
                'student_id' => $lockedStudent->id,
                'attendance_date' => $today,
                'date_at' => $localTime,
                'status' => $status,
                'note' => $outsideSession ? 'حضور خارج موعد المجموعة' : 'QR scan',
                'recorded_by' => $recordedBy,
            ]);

            return ['already_recorded' => false, 'attendance' => $attendance->load('student')];
        });
    }

    public function update(AttendanceRecord $attendance, array $attributes): AttendanceRecord
    {
        $attendance->update([
            ...$attributes,
            'attendance_date' => $attendance->attendance_date ?? now()->toDateString(),
        ]);

        return $attendance->fresh('student');
    }

    public function delete(AttendanceRecord $attendance): void
    {
        $attendance->delete();
    }
}
