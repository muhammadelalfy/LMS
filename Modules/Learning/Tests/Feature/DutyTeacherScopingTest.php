<?php

namespace Modules\Learning\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Groups\Models\ClassGroup;
use Modules\Learning\Models\Duty;
use Tests\TestCase;

/** ADR 0002: a teacher writes duties only for groups they teach, or unassigned ones. */
class DutyTeacherScopingTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name, ?User $teacher = null): ClassGroup
    {
        return ClassGroup::create([
            'name' => $name, 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1],
            'teacher_id' => $teacher?->id,
        ]);
    }

    public function test_a_teacher_may_write_duties_for_their_own_and_unassigned_groups_but_not_anothers(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $this->group('بلا معلم');

        Sanctum::actingAs($mine);
        $this->postJson('/api/duties', ['group' => 'مجموعتي', 'title' => 'واجب', 'due_on' => '2026-10-20'])->assertCreated();
        $this->postJson('/api/duties', ['group' => 'بلا معلم', 'title' => 'واجب', 'due_on' => '2026-10-20'])->assertCreated();
        $this->postJson('/api/duties', ['group' => 'مجموعة الآخر', 'title' => 'واجب', 'due_on' => '2026-10-20'])->assertForbidden();
    }

    public function test_a_teacher_may_update_and_delete_their_own_groups_duty_but_not_anothers(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $myDuty = Duty::create(['group' => 'مجموعتي', 'title' => 'واجبي', 'due_on' => '2026-10-20']);
        $theirDuty = Duty::create(['group' => 'مجموعة الآخر', 'title' => 'واجبهم', 'due_on' => '2026-10-20']);

        Sanctum::actingAs($mine);
        $this->putJson("/api/duties/{$myDuty->id}", ['done' => true])->assertOk()->assertJsonPath('done', true);
        $this->putJson("/api/duties/{$theirDuty->id}", ['done' => true])->assertForbidden();
        $this->deleteJson("/api/duties/{$theirDuty->id}")->assertForbidden();
        $this->deleteJson("/api/duties/{$myDuty->id}")->assertNoContent();
    }

    public function test_an_admin_may_write_any_groups_duty(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعة المعلم', $teacher);
        $duty = Duty::create(['group' => 'مجموعة المعلم', 'title' => 'واجب', 'due_on' => '2026-10-20']);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson("/api/duties/{$duty->id}", ['done' => true])->assertOk();
        $this->deleteJson("/api/duties/{$duty->id}")->assertNoContent();
    }
}
