<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\Worksheet;
use App\Models\WorksheetAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorksheetAssignment> */
class WorksheetAssignmentFactory extends Factory
{
    protected $model = WorksheetAssignment::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['assigned', 'in_progress', 'submitted', 'graded']);

        return [
            'worksheet_id' => Worksheet::factory(),
            'student_id' => Student::factory(),
            'status' => $status,
            'assigned_at' => now()->subDays(fake()->numberBetween(1, 10)),
            'submitted_at' => in_array($status, ['submitted', 'graded'], true) ? now()->subDays(fake()->numberBetween(0, 5)) : null,
            'score' => $status === 'graded' ? fake()->numberBetween(12, 20) : null,
            'max_score' => 20,
            'feedback' => $status === 'graded' ? 'أحسنت، راجع خطوات الحل في السؤال الثالث.' : null,
        ];
    }
}
