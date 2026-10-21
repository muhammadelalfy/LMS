<?php

namespace Tests\Feature;

use Modules\Attendance\Models\AttendanceRecord;
use Modules\Exams\Models\ExamResult;
use Modules\Students\Models\Student;
use Modules\Auth\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_updates_their_own_name_and_email(): void
    {
        $user = User::factory()->create(['role' => 'teacher', 'email' => 'old@example.com']);
        User::factory()->create(['email' => 'taken@example.com']);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', ['name' => 'أستاذ جديد', 'email' => 'new@example.com'])
            ->assertOk()->assertJsonPath('name', 'أستاذ جديد')->assertJsonPath('email', 'new@example.com');
        $this->assertSame('new@example.com', $user->fresh()->email);

        // Keeping the same email is fine; someone else's is not.
        $this->putJson('/api/auth/profile', ['name' => 'أستاذ جديد', 'email' => 'new@example.com'])->assertOk();
        $this->putJson('/api/auth/profile', ['name' => 'x', 'email' => 'taken@example.com'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->putJson('/api/auth/profile', ['name' => '', 'email' => 'not-an-email'])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_changing_the_password_needs_the_current_one_and_signs_other_devices_out(): void
    {
        $user = User::factory()->create(['role' => 'teacher', 'email' => 'pw@example.com', 'password' => 'OldPassw0rd!']);
        $other = $user->createToken('other-phone')->plainTextToken;
        $current = $user->createToken('this-phone');
        $this->withToken($current->plainTextToken);

        $this->postJson('/api/auth/password', [
            'current_password' => 'wrong', 'password' => 'NewPassw0rd!2', 'password_confirmation' => 'NewPassw0rd!2',
        ])->assertStatus(422)->assertJsonPath('errors.current_password.0', 'كلمة المرور الحالية غير صحيحة.');
        $this->postJson('/api/auth/password', [
            'current_password' => 'OldPassw0rd!', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/password', [
            'current_password' => 'OldPassw0rd!', 'password' => 'NewPassw0rd!2', 'password_confirmation' => 'different',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/password', [
            'current_password' => 'OldPassw0rd!', 'password' => 'OldPassw0rd!', 'password_confirmation' => 'OldPassw0rd!',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('OldPassw0rd!', $user->fresh()->password));

        $this->postJson('/api/auth/password', [
            'current_password' => 'OldPassw0rd!', 'password' => 'NewPassw0rd!2', 'password_confirmation' => 'NewPassw0rd!2',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('NewPassw0rd!2', $user->fresh()->password));
        $this->assertSame(1, $user->tokens()->count(), 'only the device that changed it stays signed in');
        $this->assertNotNull($user->tokens()->where('name', 'this-phone')->first());
        $this->postJson('/api/auth/login', ['email' => 'pw@example.com', 'password' => 'NewPassw0rd!2'])->assertOk();
        $this->assertNotEmpty($other);
    }

    public function test_the_report_summary_adds_a_daily_trend_and_a_row_per_group(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00', 'Africa/Cairo'));
        $teacher = User::factory()->create(['role' => 'teacher']);
        $a = Student::factory()->create(['group' => 'أ']);
        $a2 = Student::factory()->create(['group' => 'أ']);
        $b = Student::factory()->create(['group' => 'ب']);
        $record = fn (Student $s, string $date, string $status) => AttendanceRecord::create([
            'student_id' => $s->id, 'attendance_date' => $date, 'date_at' => $date.' 15:00:00', 'status' => $status,
        ]);
        $record($a, '2026-10-08', 'present');
        $record($a2, '2026-10-08', 'late');
        $record($b, '2026-10-08', 'absent');
        $record($a, '2026-10-06', 'present');
        $record($a, '2026-09-01', 'present'); // outside the 7-day window
        ExamResult::create(['student_id' => $a->id, 'title' => 'اختبار', 'score' => 8, 'max_score' => 10, 'taken_at' => now(), 'recorded_by' => $teacher->id]);
        ExamResult::create(['student_id' => $b->id, 'title' => 'اختبار', 'score' => 5, 'max_score' => 10, 'taken_at' => now(), 'recorded_by' => $teacher->id]);

        Sanctum::actingAs($teacher);
        $report = $this->getJson('/api/reports/summary')->assertOk()
            ->assertJsonPath('students', 3)
            ->assertJsonPath('attendance.present', 3) // the old keys are unchanged
            ->assertJsonCount(7, 'attendance_daily')
            ->assertJsonPath('attendance_daily.0.date', '2026-10-02')
            ->assertJsonPath('attendance_daily.6.date', '2026-10-08');

        $daily = collect($report->json('attendance_daily'))->keyBy('date');
        $this->assertSame(['present' => 1, 'late' => 1, 'absent' => 1], collect($daily['2026-10-08'])->only(['present', 'late', 'absent'])->all());
        $this->assertSame(1, $daily['2026-10-06']['present']);
        $this->assertSame(0, $daily['2026-10-07']['present'] + $daily['2026-10-07']['late'] + $daily['2026-10-07']['absent']);

        $groups = collect($report->json('groups'))->keyBy('group');
        // Group totals are all-time, so the September record counts here.
        $this->assertSame(2, $groups['أ']['students']);
        $this->assertSame(['present' => 3, 'late' => 1, 'absent' => 0], collect($groups['أ'])->only(['present', 'late', 'absent'])->all());
        $this->assertSame(8, $groups['أ']['exam_score']);
        $this->assertSame(10, $groups['أ']['exam_max_score']);
        $this->assertSame(1, $groups['ب']['students']);
        $this->assertSame(1, $groups['ب']['absent']);
    }
}
