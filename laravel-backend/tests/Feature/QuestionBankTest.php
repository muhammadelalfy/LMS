<?php

namespace Tests\Feature;

use App\Models\QuestionBankQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_crud_and_search_math_and_geometry_bank_questions(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $create = $this->actingAs($teacher, 'sanctum')->postJson('/api/question-bank', [
            'title' => 'سؤال هندسي',
            'type' => 'geometry',
            'grade' => 'الأول الإعدادي',
            'prompt_html' => '<p>احسب المساحة.</p>',
            'options' => ['shape' => 'rectangle', 'dimensions' => ['width' => '٦', 'height' => '٤']],
            'correct_answer' => '٢٤',
            'points' => 4,
            'tags' => 'هندسة',
        ]);
        $create->assertCreated()->assertJsonPath('type', 'geometry');
        $question = QuestionBankQuestion::firstOrFail();

        $this->actingAs($teacher, 'sanctum')->postJson('/api/question-bank', [
            'title' => 'سؤال رياضي', 'type' => 'math', 'prompt_html' => '<p>بسّط.</p>', 'options' => ['notation' => 'س^2 + 1'], 'points' => 2,
        ])->assertCreated();
        $this->actingAs($teacher, 'sanctum')->getJson('/api/question-bank?search=هندسي')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($teacher, 'sanctum')->putJson("/api/question-bank/{$question->id}", ['title' => 'سؤال هندسي معدل', 'points' => 5])->assertOk()->assertJsonPath('title', 'سؤال هندسي معدل');
        $this->actingAs($teacher, 'sanctum')->deleteJson('/api/question-bank/'.$question->id)->assertNoContent();
        $this->assertDatabaseMissing('question_bank_questions', ['id' => $question->id]);
    }

    public function test_students_cannot_access_question_bank(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student, 'sanctum')->getJson('/api/question-bank')->assertForbidden();
        $this->actingAs($student, 'sanctum')->postJson('/api/question-bank', ['type' => 'math', 'prompt_html' => '<p>لا</p>', 'points' => 1])->assertForbidden();
    }
}
