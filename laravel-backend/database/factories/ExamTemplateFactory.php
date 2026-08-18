<?php

namespace Database\Factories;

use App\Models\ExamDepartment;
use App\Models\ExamTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamTemplate> */
class ExamTemplateFactory extends Factory
{
    protected $model = ExamTemplate::class;

    public function definition(): array
    {
        return [
            'department_id' => ExamDepartment::factory(),
            'created_by' => User::factory(),
            'title' => $this->faker->randomElement(['اختبار الوحدة الأولى', 'مراجعة منتصف الفصل', 'اختبار المهارات الأساسية']),
            'grade' => $this->faker->randomElement(['الأول الإعدادي', 'الثاني الإعدادي', 'الثالث الإعدادي']),
            'duration_minutes' => $this->faker->randomElement([30, 45, 60]),
            'instructions' => 'اقرأ السؤال جيداً، واكتب خطوات الحل بوضوح.',
            'watermark_text' => 'الامتياز في الرياضيات',
            'watermark_opacity' => 12,
            'status' => 'draft',
        ];
    }

    public function published(): static { return $this->state(fn () => ['status' => 'published']); }
    public function archived(): static { return $this->state(fn () => ['status' => 'archived']); }
}
