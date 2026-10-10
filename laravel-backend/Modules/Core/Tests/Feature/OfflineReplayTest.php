<?php

namespace Modules\Core\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Auth\Models\User;
use Modules\Payments\Models\Payment;
use Modules\Students\Models\Student;
use Tests\TestCase;

/**
 * What lets a phone keep working without a connection: writes it kept are
 * sent again later, with their own key and their own time, and are recorded
 * exactly once on the day they happened.
 */
class OfflineReplayTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'a1b2c3d4-0000-4000-8000-000000000001';

    private function teacher(): User
    {
        return User::factory()->create(['role' => 'teacher']);
    }

    // Idempotency-Key ----------------------------------------------------------------------

    public function test_the_same_key_records_a_payment_once_and_replays_the_answer(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs($this->teacher());
        $body = ['payload' => $student->ensureQrToken(), 'amount' => 250, 'note' => 'رسوم'];

        $first = $this->withHeaders(['Idempotency-Key' => self::KEY])->postJson('/api/payments/qr-checkin', $body)
            ->assertCreated();
        $second = $this->withHeaders(['Idempotency-Key' => self::KEY])->postJson('/api/payments/qr-checkin', $body)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame(1, Payment::query()->where('student_id', $student->id)->count());
        $this->assertSame($first->json('payment.id'), $second->json('payment.id'));
    }

    public function test_a_new_key_or_no_key_records_another_payment(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs($this->teacher());
        $body = ['payload' => $student->ensureQrToken(), 'amount' => 100];

        $this->withHeaders(['Idempotency-Key' => self::KEY])->postJson('/api/payments/qr-checkin', $body)->assertCreated();
        $this->withHeaders(['Idempotency-Key' => 'a1b2c3d4-0000-4000-8000-000000000002'])->postJson('/api/payments/qr-checkin', $body)
            ->assertCreated()
            ->assertHeaderMissing('Idempotency-Replayed');
        // withHeaders() sticks to the test client; the third request carries none.
        $this->flushHeaders();
        $this->postJson('/api/payments/qr-checkin', $body)->assertCreated();

        $this->assertSame(3, Payment::query()->where('student_id', $student->id)->count());
    }

    public function test_a_key_belongs_to_one_person_and_one_endpoint(): void
    {
        $student = Student::factory()->create();
        $payload = $student->ensureQrToken();

        Sanctum::actingAs($this->teacher());
        $this->withHeaders(['Idempotency-Key' => self::KEY])->postJson('/api/payments/qr-checkin', ['payload' => $payload, 'amount' => 10])->assertCreated();

        // Another teacher sending the same key is a different request.
        Sanctum::actingAs($this->teacher());
        $this->withHeaders(['Idempotency-Key' => self::KEY])->postJson('/api/payments/qr-checkin', ['payload' => $payload, 'amount' => 10])
            ->assertCreated()
            ->assertHeaderMissing('Idempotency-Replayed');

        // The same key on another endpoint is a different request too.
        $this->withHeaders(['Idempotency-Key' => self::KEY])->postJson('/api/attendance/scan', ['payload' => $payload])
            ->assertCreated()
            ->assertHeaderMissing('Idempotency-Replayed');

        $this->assertSame(2, Payment::query()->where('student_id', $student->id)->count());
    }

    public function test_a_refused_request_is_not_remembered_so_it_can_be_corrected(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs($this->teacher());

        $this->withHeaders(['Idempotency-Key' => self::KEY])->postJson('/api/payments/qr-checkin', ['payload' => str_repeat('x', 64), 'amount' => 10])
            ->assertStatus(422);
        $this->withHeaders(['Idempotency-Key' => self::KEY])->postJson('/api/payments/qr-checkin', ['payload' => $student->ensureQrToken(), 'amount' => 10])
            ->assertCreated()
            ->assertHeaderMissing('Idempotency-Replayed');
    }

    public function test_a_malformed_key_is_refused_before_anything_is_recorded(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs($this->teacher());

        $this->withHeaders(['Idempotency-Key' => 'short'])->postJson('/api/payments/qr-checkin', ['payload' => $student->ensureQrToken(), 'amount' => 10])
            ->assertStatus(400);
        $this->assertSame(0, Payment::count());
    }

    public function test_a_key_does_not_let_a_signed_out_caller_read_a_stored_answer(): void
    {
        $student = Student::factory()->create();
        Sanctum::actingAs($this->teacher());
        $body = ['payload' => $student->ensureQrToken(), 'amount' => 10];
        $this->withHeaders(['Idempotency-Key' => self::KEY])->postJson('/api/payments/qr-checkin', $body)->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Idempotency-Key' => self::KEY])->postJson('/api/payments/qr-checkin', $body)
            ->assertUnauthorized();
    }

    // Time of a write kept offline ----------------------------------------------------------

    public function test_a_scan_kept_offline_is_recorded_on_the_day_it_happened(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'Africa/Cairo'));
        $student = Student::factory()->create();
        Sanctum::actingAs($this->teacher());

        $this->withHeaders(['X-Offline-Replay' => '1'])->postJson('/api/attendance/scan', [
            'payload' => $student->ensureQrToken(),
            'scanned_at' => '2026-10-07T09:30:00+03:00',
        ])->assertCreated()->assertJsonPath('already_recorded', false);

        $record = AttendanceRecord::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('2026-10-07', substr((string) $record->attendance_date, 0, 10));
    }

    public function test_a_live_scan_with_an_old_device_time_is_still_dated_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'Africa/Cairo'));
        $student = Student::factory()->create();
        Sanctum::actingAs($this->teacher());

        // Without the replay marker the old 36-hour guard still applies.
        $this->postJson('/api/attendance/scan', [
            'payload' => $student->ensureQrToken(),
            'scanned_at' => '2026-10-07T09:30:00+03:00',
        ])->assertCreated();

        $record = AttendanceRecord::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('2026-10-10', substr((string) $record->attendance_date, 0, 10));
    }

    public function test_a_scan_kept_for_more_than_a_week_is_refused_not_dated_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00', 'Africa/Cairo'));
        $student = Student::factory()->create();
        Sanctum::actingAs($this->teacher());

        $this->withHeaders(['X-Offline-Replay' => '1'])->postJson('/api/attendance/scan', [
            'payload' => $student->ensureQrToken(),
            'scanned_at' => '2026-10-07T09:30:00+03:00',
        ])->assertStatus(422)->assertJsonValidationErrors('scanned_at');

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_a_payment_kept_offline_carries_the_time_it_was_taken(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'Africa/Cairo'));
        $student = Student::factory()->create();
        Sanctum::actingAs($this->teacher());

        $this->withHeaders(['X-Offline-Replay' => '1'])->postJson('/api/payments/qr-checkin', [
            'payload' => $student->ensureQrToken(),
            'amount' => 300,
            'occurred_at' => '2026-10-08T16:45:00+03:00',
        ])->assertCreated();

        $payment = Payment::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('2026-10-08', $payment->paid_at->timezone('Africa/Cairo')->toDateString());
    }

    public function test_occurred_at_is_ignored_unless_the_request_is_a_replay(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'Africa/Cairo'));
        $student = Student::factory()->create();
        Sanctum::actingAs($this->teacher());

        $this->postJson('/api/payments/qr-checkin', [
            'payload' => $student->ensureQrToken(),
            'amount' => 300,
            'occurred_at' => '2026-10-01T10:00:00+03:00',
        ])->assertCreated();

        $payment = Payment::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('2026-10-10', $payment->paid_at->timezone('Africa/Cairo')->toDateString());
    }
}
