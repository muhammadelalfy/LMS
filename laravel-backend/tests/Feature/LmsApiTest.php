<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Models\Worksheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LmsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_login(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'name' => 'ولي أمر تجريبي',
            'email' => 'parent@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'parent',
        ]);

        $register->assertCreated()->assertJsonPath('user.role', 'parent');
        $this->postJson('/api/auth/login', [
            'email' => 'parent@example.com',
            'password' => 'Password123!',
        ])->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_teacher_can_assign_and_student_can_submit_a_worksheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $studentUser = User::factory()->create(['role' => 'student']);
        $student = Student::create(['name' => 'طالب تجريبي', 'group' => 'بنين', 'grade' => 'ثانية إعدادى', 'phone' => '0100000000']);
        $student->account()->create(['user_id' => $studentUser->id, 'relationship' => 'student']);
        $worksheet = Worksheet::create(['title' => 'شيت الجبر', 'subject' => 'الجبر', 'grade' => 'ثانية إعدادى', 'status' => 'published', 'created_by' => $teacher->id]);

        Sanctum::actingAs($teacher);
        $assignment = $this->postJson("/api/worksheets/{$worksheet->id}/assign", ['student_ids' => [$student->id]])
            ->assertOk()->json('assignments.0.id');

        Sanctum::actingAs($studentUser);
        $this->postJson("/api/assignments/{$assignment}/submit", ['score' => 18, 'max_score' => 20])
            ->assertOk()->assertJsonPath('status', 'submitted');
    }

    public function test_student_cannot_create_students_or_view_admin_reports(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->postJson('/api/students', [
            'name' => 'غير مصرح', 'group' => 'بنين', 'grade' => 'ثانية إعدادى', 'phone' => '0100000000',
        ])->assertForbidden();
        $this->getJson('/api/reports/summary')->assertForbidden();
    }
}
