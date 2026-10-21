<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Auth\Models\User;
use Modules\Groups\Models\ClassGroup;
use Modules\Students\Models\Student;
use Tests\TestCase;

/**
 * ADR 0002: manual attendance (store/update/destroy, and the list) is
 * scoped to a teacher's own groups. `scan`/`sweep`/`remindSessions` are
 * deliberately not: they stay staff-wide (front desk / batch triggers).
 */
class AttendanceTeacherScopingTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name, ?User $teacher = null): ClassGroup
    {
        return ClassGroup::create([
            'name' => $name, 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1],
            'teacher_id' => $teacher?->id,
        ]);
    }

    public function test_a_teacher_may_record_attendance_for_their_own_and_unassigned_groups_but_not_anothers(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $myStudent = Student::factory()->create(['group' => 'مجموعتي']);
        $unassignedStudent = Student::factory()->create(['group' => 'بلا معلم على الإطلاق']);
        $theirStudent = Student::factory()->create(['group' => 'مجموعة الآخر']);

        Sanctum::actingAs($mine);
        $this->postJson('/api/attendance', ['student_id' => $myStudent->id, 'date_at' => now()->toIso8601String(), 'status' => 'present'])
            ->assertCreated();
        $this->postJson('/api/attendance', ['student_id' => $unassignedStudent->id, 'date_at' => now()->toIso8601String(), 'status' => 'present'])
            ->assertCreated();
        $this->postJson('/api/attendance', ['student_id' => $theirStudent->id, 'date_at' => now()->toIso8601String(), 'status' => 'present'])
            ->assertForbidden();
    }

    public function test_a_teacher_lists_updates_and_deletes_only_their_own_students_attendance(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $myStudent = Student::factory()->create(['group' => 'مجموعتي']);
        $theirStudent = Student::factory()->create(['group' => 'مجموعة الآخر']);
        $myRecord = AttendanceRecord::create(['student_id' => $myStudent->id, 'date_at' => now(), 'attendance_date' => now()->toDateString(), 'status' => 'present']);
        $theirRecord = AttendanceRecord::create(['student_id' => $theirStudent->id, 'date_at' => now(), 'attendance_date' => now()->toDateString(), 'status' => 'present']);

        Sanctum::actingAs($mine);
        $ids = collect($this->getJson('/api/attendance')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($myRecord->id));
        $this->assertFalse($ids->contains($theirRecord->id));

        $this->patchJson("/api/attendance/{$myRecord->id}", ['status' => 'late'])->assertOk()->assertJsonPath('status', 'late');
        $this->patchJson("/api/attendance/{$theirRecord->id}", ['status' => 'late'])->assertForbidden();
        $this->deleteJson("/api/attendance/{$theirRecord->id}")->assertForbidden();
        $this->deleteJson("/api/attendance/{$myRecord->id}")->assertNoContent();
    }

    public function test_an_admin_reaches_every_students_attendance(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعة المعلم', $teacher);
        $student = Student::factory()->create(['group' => 'مجموعة المعلم']);
        $record = AttendanceRecord::create(['student_id' => $student->id, 'date_at' => now(), 'attendance_date' => now()->toDateString(), 'status' => 'present']);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson("/api/attendance/{$record->id}", ['status' => 'absent'])->assertOk();
        $this->deleteJson("/api/attendance/{$record->id}")->assertNoContent();
    }

    public function test_scan_sweep_and_remind_sessions_stay_staff_wide_regardless_of_group_ownership(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعة الآخر', User::factory()->create(['role' => 'teacher']));
        $student = Student::factory()->create(['group' => 'مجموعة الآخر']);
        $student->ensureQrToken();

        Sanctum::actingAs($teacher);
        $this->postJson('/api/attendance/scan', ['payload' => $student->qr_token, 'confirm_outside_session' => true])->assertSuccessful();
        $this->postJson('/api/attendance/sweep')->assertOk();
        $this->postJson('/api/attendance/session-reminders')->assertOk();
    }
}
