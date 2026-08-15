<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'أحمد محمد علي', 'سارة محمود حسن', 'يوسف خالد إبراهيم', 'ملك أحمد السيد',
                'عمر سامح عبد الله', 'نورهان محمد سالم', 'آدم كريم فؤاد', 'جنى تامر حسين',
                'زياد أشرف مصطفى', 'ليان هشام عادل',
            ]),
            'group' => fake()->randomElement(['المجموعة الأولى', 'المجموعة الثانية', 'المجموعة الثالثة']),
            'grade' => fake()->randomElement(['الأول الإعدادي', 'الثاني الإعدادي', 'الثالث الإعدادي']),
            'phone' => fake()->unique()->numerify('010########'),
            'parent_phone' => fake()->unique()->numerify('011########'),
            'status' => fake()->randomElement(['excellent', 'average', 'weak']),
        ];
    }
}
