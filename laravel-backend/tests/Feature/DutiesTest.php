<?php

namespace Tests\Feature;

use App\Models\ClassGroup;
use App\Models\Duty;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DutiesTest extends TestCase
{
    use RefreshDatabase;

    /** Monday 5 October 2026, noon, Cairo time. */
    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monday = CarbonImmutable::parse('2026-10-05 12:00:00', 'Africa/Cairo');
        $this->travelTo($this->monday);
    }

    private function learnerIn(string $group): array
    {
        $student = Student::factory()->create(['group' => $group]);
        $parent = User::factory()->create(['role' => 'parent']);
        $learner = User::factory()->create(['role' => 'student']);
        StudentAccount::create(['user_id' => $parent->id, 'student_id' => $student->id, 'relationship' => 'parent']);
        StudentAccount::create(['user_id' => $learner->id, 'student_id' => $student->id, 'relationship' => 'student']);

        return [$student, $parent, $learner];
    }

    private function groupA(): ClassGroup
    {
        return ClassGroup::create(['name' => 'أ', 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1], 'late_after_minutes' => 10]);
    }

    public function test_staff_add_tick_off_and_delete_duties_by_hand(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $id = $this->postJson('/api/duties', [
            'group' => 'أ', 'title' => 'حل صفحة ٤٢', 'details' => 'التمارين من ١ إلى ٨', 'due_on' => '2026-10-07',
        ])->assertCreated()->assertJsonPath('done', false)->json('id');

        $this->putJson("/api/duties/{$id}", ['done' => true])
            ->assertOk()->assertJsonPath('done', true)->assertJsonPath('title', 'حل صفحة ٤٢');
        $this->putJson("/api/duties/{$id}", ['done' => false])->assertOk()->assertJsonPath('done', false);
        $this->putJson("/api/duties/{$id}", ['title' => 'حل صفحة ٤٣'])->assertOk()->assertJsonPath('title', 'حل صفحة ٤٣');

        $this->postJson('/api/duties', ['group' => 'أ', 'title' => '', 'due_on' => 'غدا'])
            ->assertStatus(422)->assertJsonValidationErrors(['title', 'due_on']);

        $this->deleteJson("/api/duties/{$id}")->assertNoContent();
        $this->assertSame(0, Duty::count());
    }

    public function test_learners_see_only_their_own_groups_duties_and_cannot_change_them(): void
    {
        Duty::create(['group' => 'أ', 'title' => 'واجب أ', 'due_on' => '2026-10-05']);
        Duty::create(['group' => 'ب', 'title' => 'واجب ب', 'due_on' => '2026-10-05']);
        [, $parent] = $this->learnerIn('أ');

        Sanctum::actingAs($parent);
        $titles = collect($this->getJson('/api/duties')->assertOk()->json('data'))->pluck('title');
        $this->assertSame(['واجب أ'], $titles->all());

        $this->postJson('/api/duties', ['group' => 'أ', 'title' => 'x', 'due_on' => '2026-10-06'])->assertForbidden();
        $this->putJson('/api/duties/'.Duty::query()->value('id'), ['done' => true])->assertForbidden();
        $this->postJson('/api/duties/remind')->assertForbidden();
    }

    public function test_the_group_is_reminded_once_on_its_lesson_day_before_the_lesson(): void
    {
        $this->groupA();
        [, $parent, $learner] = $this->learnerIn('أ');
        [, $otherParent] = $this->learnerIn('ب');
        Duty::create(['group' => 'أ', 'title' => 'حل صفحة ٤٢', 'due_on' => '2026-10-05']);
        Duty::create(['group' => 'أ', 'title' => 'حفظ القانون', 'due_on' => '2026-10-05']);
        Duty::create(['group' => 'أ', 'title' => 'واجب غدا', 'due_on' => '2026-10-06']);
        Duty::create(['group' => 'أ', 'title' => 'واجب منتهي', 'due_on' => '2026-10-05', 'done_at' => now()]);
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        // Noon is more than two hours before the 15:00 lesson: too early.
        $this->postJson('/api/duties/remind', ['now' => '2026-10-05T12:00:00+03:00'])->assertJsonPath('reminded', 0);

        // 13:30 is inside the two-hour window before the lesson.
        $this->postJson('/api/duties/remind', ['now' => '2026-10-05T13:30:00+03:00'])->assertJsonPath('reminded', 2);
        $this->postJson('/api/duties/remind', ['now' => '2026-10-05T14:00:00+03:00'])->assertJsonPath('reminded', 0);

        foreach ([$parent, $learner] as $account) {
            $this->assertSame(1, $account->notifications()->count());
            $data = $account->notifications()->first()->data;
            $this->assertSame('duty', $data['category']);
            $this->assertStringContainsString('حل صفحة ٤٢', $data['body']);
            $this->assertStringContainsString('حفظ القانون', $data['body']);
            $this->assertStringNotContainsString('واجب غدا', $data['body']);
            $this->assertStringNotContainsString('واجب منتهي', $data['body']);
        }
        $this->assertSame(0, $otherParent->notifications()->count());
    }

    public function test_no_reminder_after_the_lesson_has_ended_but_groups_without_a_timetable_are_reminded(): void
    {
        $this->groupA();
        [, $parentOfA] = $this->learnerIn('أ');
        Duty::create(['group' => 'أ', 'title' => 'متأخر', 'due_on' => '2026-10-05']);
        Duty::create(['group' => 'ج', 'title' => 'بلا جدول', 'due_on' => '2026-10-05']);
        [, $noTimetableParent] = $this->learnerIn('ج');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/duties/remind', ['now' => '2026-10-05T17:00:00+03:00'])->assertJsonPath('reminded', 1);

        $this->assertSame(1, $noTimetableParent->notifications()->count());
        $this->assertSame(0, $parentOfA->notifications()->count());
    }

    public function test_the_scheduler_command_sends_reminders_on_server_time(): void
    {
        $this->groupA();
        [, $parent] = $this->learnerIn('أ');
        Duty::create(['group' => 'أ', 'title' => 'حل صفحة ٤٢', 'due_on' => '2026-10-05']);
        $this->travelTo($this->monday->setTime(14, 0));

        $this->artisan('duties:send-reminders')->assertSuccessful();

        $this->assertSame(1, $parent->notifications()->count());
    }
}
