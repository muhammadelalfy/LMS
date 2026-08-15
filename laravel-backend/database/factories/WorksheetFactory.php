<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Worksheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Worksheet> */
class WorksheetFactory extends Factory
{
    protected $model = Worksheet::class;

    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['مراجعة المعادلات الخطية', 'تدريب على النسب والتناسب', 'اختبار الدوال التربيعية', 'ورقة الهندسة والمثلثات']),
            'subject' => 'الرياضيات',
            'grade' => fake()->randomElement(['الأول الإعدادي', 'الثاني الإعدادي', 'الثالث الإعدادي']),
            'instructions' => 'حل الأسئلة بخطوات واضحة، ثم راجع إجاباتك قبل التسليم.',
            'due_at' => now()->addDays(fake()->numberBetween(3, 14)),
            'status' => fake()->randomElement(['draft', 'published']),
            'created_by' => User::factory()->state(['role' => 'teacher']),
        ];
    }
}
