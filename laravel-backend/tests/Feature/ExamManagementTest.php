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

    public function test_staff_can_create_a_dimensioned_geometry_question(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $response = $this->actingAs($teacher, 'sanctum')->postJson('/api/exam-templates', [
            'title' => 'اختبار الهندسة',
            'grade' => 'الأول الإعدادي',
            'duration_minutes' => 30,
            'status' => 'draft',
            'questions' => [[
                'type' => 'geometry',
                'prompt_html' => '<p>احسب مساحة المستطيل.</p>',
                'options' => ['shape' => 'rectangle', 'dimensions' => ['width' => '٦ سم', 'height' => '٤ سم']],
                'correct_answer' => '٢٤ سم²',
                'points' => 4,
            ]],
        ]);

        $response->assertCreated()->assertJsonPath('questions.0.type', 'geometry');
        $this->assertDatabaseHas('exam_questions', ['type' => 'geometry']);
    }

    public function test_staff_can_download_an_exam_paper_as_pdf(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $template = ExamTemplate::create([
            'created_by' => $teacher->id,
            'title' => 'اختبار PDF',
            'duration_minutes' => 30,
            'instructions' => 'اختر الإجابة الصحيحة.',
            'watermark_text' => 'نسخة المركز',
            'watermark_opacity' => 12,
            'status' => 'draft',
        ]);
        $template->questions()->create([
            'type' => 'mcq',
            'prompt_html' => '<p>اختر ٢ + ٢</p>',
            'options' => ['٣', '٤'],
            'points' => 2,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($teacher, 'sanctum')->get("/api/exam-templates/{$template->id}/pdf");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_student_can_download_only_published_exam_papers(): void
    {
        $student = Student::factory()->create();
        $studentUser = User::factory()->create(['role' => 'student']);
        StudentAccount::create(['user_id' => $studentUser->id, 'student_id' => $student->id, 'relationship' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $draft = ExamTemplate::create(['created_by' => $teacher->id, 'title' => 'مسودة', 'duration_minutes' => 20, 'status' => 'draft']);
        $published = ExamTemplate::create(['created_by' => $teacher->id, 'title' => 'منشور', 'duration_minutes' => 20, 'status' => 'published']);

        $this->actingAs($studentUser, 'sanctum')->get("/api/exam-templates/{$draft->id}/pdf")->assertNotFound();
        $publishedResponse = $this->actingAs($studentUser, 'sanctum')->get("/api/exam-templates/{$published->id}/pdf");
        $publishedResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_staff_can_update_question_collection_with_edit_delete_add_and_reorder(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $template = ExamTemplate::create(['created_by' => $teacher->id, 'title' => 'قالب قابل للتعديل', 'duration_minutes' => 30, 'status' => 'draft']);
        $first = $template->questions()->create(['type' => 'mcq', 'prompt_html' => '<p>الأول</p>', 'options' => ['أ', 'ب'], 'points' => 1, 'sort_order' => 0]);
        $second = $template->questions()->create(['type' => 'essay', 'prompt_html' => '<p>الثاني</p>', 'options' => null, 'points' => 2, 'sort_order' => 1]);

        $response = $this->actingAs($teacher, 'sanctum')->putJson("/api/exam-templates/{$template->id}", [
            'title' => 'قالب قابل للتعديل بعد الحفظ',
            'questions' => [
                ['id' => $second->id, 'type' => 'essay', 'prompt_html' => '<p>الثاني بعد التعديل</p>', 'options' => null, 'points' => 3],
                ['id' => $first->id, 'type' => 'mcq', 'prompt_html' => '<p>الأول بعد النقل</p>', 'options' => ['أ', 'ب', 'ج'], 'points' => 2],
                ['type' => 'geometry', 'prompt_html' => '<p>أضف شكلاً</p>', 'options' => ['shape' => 'circle', 'dimensions' => ['radius' => '٣']], 'points' => 4],
            ],
        ]);

        $response->assertOk()->assertJsonPath('questions.0.id', $second->id)->assertJsonPath('questions.1.id', $first->id)->assertJsonCount(3, 'questions');
        $this->assertDatabaseHas('exam_questions', ['id' => $second->id, 'prompt_html' => '<p>الثاني بعد التعديل</p>', 'sort_order' => 0]);
        $this->assertDatabaseHas('exam_questions', ['id' => $first->id, 'prompt_html' => '<p>الأول بعد النقل</p>', 'sort_order' => 1]);
        $this->assertDatabaseHas('exam_questions', ['template_id' => $template->id, 'type' => 'geometry', 'sort_order' => 2]);
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
