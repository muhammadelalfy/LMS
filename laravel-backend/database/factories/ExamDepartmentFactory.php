<?php

namespace Database\Factories;

use App\Models\ExamDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamDepartment> */
class ExamDepartmentFactory extends Factory
{
    protected $model = ExamDepartment::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement(['الجبر', 'الهندسة', 'التفاضل والتكامل', 'الإحصاء']);
        return ['name' => $name, 'slug' => $this->faker->unique()->slug(), 'description' => 'قسم متخصص في '.$name, 'is_active' => true];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
