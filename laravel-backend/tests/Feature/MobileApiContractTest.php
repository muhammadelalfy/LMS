<?php

namespace Tests\Feature;

use App\Models\ExamTemplate;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\User;
use App\Models\Worksheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_sign_in_through_the_teacher_portal_only(): void
    {
        User::factory()->create(['email' => 'teacher@example.com', 'role' => 'teacher', 'password' => Hash::make('Secret123!')]);
        User::factory()->create(['email' => 'parent@example.com', 'role' => 'parent', 'password' => Hash::make('Secret123!')]);

        $this->postJson('/api/auth/teacher/login', ['email' => 'teacher@example.com', 'password' => 'Secret123!'])
            ->assertOk()
            ->assertJsonPath('user.role', 'teacher')
            ->assertJsonPath('login_type', 'teacher');

        $this->postJson('/api/auth/teacher/login', ['email' => 'parent@example.com', 'password' => 'Secret123!'])
            ->assertStatus(422);
    }

    public function test_linked_accounts_receive_and_can_read_school_notifications(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = Student::factory()->create();
        $studentUser = User::factory()->create(['role' => 'student']);
        $parentUser = User::factory()->create(['role' => 'parent']);
        StudentAccount::create(['user_id' => $studentUser->id, 'student_id' => $student->id, 'relationship' => 'student']);
        StudentAccount::create(['user_id' => $parentUser->id, 'student_id' => $student->id, 'relationship' => 'parent']);
        $worksheet = Worksheet::create(['title' => 'شيت القراءة', 'subject' => 'اللغة العربية', 'grade' => 'الصف الأول', 'status' => 'published', 'created_by' => $teacher->id]);

        Sanctum::actingAs($teacher);
        $this->postJson("/api/worksheets/{$worksheet->id}/assign", ['student_ids' => [$student->id]])->assertOk();
        $this->postJson('/api/exams', ['student_id' => $student->id, 'title' => 'اختبار الشهر', 'score' => 17, 'max_score' => 20, 'taken_at' => now()->toDateString()])
            ->assertCreated()
            ->assertJsonPath('student.id', $student->id);

        Sanctum::actingAs($parentUser);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(2, 'data');

        Sanctum::actingAs($studentUser);
        $inbox = $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'title', 'body', 'category', 'created_at', 'read_at']]]);
        $notificationId = $inbox->json('data.0.id');
        $this->assertNull($inbox->json('data.0.read_at'));

        $this->postJson("/api/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('id', $notificationId);
        $this->assertNotNull($this->getJson('/api/notifications')->json('data.0.read_at'));

        Sanctum::actingAs($parentUser);
        $this->postJson("/api/notifications/{$notificationId}/read")->assertNotFound();
    }

    public function test_students_never_receive_exam_answer_keys(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = Student::factory()->create();
        $studentUser = User::factory()->create(['role' => 'student']);
        StudentAccount::create(['user_id' => $studentUser->id, 'student_id' => $student->id, 'relationship' => 'student']);
        $template = ExamTemplate::create(['title' => 'اختبار العلوم', 'duration_minutes' => 20, 'status' => 'published', 'created_by' => $teacher->id]);
        $question = $template->questions()->create(['type' => 'mcq', 'prompt_html' => '<p>ما الغاز اللازم للتنفس؟</p>', 'options' => ['الأكسجين', 'النيتروجين'], 'correct_answer' => 'الأكسجين', 'points' => 2, 'sort_order' => 0]);

        Sanctum::actingAs($studentUser);
        $templates = $this->getJson('/api/exam-templates')->assertOk();
        $this->assertArrayNotHasKey('correct_answer', $templates->json('data.0.questions.0'));

        $session = $this->postJson("/api/exam-templates/{$template->id}/start")->assertSuccessful();
        $this->assertArrayNotHasKey('correct_answer', $session->json('template.questions.0'));

        $sessionId = $session->json('id');
        $this->postJson("/api/exam-sessions/{$sessionId}/answers", ['question_id' => $question->id, 'answer' => 'الأكسجين'])->assertSuccessful();
        $submitted = $this->postJson("/api/exam-sessions/{$sessionId}/submit")->assertOk()->assertJsonPath('status', 'submitted');
        $this->assertArrayNotHasKey('correct_answer', $submitted->json('template.questions.0'));

        Sanctum::actingAs($teacher);
        $this->assertSame('الأكسجين', $this->getJson('/api/exam-templates')->json('data.0.questions.0.correct_answer'));
    }
}
