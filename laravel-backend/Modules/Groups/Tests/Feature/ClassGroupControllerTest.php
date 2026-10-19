<?php

namespace Modules\Groups\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Groups\Models\ClassGroup;
use Tests\TestCase;

/**
 * ADR 0002: a teacher reaches only their own groups and unassigned ones;
 * reassigning (`teacher_id`) and deleting a group are admin-only.
 */
class ClassGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function group(array $overrides = []): ClassGroup
    {
        return ClassGroup::create($overrides + [
            'name' => 'أ', 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1, 3], 'late_after_minutes' => 10,
        ]);
    }

    private function body(array $overrides = []): array
    {
        return $overrides + ['name' => 'ب', 'start_time' => '09:00', 'end_time' => '10:00', 'days' => [2]];
    }

    public function test_a_teacher_sees_and_lists_only_their_own_and_unassigned_groups(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group(['name' => 'مجموعتي', 'teacher_id' => $mine->id]);
        $this->group(['name' => 'مجموعة الآخر', 'teacher_id' => $other->id]);
        $this->group(['name' => 'بلا معلم']);

        Sanctum::actingAs($mine);
        $names = collect($this->getJson('/api/groups')->assertOk()->json('data'))->pluck('name')->sort()->values();
        $this->assertSame(['بلا معلم', 'مجموعتي'], $names->all());

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/groups')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_a_teacher_may_create_a_group_naming_only_themselves_or_nobody(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($teacher);

        // Omitted entirely: the existing "whoever creates it runs it" default applies.
        $id = $this->postJson('/api/groups', $this->body(['name' => 'تلقائياً لي']))
            ->assertCreated()->json('id');
        $this->assertSame($teacher->id, ClassGroup::find($id)->teacher_id);

        // Explicitly left unassigned: honoured as-is.
        $id = $this->postJson('/api/groups', $this->body(['name' => 'بلا معلم بعد', 'teacher_id' => null]))
            ->assertCreated()->json('id');
        $this->assertNull(ClassGroup::find($id)->teacher_id);

        $id = $this->postJson('/api/groups', $this->body(['name' => 'ملكي', 'teacher_id' => $teacher->id]))
            ->assertCreated()->json('id');
        $this->assertSame($teacher->id, ClassGroup::find($id)->teacher_id);

        $this->postJson('/api/groups', $this->body(['name' => 'لغيري', 'teacher_id' => $other->id]))
            ->assertForbidden();
    }

    public function test_a_teacher_updates_their_own_groups_schedule_and_unassigned_ones_but_not_another_teachers(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $own = $this->group(['name' => 'مجموعتي', 'teacher_id' => $mine->id]);
        $unassigned = $this->group(['name' => 'بلا معلم']);
        $theirs = $this->group(['name' => 'مجموعة الآخر', 'teacher_id' => $other->id]);

        Sanctum::actingAs($mine);
        $this->putJson("/api/groups/{$own->id}", $this->body(['name' => 'مجموعتي', 'start_time' => '14:00', 'end_time' => '15:00']))
            ->assertOk()->assertJsonPath('start_time', '14:00');
        $this->putJson("/api/groups/{$unassigned->id}", $this->body(['name' => 'بلا معلم', 'start_time' => '08:00', 'end_time' => '09:00']))
            ->assertOk()->assertJsonPath('start_time', '08:00');
        $this->putJson("/api/groups/{$theirs->id}", $this->body(['name' => 'مجموعة الآخر', 'start_time' => '08:00', 'end_time' => '09:00']))
            ->assertForbidden();
    }

    public function test_only_an_admin_reassigns_a_groups_teacher_even_the_teachers_own_group(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $successor = User::factory()->create(['role' => 'teacher']);
        $own = $this->group(['name' => 'مجموعتي', 'teacher_id' => $mine->id]);

        Sanctum::actingAs($mine);
        // Scoped action (editing the schedule) is allowed.
        $this->putJson("/api/groups/{$own->id}", $this->body(['name' => 'مجموعتي', 'start_time' => '10:00', 'end_time' => '11:00']))
            ->assertOk();
        // Admin-only sub-action (reassigning teacher_id) is forbidden, even on their own group.
        $this->putJson("/api/groups/{$own->id}", $this->body(['name' => 'مجموعتي', 'teacher_id' => $successor->id]))
            ->assertForbidden();
        $this->assertSame($mine->id, $own->fresh()->teacher_id);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson("/api/groups/{$own->id}", $this->body(['name' => 'مجموعتي', 'teacher_id' => $successor->id]))
            ->assertOk();
        $this->assertSame($successor->id, $own->fresh()->teacher_id);
    }

    public function test_only_an_admin_deletes_a_group_even_the_teachers_own(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $own = $this->group(['name' => 'مجموعتي', 'teacher_id' => $mine->id]);

        Sanctum::actingAs($mine);
        $this->deleteJson("/api/groups/{$own->id}")->assertForbidden();
        $this->assertNotNull($own->fresh());

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson("/api/groups/{$own->id}")->assertNoContent();
        $this->assertNull(ClassGroup::find($own->id));
    }
}
