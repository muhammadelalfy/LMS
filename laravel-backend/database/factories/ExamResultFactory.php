<?php

namespace Database\Factories;

use App\Models\ExamResult;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamResult> */
class ExamResultFactory extends Factory
{
    protected $model = ExamResult::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'title' => fake()->randomElement(['اختبار الوحدة الأولى', 'اختبار منتصف الفصل', 'اختبار الجبر', 'اختبار الهندسة']),
            'score' => fake()->numberBetween(10, 20),
            'max_score' => 20,
            'taken_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'recorded_by' => null,
        ];
    }
}
