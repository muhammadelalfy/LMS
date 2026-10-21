<?php

namespace Modules\Students\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Groups\Models\ClassGroup;
use Modules\Students\Models\FaceTemplate;
use Modules\Students\Models\Student;
use Tests\TestCase;

/** ADR 0002: biometric face templates are scoped to a teacher's own groups. */
class FaceTemplateTeacherScopingTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name, ?User $teacher = null): ClassGroup
    {
        return ClassGroup::create([
            'name' => $name, 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1],
            'teacher_id' => $teacher?->id,
        ]);
    }

    private function body(): array
    {
        return ['consent' => true, 'model' => 'sface-int8-v1', 'embeddings' => [base64_encode(str_repeat('a', 512))]];
    }

    public function test_a_teacher_may_enrol_a_face_for_their_own_and_unassigned_groups_but_not_anothers(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $myStudent = Student::factory()->create(['group' => 'مجموعتي']);
        $unassignedStudent = Student::factory()->create(['group' => 'بلا معلم على الإطلاق']);
        $theirStudent = Student::factory()->create(['group' => 'مجموعة الآخر']);

        Sanctum::actingAs($mine);
        $this->putJson("/api/students/{$myStudent->id}/face-template", $this->body())->assertOk();
        $this->putJson("/api/students/{$unassignedStudent->id}/face-template", $this->body())->assertOk();
        $this->putJson("/api/students/{$theirStudent->id}/face-template", $this->body())->assertForbidden();
    }

    public function test_a_teacher_lists_and_removes_only_their_own_students_faces(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $myStudent = Student::factory()->create(['group' => 'مجموعتي']);
        $theirStudent = Student::factory()->create(['group' => 'مجموعة الآخر']);
        FaceTemplate::create(['student_id' => $myStudent->id, 'model' => 'm', 'embeddings' => ['x'], 'enrolled_at' => now()]);
        FaceTemplate::create(['student_id' => $theirStudent->id, 'model' => 'm', 'embeddings' => ['x'], 'enrolled_at' => now()]);

        Sanctum::actingAs($mine);
        $ids = collect($this->getJson('/api/face-templates')->assertOk()->json('data'))->pluck('student_id');
        $this->assertTrue($ids->contains($myStudent->id));
        $this->assertFalse($ids->contains($theirStudent->id));

        $this->deleteJson("/api/students/{$theirStudent->id}/face-template")->assertForbidden();
        $this->deleteJson("/api/students/{$myStudent->id}/face-template")->assertNoContent();
    }

    public function test_an_admin_reaches_every_students_face(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعة المعلم', $teacher);
        $student = Student::factory()->create(['group' => 'مجموعة المعلم']);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson("/api/students/{$student->id}/face-template", $this->body())->assertOk();
        $this->deleteJson("/api/students/{$student->id}/face-template")->assertNoContent();
    }
}
