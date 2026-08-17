<?php

namespace Modules\Attendance\Services;

use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
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

    public function create(array $attributes, int $recordedBy): AttendanceRecord
    {
        return AttendanceRecord::create([
            ...$attributes,
            'attendance_date' => now()->toDateString(),
            'recorded_by' => $recordedBy,
        ]);
    }

    public function scan(string $payload, int $recordedBy): array
    {
        $student = Student::where('qr_token', $payload)->first();
        if (!$student) {
            throw ValidationException::withMessages(['payload' => 'Invalid student QR code.']);
        }

        return DB::transaction(function () use ($student, $recordedBy): array {
            $lockedStudent = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $today = now()->toDateString();
            $existing = AttendanceRecord::where('student_id', $lockedStudent->id)
                ->where('attendance_date', $today)
                ->first();

            if ($existing) {
                return ['already_recorded' => true, 'attendance' => $existing->load('student')];
            }

            $attendance = AttendanceRecord::create([
                'student_id' => $lockedStudent->id,
                'attendance_date' => $today,
                'date_at' => now(),
                'status' => 'present',
                'note' => 'QR scan',
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
