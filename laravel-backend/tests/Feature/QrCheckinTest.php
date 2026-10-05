<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QrCheckinTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_returns_the_students_outstanding_dues(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = Student::factory()->create();
        $payload = $student->ensureQrToken();
        Payment::create(['student_id' => $student->id, 'amount' => 300, 'status' => 'overdue', 'due_at' => now()->subMonth(), 'recorded_by' => $teacher->id]);
        Payment::create(['student_id' => $student->id, 'amount' => 450, 'status' => 'pending', 'due_at' => now(), 'recorded_by' => $teacher->id]);
        Payment::create(['student_id' => $student->id, 'amount' => 999, 'status' => 'paid', 'due_at' => now()->subMonths(2), 'recorded_by' => $teacher->id]);

        Sanctum::actingAs($teacher);
        $this->postJson('/api/attendance/scan', ['payload' => $payload])
            ->assertCreated()
            ->assertJsonPath('already_recorded', false)
            ->assertJsonPath('attendance.student.id', $student->id)
            ->assertJsonPath('outstanding_total', 750)
            ->assertJsonCount(2, 'outstanding_payments')
            ->assertJsonPath('outstanding_payments.0.status', 'overdue');
    }

    public function test_staff_confirm_an_outstanding_payment_with_the_students_qr(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = Student::factory()->create();
        $parent = User::factory()->create(['role' => 'parent']);
        StudentAccount::create(['user_id' => $parent->id, 'student_id' => $student->id, 'relationship' => 'parent']);
        $payload = $student->ensureQrToken();
        $payment = Payment::create(['student_id' => $student->id, 'amount' => 450, 'status' => 'pending', 'due_at' => now(), 'recorded_by' => $teacher->id]);

        Sanctum::actingAs($teacher);
        $this->postJson('/api/payments/qr-checkin', ['payload' => $payload, 'payment_id' => $payment->id])
            ->assertOk()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('student.id', $student->id)
            ->assertJsonPath('outstanding_total', 0);
        $this->assertNotNull($payment->fresh()->paid_at);
        $this->assertSame(1, $parent->notifications()->count());

        $this->postJson('/api/payments/qr-checkin', ['payload' => $payload, 'payment_id' => $payment->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_id');
    }

    public function test_staff_collect_a_new_payment_at_qr_checkin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = Student::factory()->create();
        $payload = $student->ensureQrToken();

        Sanctum::actingAs($admin);
        $this->postJson('/api/payments/qr-checkin', ['payload' => $payload, 'amount' => 200, 'note' => 'رسوم كتب'])
            ->assertCreated()
            ->assertJsonPath('payment.amount', 200)
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.note', 'رسوم كتب');
    }

    public function test_qr_payment_confirmation_rejects_mismatched_cards_and_non_staff(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $owner = Student::factory()->create();
        $other = Student::factory()->create();
        $owner->ensureQrToken();
        $otherPayload = $other->ensureQrToken();
        $payment = Payment::create(['student_id' => $owner->id, 'amount' => 450, 'status' => 'pending', 'due_at' => now(), 'recorded_by' => $teacher->id]);

        Sanctum::actingAs($teacher);
        $this->postJson('/api/payments/qr-checkin', ['payload' => $otherPayload, 'payment_id' => $payment->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_id');
        $this->postJson('/api/payments/qr-checkin', ['payload' => str_repeat('x', 64), 'amount' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payload');
        $this->postJson('/api/payments/qr-checkin', ['payload' => $otherPayload])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
        $this->assertSame('pending', $payment->fresh()->status);

        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->postJson('/api/payments/qr-checkin', ['payload' => $otherPayload, 'amount' => 100])->assertForbidden();
    }

    public function test_staff_can_regenerate_a_lost_qr_card(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = Student::factory()->create();
        $oldPayload = $student->ensureQrToken();

        Sanctum::actingAs($teacher);
        $newPayload = $this->postJson("/api/students/{$student->id}/qr/regenerate")
            ->assertOk()
            ->assertJsonPath('student_id', $student->id)
            ->json('payload');

        $this->assertNotSame($oldPayload, $newPayload);
        $this->assertSame(64, strlen($newPayload));
        $this->postJson('/api/attendance/scan', ['payload' => $oldPayload])->assertStatus(422);
        $this->postJson('/api/attendance/scan', ['payload' => $newPayload])->assertCreated();

        Sanctum::actingAs(User::factory()->create(['role' => 'parent']));
        $this->postJson("/api/students/{$student->id}/qr/regenerate")->assertForbidden();
    }

    public function test_manual_attendance_uses_its_own_date_and_one_record_per_day(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = Student::factory()->create();
        Sanctum::actingAs($teacher);
        $yesterday = now()->subDay()->toDateString();

        $this->postJson('/api/attendance', ['student_id' => $student->id, 'date_at' => $yesterday, 'status' => 'absent'])
            ->assertCreated();
        $this->postJson('/api/attendance', ['student_id' => $student->id, 'date_at' => $yesterday, 'status' => 'late'])
            ->assertOk()
            ->assertJsonPath('status', 'late');

        $records = AttendanceRecord::where('student_id', $student->id)->get();
        $this->assertCount(1, $records);
        $this->assertSame($yesterday, $records->first()->attendance_date);

        $this->postJson('/api/attendance/scan', ['payload' => $student->ensureQrToken()])
            ->assertCreated()
            ->assertJsonPath('already_recorded', false);
    }
}
