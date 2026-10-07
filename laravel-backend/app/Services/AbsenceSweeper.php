<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\ClassGroup;
use App\Models\Student;
use Carbon\CarbonInterface;

/**
 * Marks students absent once their group's session has ended without a
 * check-in, and tells their parents. Safe to run as often as needed: a
 * student who already has a record for the day is never touched again, so
 * each parent hears about an absence once.
 */
class AbsenceSweeper
{
    public function __construct(private readonly StudentNotifier $notifier)
    {
    }

    /**
     * @param  CarbonInterface|null  $localTime  wall-clock time to judge by (the
     *         calling device's, or the server's when run by the scheduler)
     * @return int  students marked absent
     */
    public function sweep(?CarbonInterface $localTime = null): int
    {
        $localTime ??= now();
        $date = $localTime->toDateString();
        $marked = 0;

        foreach (ClassGroup::query()->get() as $group) {
            if (! $group->hasEndedAt($localTime)) {
                continue;
            }
            $missing = Student::query()
                ->where('group', $group->name)
                ->whereDoesntHave('attendanceRecords', fn ($query) => $query->where('attendance_date', $date))
                ->get();

            foreach ($missing as $student) {
                AttendanceRecord::create([
                    'student_id' => $student->id,
                    'attendance_date' => $date,
                    'date_at' => $localTime,
                    'status' => 'absent',
                    'note' => 'غياب تلقائي',
                    'recorded_by' => null,
                ]);
                $this->notifier->notify(
                    [$student->id],
                    'تنبيه غياب',
                    "تغيّب {$student->name} اليوم عن حصة {$group->name} ({$group->start_time} - {$group->end_time}).",
                    'absence',
                    ['parent'],
                );
                $marked++;
            }
        }

        return $marked;
    }
}
