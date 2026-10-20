<?php

namespace Modules\Exams\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Exams\Models\ExamResult;
use Modules\Groups\Models\ClassGroup;
use Modules\Students\Models\Student;
use Tests\TestCase;

/** ADR 0002: a teacher reaches exam results only for students in groups they teach, or unassigned ones. */
class ExamResultTeacherScopingTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name, ?User $teacher = null): ClassGroup
    {
        return ClassGroup::create([
            'name' => $name, 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1],
            'teacher_id' => $teacher?->id,
        ]);
    }

    private function body(int $studentId): array
    {
        return ['student_id' => $studentId, 'title' => 'اختبار', 'score' => 15, 'max_score' => 20, 'taken_at' => now()->toIso8601String()];
    }

    public function test_a_teacher_may_record_results_for_their_own_and_unassigned_groups_but_not_anothers(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $myStudent = Student::factory()->create(['group' => 'مجموعتي']);
        $unassignedStudent = Student::factory()->create(['group' => 'بلا معلم على الإطلاق']);
        $theirStudent = Student::factory()->create(['group' => 'مجموعة الآخر']);

        Sanctum::actingAs($mine);
        $this->postJson('/api/exams', $this->body($myStudent->id))->assertCreated();
        $this->postJson('/api/exams', $this->body($unassignedStudent->id))->assertCreated();
        $this->postJson('/api/exams', $this->body($theirStudent->id))->assertForbidden();
    }

    public function test_a_teacher_lists_updates_and_deletes_only_their_own_students_results(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $myStudent = Student::factory()->create(['group' => 'مجموعتي']);
        $theirStudent = Student::factory()->create(['group' => 'مجموعة الآخر']);
        $myResult = ExamResult::create(['student_id' => $myStudent->id, 'title' => 'ت', 'score' => 10, 'max_score' => 20, 'taken_at' => now(), 'recorded_by' => $mine->id]);
        $theirResult = ExamResult::create(['student_id' => $theirStudent->id, 'title' => 'ت', 'score' => 10, 'max_score' => 20, 'taken_at' => now(), 'recorded_by' => $other->id]);

        Sanctum::actingAs($mine);
        $ids = collect($this->getJson('/api/exams')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($myResult->id));
        $this->assertFalse($ids->contains($theirResult->id));

        $this->putJson("/api/exams/{$myResult->id}", ['score' => 18])->assertOk()->assertJsonPath('score', 18);
        $this->putJson("/api/exams/{$theirResult->id}", ['score' => 18])->assertForbidden();
        $this->deleteJson("/api/exams/{$theirResult->id}")->assertForbidden();
        $this->deleteJson("/api/exams/{$myResult->id}")->assertNoContent();
    }

    public function test_an_admin_reaches_every_students_results(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعة المعلم', $teacher);
        $student = Student::factory()->create(['group' => 'مجموعة المعلم']);
        $result = ExamResult::create(['student_id' => $student->id, 'title' => 'ت', 'score' => 10, 'max_score' => 20, 'taken_at' => now(), 'recorded_by' => $teacher->id]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson("/api/exams/{$result->id}", ['score' => 20])->assertOk();
        $this->deleteJson("/api/exams/{$result->id}")->assertNoContent();
    }
}
