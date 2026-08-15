<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceRecord> */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        $date = now()->subDays(fake()->numberBetween(0, 14));

        return [
            'student_id' => Student::factory(),
            'date_at' => $date,
            'attendance_date' => $date->toDateString(),
            'status' => fake()->randomElement(['present', 'late', 'absent']),
            'note' => null,
            'recorded_by' => null,
        ];
    }
}
