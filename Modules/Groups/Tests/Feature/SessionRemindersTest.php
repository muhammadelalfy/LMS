<?php

namespace Modules\Groups\Tests\Feature;

use Modules\Groups\Models\ClassGroup;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentAccount;
use Modules\Auth\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The app only trusts a device clock within a day and a half of its own.
        $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00', 'Africa/Cairo'));
    }

    /** Monday 5 October 2026, at the given Cairo time. */
    private function monday(string $time): string
    {
        return CarbonImmutable::parse("2026-10-05 {$time}", 'Africa/Cairo')->toIso8601String();
    }

    private function group(): ClassGroup
    {
        return ClassGroup::create(['name' => 'أ', 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1], 'late_after_minutes' => 10]);
    }

    /** @return array{0: User, 1: User} the student's own account and a parent's */
    private function learnerIn(string $group): array
    {
        $student = Student::factory()->create(['group' => $group]);
        $parent = User::factory()->create(['role' => 'parent']);
        $learner = User::factory()->create(['role' => 'student']);
        StudentAccount::create(['user_id' => $parent->id, 'student_id' => $student->id, 'relationship' => 'parent']);
        StudentAccount::create(['user_id' => $learner->id, 'student_id' => $student->id, 'relationship' => 'student']);

        return [$learner, $parent];
    }

    private function remind(string $time): int
    {
        return $this->postJson('/api/attendance/session-reminders', ['now' => $this->monday($time)])
            ->assertOk()->json('reminded');
    }

    public function test_students_and_teachers_hear_a_session_starts_soon_but_parents_do_not(): void
    {
        $this->group();
        [$learner, $parent] = $this->learnerIn('أ');
        $teacher = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($teacher);

        $this->assertSame(1, $this->remind('14:35'));

        $mine = $learner->notifications()->get();
        $this->assertCount(1, $mine);
        $this->assertSame('حصتك بعد 25 دقيقة', $mine[0]->data['title']);
        $this->assertSame('تبدأ حصة أ اليوم الساعة 3:00 م.', $mine[0]->data['body']);
        $this->assertSame('session', $mine[0]->data['category']);

        $staff = $teacher->notifications()->get();
        $this->assertCount(1, $staff);
        $this->assertSame('حصة أ بعد 25 دقيقة', $staff[0]->data['title']);
        $this->assertStringContainsString('(1 طالب)', $staff[0]->data['body']);

        $this->assertCount(0, $parent->notifications()->get());
    }

    public function test_each_group_is_reminded_once_a_day_and_only_in_the_lead_window(): void
    {
        $this->group();
        [$learner] = $this->learnerIn('أ');
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $this->assertSame(0, $this->remind('14:20'), 'too early');
        $this->assertSame(0, $this->remind('15:00'), 'already started');
        $this->assertSame(1, $this->remind('14:30'));
        $this->assertSame(0, $this->remind('14:45'), 'sent already');

        $this->assertCount(1, $learner->notifications()->get());
    }

    public function test_a_group_that_does_not_meet_today_is_not_reminded(): void
    {
        ClassGroup::create(['name' => 'ب', 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [3], 'late_after_minutes' => 10]);
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $this->assertSame(0, $this->remind('14:40'));
    }

    public function test_learners_cannot_trigger_it(): void
    {
        [$learner] = $this->learnerIn('أ');
        Sanctum::actingAs($learner);

        $this->postJson('/api/attendance/session-reminders', [])->assertForbidden();
    }
}
