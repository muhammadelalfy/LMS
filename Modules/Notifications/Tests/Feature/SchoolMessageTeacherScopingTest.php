<?php

namespace Modules\Notifications\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Groups\Models\ClassGroup;
use Modules\Students\Models\Student;
use Tests\TestCase;

/**
 * ADR 0002: a teacher may message a group or chosen students only within the
 * groups they teach, or unassigned ones; a whole-school broadcast is admin-only.
 */
class SchoolMessageTeacherScopingTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name, ?User $teacher = null): ClassGroup
    {
        return ClassGroup::create([
            'name' => $name, 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1],
            'teacher_id' => $teacher?->id,
        ]);
    }

    private function body(array $overrides = []): array
    {
        return $overrides + ['title' => 'عنوان', 'body' => 'نص', 'audience' => 'students'];
    }

    public function test_a_teacher_may_message_their_own_group_or_an_unassigned_one_but_not_anothers(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $this->group('بلا معلم');
        Student::factory()->create(['group' => 'مجموعتي']);
        Student::factory()->create(['group' => 'مجموعة الآخر']);
        Student::factory()->create(['group' => 'بلا معلم']);

        Sanctum::actingAs($mine);
        $this->postJson('/api/notifications/send', $this->body(['group' => 'مجموعتي']))->assertOk();
        $this->postJson('/api/notifications/send', $this->body(['group' => 'بلا معلم']))->assertOk();
        $this->postJson('/api/notifications/send', $this->body(['group' => 'مجموعة الآخر']))->assertForbidden();
    }

    public function test_a_teacher_may_message_chosen_students_only_within_their_own_groups(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $myStudent = Student::factory()->create(['group' => 'مجموعتي']);
        $theirStudent = Student::factory()->create(['group' => 'مجموعة الآخر']);

        Sanctum::actingAs($mine);
        $this->postJson('/api/notifications/send', $this->body(['student_ids' => [$myStudent->id]]))->assertOk();
        $this->postJson('/api/notifications/send', $this->body(['student_ids' => [$theirStudent->id]]))->assertForbidden();
        $this->postJson('/api/notifications/send', $this->body(['student_ids' => [$myStudent->id, $theirStudent->id]]))->assertForbidden();
    }

    public function test_only_an_admin_broadcasts_to_the_whole_school(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        Student::factory()->create();

        Sanctum::actingAs($teacher);
        $this->postJson('/api/notifications/send', $this->body(['all' => true]))->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/notifications/send', $this->body(['all' => true]))->assertOk();
    }
}
