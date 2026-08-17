<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_attendance_and_qr_contracts_are_preserved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = Student::factory()->create();
        $student->ensureQrToken();
        Sanctum::actingAs($admin);

        $this->getJson('/api/attendance')->assertOk();
        $created = $this->postJson('/api/attendance', [
            'student_id' => $student->id,
            'date_at' => now()->toIso8601String(),
            'status' => 'present',
            'note' => 'manual',
        ])->assertCreated()->json();

        $attendanceId = $created['id'];
        $this->patchJson("/api/attendance/{$attendanceId}", ['status' => 'late'])
            ->assertOk()
            ->assertJsonPath('status', 'late');
        $this->deleteJson("/api/attendance/{$attendanceId}")->assertNoContent();

        $first = $this->postJson('/api/attendance/scan', ['payload' => $student->qr_token])
            ->assertCreated()
            ->assertJsonPath('already_recorded', false);
        $this->assertNotNull($first->json('attendance.id'));

        $this->postJson('/api/attendance/scan', ['payload' => $student->qr_token])
            ->assertOk()
            ->assertJsonPath('already_recorded', true)
            ->assertJsonPath('attendance.id', $first->json('attendance.id'));

        $this->postJson('/api/attendance/scan', ['payload' => 'invalid-qr-payload'])
            ->assertStatus(422);
    }
}
