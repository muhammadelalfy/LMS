<?php

namespace Database\Factories;

use App\Models\ExamQuestion;
use App\Models\ExamTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamQuestion> */
class ExamQuestionFactory extends Factory
{
    protected $model = ExamQuestion::class;

    public function definition(): array
    {
        return [
            'template_id' => ExamTemplate::factory(),
            'type' => 'mcq',
            'prompt_html' => '<p>إذا كان <strong>س = ٣</strong>، فما قيمة ٢س + ١؟</p>',
            'options' => ['٥', '٦', '٧', '٨'],
            'correct_answer' => '٧',
            'points' => 2,
            'sort_order' => 0,
        ];
    }

    public function trueFalse(): static { return $this->state(fn () => ['type' => 'true_false', 'options' => ['صح', 'خطأ'], 'correct_answer' => 'صح']); }
    public function essay(): static { return $this->state(fn () => ['type' => 'essay', 'options' => null, 'correct_answer' => null]); }
    public function math(): static { return $this->state(fn () => ['type' => 'math', 'options' => null, 'correct_answer' => 'س = ٤']); }
}
