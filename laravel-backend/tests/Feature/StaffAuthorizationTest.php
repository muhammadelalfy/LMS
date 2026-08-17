<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_use_staff_mutation_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));

        $this->postJson('/api/payments', [])->assertForbidden();
    }

    public function test_teacher_can_use_staff_mutation_endpoints(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = Student::factory()->create();
        Sanctum::actingAs($teacher);

        $this->postJson('/api/exams', [
            'student_id' => $student->id,
            'title' => 'اختبار تجريبي',
            'score' => 18,
            'max_score' => 20,
            'taken_at' => now()->toIso8601String(),
        ])->assertCreated();
    }
}
