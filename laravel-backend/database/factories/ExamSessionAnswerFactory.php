<?php

namespace Database\Factories;

use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
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
