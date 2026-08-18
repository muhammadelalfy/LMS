<?php

namespace Tests\Feature;

use App\Models\ExamDepartment;
use App\Models\ExamTemplate;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_rich_exam_template_and_student_can_start_a_monitored_session(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = Student::factory()->create();
        $studentUser = User::factory()->create(['role' => 'student']);
        StudentAccount::create(['user_id' => $studentUser->id, 'student_id' => $student->id, 'relationship' => 'student']);
        $department = ExamDepartment::create(['name' => 'رياضيات', 'slug' => 'mathematics']);

        $response = $this->actingAs($teacher, 'sanctum')->postJson('/api/exam-templates', [
            'department_id' => $department->id,
            'title' => 'اختبار الجبر',
            'grade' => 'الأول الإعدادي',
            'duration_minutes' => 45,
            'instructions' => 'أجب بهدوء.',
            'watermark_text' => 'الامتياز في الرياضيات',
            'status' => 'published',
            'questions' => [[
                'type' => 'mcq', 'prompt_html' => '<p>كم يساوي ٢ + ٢؟</p>', 'options' => ['٣', '٤'], 'correct_answer' => '٤', 'points' => 2,
            ]],
        ]);

        $response->assertCreated()->assertJsonPath('title', 'اختبار الجبر');
        $template = ExamTemplate::firstOrFail();

        $this->actingAs($studentUser, 'sanctum')->postJson("/api/exam-templates/{$template->id}/start")
            ->assertCreated()->assertJsonFragment(['camera_required' => true]);
        $this->assertDatabaseHas('exam_sessions', ['template_id' => $template->id, 'student_id' => $student->id, 'status' => 'ready']);
    }

    public function test_student_focus_loss_is_recorded_and_flags_session(): void
    {
        $student = Student::factory()->create();
        $studentUser = User::factory()->create(['role' => 'student']);
        StudentAccount::create(['user_id' => $studentUser->id, 'student_id' => $student->id, 'relationship' => 'student']);
        $template = ExamTemplate::create(['created_by' => User::factory()->create(['role' => 'teacher'])->id, 'title' => 'هندسة', 'duration_minutes' => 30, 'status' => 'published']);
        $session = $template->sessions()->create(['student_id' => $student->id]);

        $this->actingAs($studentUser, 'sanctum')->postJson("/api/exam-sessions/{$session->id}/events", ['type' => 'focus_lost'])
            ->assertCreated()->assertJsonPath('type', 'focus_lost');

        $this->assertDatabaseHas('exam_sessions', ['id' => $session->id, 'status' => 'flagged', 'focus_loss_count' => 1]);
        $this->assertDatabaseHas('exam_session_events', ['session_id' => $session->id, 'type' => 'focus_lost']);
    }
}
