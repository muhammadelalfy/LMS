<?php

namespace Modules\Learning\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Learning\Models\AgendaItem;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentAccount;
use Tests\TestCase;

/** The teacher's agenda: what to do with a group, on a day. */
class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private function item(array $extra = []): array
    {
        return array_merge(['group' => 'أ', 'title' => 'تجهيز اختبار قصير', 'due_on' => now()->addDays(2)->toDateString()], $extra);
    }

    public function test_a_teacher_adds_ticks_off_edits_and_deletes_their_notes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $id = $this->postJson('/api/agenda', $this->item(['details' => 'من الدرس ٣']))
            ->assertCreated()->assertJsonPath('done', false)->assertJsonPath('group', 'أ')->json('id');

        $this->putJson("/api/agenda/{$id}", ['done' => true])->assertOk()->assertJsonPath('done', true);
        $this->putJson("/api/agenda/{$id}", ['done' => false])->assertOk()->assertJsonPath('done', false);
        $this->putJson("/api/agenda/{$id}", ['title' => 'مراجعة الدرس'])->assertOk()->assertJsonPath('title', 'مراجعة الدرس');

        $this->getJson('/api/agenda')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/agenda/{$id}")->assertNoContent();
        $this->getJson('/api/agenda')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_it_is_validated_like_a_duty(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $this->postJson('/api/agenda', ['group' => 'أ', 'title' => '', 'due_on' => 'غدا'])
            ->assertStatus(422)->assertJsonValidationErrors(['title', 'due_on']);
    }

    public function test_a_teacher_sees_only_their_own_but_an_admin_sees_all(): void
    {
        $first = User::factory()->create(['role' => 'teacher']);
        $second = User::factory()->create(['role' => 'teacher']);
        AgendaItem::create([...$this->item(['title' => 'ملاحظة الأول']), 'created_by' => $first->id]);
        AgendaItem::create([...$this->item(['title' => 'ملاحظة الثاني']), 'created_by' => $second->id]);

        Sanctum::actingAs($first);
        $this->getJson('/api/agenda')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'ملاحظة الأول');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/agenda')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_teacher_cannot_change_or_delete_another_teachers_note(): void
    {
        $owner = User::factory()->create(['role' => 'teacher']);
        $id = AgendaItem::create([...$this->item(), 'created_by' => $owner->id])->id;

        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $this->putJson("/api/agenda/{$id}", ['done' => true])->assertNotFound();
        $this->deleteJson("/api/agenda/{$id}")->assertNotFound();
        $this->assertDatabaseHas('agenda_items', ['id' => $id]);
    }

    public function test_students_and_parents_never_see_the_agenda(): void
    {
        $student = Student::factory()->create(['group' => 'أ']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        AgendaItem::create([...$this->item(), 'created_by' => $teacher->id]);

        foreach (['student', 'parent'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            StudentAccount::create(['user_id' => $user->id, 'student_id' => $student->id, 'relationship' => $role]);
            Sanctum::actingAs($user);

            $this->getJson('/api/agenda')->assertForbidden();
            $this->postJson('/api/agenda', $this->item())->assertForbidden();
        }
    }

    public function test_old_items_drop_out_of_the_list(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        AgendaItem::create([...$this->item(['title' => 'قديمة', 'due_on' => now()->subDays(45)->toDateString()]), 'created_by' => $teacher->id]);
        AgendaItem::create([...$this->item(['title' => 'حديثة', 'due_on' => now()->subDays(3)->toDateString()]), 'created_by' => $teacher->id]);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/agenda')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'حديثة');
    }
}
