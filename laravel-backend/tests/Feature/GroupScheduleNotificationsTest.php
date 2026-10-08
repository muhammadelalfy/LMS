<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\ClassGroup;
use App\Models\DeviceToken;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupScheduleNotificationsTest extends TestCase
{
    use RefreshDatabase;

    /** A Monday afternoon in the academy's time zone. */
    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monday = CarbonImmutable::parse('2026-10-05 15:00:00', 'Africa/Cairo');
        $this->travelTo($this->monday);
    }

    private function student(string $group = 'أ'): array
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
        return ClassGroup::create([
            'name' => 'أ', 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1, 3], 'late_after_minutes' => 10,
        ]);
    }

    public function test_staff_manage_group_timetables_and_learners_cannot(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $id = $this->postJson('/api/groups', [
            'name' => 'أ', 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1, 3],
        ])->assertCreated()->assertJsonPath('late_after_minutes', 10)->json('id');
        $this->postJson('/api/groups', ['name' => 'ب', 'start_time' => '16:00', 'end_time' => '15:00', 'days' => [1]])
            ->assertStatus(422)->assertJsonValidationErrors('end_time');
        $this->putJson("/api/groups/{$id}", ['name' => 'أ', 'start_time' => '14:00', 'end_time' => '15:30', 'days' => [2]])
            ->assertOk()->assertJsonPath('start_time', '14:00');
        $this->getJson('/api/groups')->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs(User::factory()->create(['role' => 'parent']));
        $this->getJson('/api/groups')->assertForbidden();
    }

    public function test_scans_use_the_device_clock_against_the_group_timetable(): void
    {
        $this->groupA();
        [$onTime] = $this->student();
        [$late] = $this->student();
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $this->postJson('/api/attendance/scan', ['payload' => $onTime->ensureQrToken(), 'scanned_at' => '2026-10-05T15:08:00+03:00'])
            ->assertCreated()->assertJsonPath('attendance.status', 'present');
        $this->postJson('/api/attendance/scan', ['payload' => $late->ensureQrToken(), 'scanned_at' => '2026-10-05T15:25:00+03:00'])
            ->assertCreated()->assertJsonPath('attendance.status', 'late');
        $this->assertSame('2026-10-05', AttendanceRecord::query()->where('student_id', $late->id)->value('attendance_date'));
    }

    public function test_arriving_outside_the_group_time_asks_for_confirmation_before_recording(): void
    {
        $this->groupA(); // Mon/Wed 15:00-16:30
        [$student] = $this->student();
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $payload = $student->ensureQrToken();

        // Monday 17:00 is after the session: nothing is recorded yet.
        $this->postJson('/api/attendance/scan', ['payload' => $payload, 'scanned_at' => '2026-10-05T17:00:00+03:00'])
            ->assertOk()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('reason', 'outside_session')
            ->assertJsonPath('student.name', $student->name)
            ->assertJsonPath('group.name', 'أ')
            ->assertJsonPath('group.start_time', '15:00');
        $this->assertSame(0, AttendanceRecord::query()->where('student_id', $student->id)->count());

        // Tuesday has no lesson: also outside, and still nothing recorded.
        $this->postJson('/api/attendance/scan', ['payload' => $payload, 'scanned_at' => '2026-10-06T15:30:00+03:00'])
            ->assertOk()->assertJsonPath('requires_confirmation', true);
        $this->assertSame(0, AttendanceRecord::query()->where('student_id', $student->id)->count());

        // Staff confirm on the phone: recorded as present, with a note.
        $this->postJson('/api/attendance/scan', [
            'payload' => $payload, 'scanned_at' => '2026-10-05T17:00:00+03:00', 'confirm_outside_session' => true,
        ])->assertCreated()
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonPath('attendance.note', 'حضور خارج موعد المجموعة');

        // Once recorded the day is settled; no further question is asked.
        $this->postJson('/api/attendance/scan', ['payload' => $payload, 'scanned_at' => '2026-10-05T17:05:00+03:00'])
            ->assertOk()->assertJsonPath('already_recorded', true);
    }

    public function test_early_arrival_up_to_thirty_minutes_before_the_start_is_accepted(): void
    {
        $this->groupA();
        [$early] = $this->student();
        [$tooEarly] = $this->student();
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $this->postJson('/api/attendance/scan', ['payload' => $early->ensureQrToken(), 'scanned_at' => '2026-10-05T14:30:00+03:00'])
            ->assertCreated()->assertJsonPath('attendance.status', 'present');
        $this->postJson('/api/attendance/scan', ['payload' => $tooEarly->ensureQrToken(), 'scanned_at' => '2026-10-05T14:29:00+03:00'])
            ->assertOk()->assertJsonPath('requires_confirmation', true);
    }

    public function test_students_without_a_known_group_are_never_questioned(): void
    {
        [$student] = $this->student('بدون جدول');
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $this->postJson('/api/attendance/scan', ['payload' => $student->ensureQrToken(), 'scanned_at' => '2026-10-05T23:00:00+03:00'])
            ->assertCreated()->assertJsonMissingPath('requires_confirmation');
    }

    public function test_absences_are_recorded_after_the_session_and_parents_alerted_once(): void
    {
        $this->groupA();
        [$present] = $this->student();
        [$absent, $parent, $learner] = $this->student();
        $teacher = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($teacher);
        $this->postJson('/api/attendance/scan', ['payload' => $present->ensureQrToken(), 'scanned_at' => '2026-10-05T15:02:00+03:00']);

        // Before the end of the session nobody is marked absent.
        $this->postJson('/api/attendance/sweep', ['now' => '2026-10-05T16:00:00+03:00'])->assertJsonPath('marked_absent', 0);

        $this->postJson('/api/attendance/sweep', ['now' => '2026-10-05T16:31:00+03:00'])->assertJsonPath('marked_absent', 1);
        $this->postJson('/api/attendance/sweep', ['now' => '2026-10-05T16:45:00+03:00'])->assertJsonPath('marked_absent', 0);

        $record = AttendanceRecord::query()->where('student_id', $absent->id)->firstOrFail();
        $this->assertSame('absent', $record->status);
        $this->assertNull($record->recorded_by);
        $this->assertSame(1, $parent->notifications()->count());
        $this->assertSame('absence', $parent->notifications()->first()->data['category']);
        $this->assertSame(0, $learner->notifications()->count());
    }

    public function test_the_scheduler_command_sweeps_on_server_time(): void
    {
        $this->groupA();
        [$absent] = $this->student();
        $this->travelTo($this->monday->setTime(17, 0));

        $this->artisan('attendance:sweep-absences')->assertSuccessful();

        $this->assertSame('absent', AttendanceRecord::query()->where('student_id', $absent->id)->value('status'));
    }

    public function test_staff_send_messages_to_students_parents_or_a_whole_group(): void
    {
        [$first, $firstParent, $firstLearner] = $this->student('أ');
        [, $secondParent, $secondLearner] = $this->student('أ');
        [, $otherParent] = $this->student('ب');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/notifications/send', ['title' => 'تذكير', 'body' => 'موعد الاختبار غداً.', 'audience' => 'parents', 'group' => 'أ'])
            ->assertOk()->assertJsonPath('students', 2)->assertJsonPath('recipients', 2);
        $this->postJson('/api/notifications/send', ['title' => 'مرحباً', 'body' => 'أحسنت!', 'audience' => 'students', 'student_ids' => [$first->id]])
            ->assertOk()->assertJsonPath('recipients', 1);
        $this->postJson('/api/notifications/send', ['title' => 'عطلة', 'body' => 'لا دروس يوم الجمعة.', 'audience' => 'both', 'all' => true])
            ->assertOk()->assertJsonPath('recipients', 6);
        $this->postJson('/api/notifications/send', ['title' => 'x', 'body' => 'y', 'audience' => 'both'])->assertStatus(422);

        $this->assertSame(2, $firstParent->notifications()->count());
        $this->assertSame(2, $firstLearner->notifications()->count());
        $this->assertSame(2, $secondParent->notifications()->count());
        $this->assertSame(1, $secondLearner->notifications()->count());
        $this->assertSame(1, $otherParent->notifications()->count());

        Sanctum::actingAs($firstParent);
        $this->postJson('/api/notifications/send', ['title' => 'x', 'body' => 'y', 'audience' => 'both', 'all' => true])->assertForbidden();
    }

    public function test_registered_devices_receive_push_when_firebase_is_configured(): void
    {
        [$student, $parent] = $this->student();
        Sanctum::actingAs($parent);
        $this->postJson('/api/devices', ['token' => 'device-token-1', 'platform' => 'android'])->assertOk();
        $this->assertSame(1, DeviceToken::query()->count());

        // A throwaway signing key. Windows PHP needs its bundled openssl.cnf.
        $options = ['private_key_bits' => 2048];
        $bundled = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';
        if (! getenv('OPENSSL_CONF') && is_readable($bundled)) {
            $options['config'] = $bundled;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $privateKey, null, $options);
        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, json_encode(['project_id' => 'zewal-test', 'client_email' => 'push@zewal-test.iam', 'private_key' => $privateKey]));
        config(['services.fcm.credentials' => $path]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'access']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/zewal-test/messages/1']),
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));
        $this->postJson('/api/notifications/send', ['title' => 'تذكير', 'body' => 'نص', 'audience' => 'parents', 'student_ids' => [$student->id]])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'projects/zewal-test/messages:send')
            && $request['message']['token'] === 'device-token-1'
            && $request['message']['notification']['title'] === 'تذكير');
        unlink($path);
    }
}
