<?php

namespace Database\Factories;

use Modules\Exams\Models\ExamQuestion;
use Modules\Exams\Models\ExamSession;
use Modules\Exams\Models\ExamSessionAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamSessionAnswer> */
class ExamSessionAnswerFactory extends Factory
{
    protected $model = ExamSessionAnswer::class;

    public function definition(): array
    {
        return ['session_id' => ExamSession::factory(), 'question_id' => ExamQuestion::factory(), 'answer' => $this->faker->randomElement(['٥', '٦', '٧', '٨']), 'answered_at' => now()->subMinutes(3)];
    }

    public function unanswered(): static { return $this->state(fn () => ['answer' => null, 'answered_at' => null]); }
}
