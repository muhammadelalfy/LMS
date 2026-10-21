<?php

namespace Modules\Payments\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Groups\Models\ClassGroup;
use Modules\Payments\Models\Payment;
use Modules\Students\Models\Student;
use Tests\TestCase;

/**
 * ADR 0002: a teacher reaches payments only for students in groups they
 * teach, or unassigned ones. Deleting a payment is admin-only even for the
 * teacher's own student, to prevent hiding a cash shortfall.
 */
class PaymentTeacherScopingTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name, ?User $teacher = null): ClassGroup
    {
        return ClassGroup::create([
            'name' => $name, 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1],
            'teacher_id' => $teacher?->id,
        ]);
    }

    private function body(int $studentId): array
    {
        return ['student_id' => $studentId, 'amount' => 500, 'status' => 'pending', 'due_at' => now()->toIso8601String()];
    }

    public function test_a_teacher_may_record_payments_for_their_own_and_unassigned_groups_but_not_anothers(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $myStudent = Student::factory()->create(['group' => 'مجموعتي']);
        $unassignedStudent = Student::factory()->create(['group' => 'بلا معلم على الإطلاق']);
        $theirStudent = Student::factory()->create(['group' => 'مجموعة الآخر']);

        Sanctum::actingAs($mine);
        $this->postJson('/api/payments', $this->body($myStudent->id))->assertCreated();
        $this->postJson('/api/payments', $this->body($unassignedStudent->id))->assertCreated();
        $this->postJson('/api/payments', $this->body($theirStudent->id))->assertForbidden();
    }

    public function test_a_teacher_lists_and_updates_only_their_own_students_payments_but_may_never_delete(): void
    {
        $mine = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعتي', $mine);
        $this->group('مجموعة الآخر', $other);
        $myStudent = Student::factory()->create(['group' => 'مجموعتي']);
        $theirStudent = Student::factory()->create(['group' => 'مجموعة الآخر']);
        $myPayment = Payment::create(['student_id' => $myStudent->id, 'amount' => 500, 'status' => 'pending', 'due_at' => now(), 'recorded_by' => $mine->id]);
        $theirPayment = Payment::create(['student_id' => $theirStudent->id, 'amount' => 500, 'status' => 'pending', 'due_at' => now(), 'recorded_by' => $other->id]);

        Sanctum::actingAs($mine);
        $ids = collect($this->getJson('/api/payments')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($myPayment->id));
        $this->assertFalse($ids->contains($theirPayment->id));

        $this->putJson("/api/payments/{$myPayment->id}", ['status' => 'paid'])->assertOk()->assertJsonPath('status', 'paid');
        $this->putJson("/api/payments/{$theirPayment->id}", ['status' => 'paid'])->assertForbidden();

        // Deleting is admin-only, even for the teacher's own student's payment.
        $this->deleteJson("/api/payments/{$myPayment->id}")->assertForbidden();
        $this->assertNotNull($myPayment->fresh());
    }

    public function test_only_an_admin_deletes_a_payment(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->group('مجموعة المعلم', $teacher);
        $student = Student::factory()->create(['group' => 'مجموعة المعلم']);
        $payment = Payment::create(['student_id' => $student->id, 'amount' => 500, 'status' => 'pending', 'due_at' => now(), 'recorded_by' => $teacher->id]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson("/api/payments/{$payment->id}")->assertNoContent();
        $this->assertNull(Payment::find($payment->id));
    }
}
