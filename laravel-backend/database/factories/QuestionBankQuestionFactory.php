<?php

namespace Database\Factories;

use App\Models\QuestionBankQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuestionBankQuestion> */
class QuestionBankQuestionFactory extends Factory
{
    protected $model = QuestionBankQuestion::class;

    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'department_id' => null,
            'type' => 'mcq',
            'title' => 'سؤال جبر أساسي',
            'grade' => 'الأول الإعدادي',
            'prompt_html' => '<p>إذا كان <strong>س = ٣</strong>، فما قيمة ٢س + ١؟</p>',
            'options' => ['٥', '٦', '٧', '٨'],
            'correct_answer' => '٧',
            'points' => 2,
            'tags' => 'جبر،معادلات',
            'is_active' => true,
        ];
    }

    public function geometry(): static { return $this->state(fn () => ['type' => 'geometry', 'title' => 'مساحة مستطيل', 'options' => ['shape' => 'rectangle', 'dimensions' => ['width' => '٦', 'height' => '٤']], 'correct_answer' => '٢٤']); }
    public function math(): static { return $this->state(fn () => ['type' => 'math', 'title' => 'تبسيط تعبير', 'options' => ['notation' => 'س² + ٢س + ١'], 'correct_answer' => '(س + ١)²']); }
    public function essay(): static { return $this->state(fn () => ['type' => 'essay', 'options' => null, 'correct_answer' => null]); }
}
