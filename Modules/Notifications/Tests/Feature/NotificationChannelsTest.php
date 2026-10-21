<?php

namespace Modules\Notifications\Tests\Feature;

use Modules\Notifications\Jobs\SendChannelDelivery;
use Modules\Notifications\Models\ContactChannel;
use Modules\Notifications\Models\NotificationDelivery;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentAccount;
use Modules\Auth\Models\User;
use Modules\Notifications\Services\FcmPushSender;
use Modules\Notifications\Services\ChannelRegistry;
use Modules\Notifications\Services\StudentNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whatsapp-app-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.sms.url' => 'https://sms.example/send',
            'services.sms.token' => 'sms-token',
            'services.sms.webhook_secret' => 'sms-secret',
            'services.whatsapp.token' => 'wa-token',
            'services.whatsapp.phone_number_id' => '1234',
            'services.whatsapp.graph_url' => 'https://graph.example/v20.0',
            'services.whatsapp.app_secret' => self::SECRET,
            'services.whatsapp.verify_token' => 'verify-me',
        ]);
    }

    /** A student with a parent account that has both paid channels switched on. */
    private function parentOf(string $phone = '+201012345678'): array
    {
        $student = Student::factory()->create(['group' => 'أ']);
        $parent = User::factory()->create(['role' => 'parent']);
        StudentAccount::create(['user_id' => $parent->id, 'student_id' => $student->id, 'relationship' => 'parent']);
        foreach (['whatsapp', 'sms'] as $channel) {
            ContactChannel::create([
                'user_id' => $parent->id, 'channel' => $channel, 'address' => $phone,
                'verified_at' => now(), 'opted_in_at' => now(), 'source' => 'app',
            ]);
        }

        return [$student, $parent];
    }

    private function alert(Student $student, string $category = 'absence'): void
    {
        app(StudentNotifier::class)->notify([$student->id], 'تنبيه غياب', "تغيّب {$student->name} اليوم.", $category, ['parent']);
    }

    private function deliver(string $channel, NotificationDelivery $delivery): void
    {
        (new SendChannelDelivery($delivery->id, $channel))->handle(app(ChannelRegistry::class));
    }

    private function delivery(User $user, string $channel): NotificationDelivery
    {
        return NotificationDelivery::query()->where(['user_id' => $user->id, 'channel' => $channel])->firstOrFail();
    }

    private function whatsappHook(array $payload)
    {
        $body = json_encode($payload);

        return $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'HTTP_X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, self::SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function statusReport(string $id, string $status): array
    {
        return ['entry' => [['changes' => [['value' => ['statuses' => [['id' => $id, 'status' => $status]]]]]]]];
    }

    // Phone numbers ----------------------------------------------------------

    public function test_a_phone_is_normalised_and_proved_by_a_code(): void
    {
        Http::fake(['sms.example/*' => Http::response(['id' => 'm1'])]);
        $user = User::factory()->create(['role' => 'parent']);
        Sanctum::actingAs($user);

        $this->postJson('/api/me/phone', ['phone' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->postJson('/api/me/phone', ['phone' => '010 1234 5678'])->assertOk()->assertJsonPath('sent', true);

        $sent = Http::recorded()[0][0]->data();
        $this->assertSame('+201012345678', $sent['to']);
        preg_match('/\d{6}/', $sent['message'], $match);
        $code = $match[0];

        $this->postJson('/api/me/phone/verify', ['code' => $code === '000000' ? '111111' : '000000'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson('/api/me/phone/verify', ['code' => $code])
            ->assertOk()->assertJsonPath('verified', true)->assertJsonPath('phone', '+20••••••5678');

        foreach (['sms', 'whatsapp'] as $channel) {
            $contact = ContactChannel::where(['user_id' => $user->id, 'channel' => $channel])->first();
            $this->assertNotNull($contact->verified_at);
            $this->assertFalse($contact->canReceive(), 'verified, but not switched on yet');
        }
    }

    public function test_a_code_stops_working_after_too_many_wrong_tries_or_when_it_expires(): void
    {
        Http::fake(['sms.example/*' => Http::response(['id' => 'm1'])]);
        Sanctum::actingAs(User::factory()->create(['role' => 'parent']));
        $this->postJson('/api/me/phone', ['phone' => '01012345678'])->assertOk();
        preg_match('/\d{6}/', Http::recorded()[0][0]->data()['message'], $match);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/me/phone/verify', ['code' => '000000'])->assertStatus(422);
        }
        $this->postJson('/api/me/phone/verify', ['code' => $match[0]])->assertStatus(422);

        $this->travel(11)->minutes();
        $this->postJson('/api/me/phone', ['phone' => '01012345678'])->assertOk();
        preg_match('/\d{6}/', Http::recorded()[1][0]->data()['message'], $fresh);
        $this->travel(11)->minutes();
        $this->postJson('/api/me/phone/verify', ['code' => $fresh[0]])->assertStatus(422);
    }

    // Preferences ------------------------------------------------------------

    public function test_channels_need_a_verified_phone_and_critical_push_stays_on(): void
    {
        $user = User::factory()->create(['role' => 'parent']);
        Sanctum::actingAs($user);

        $this->getJson('/api/me/notification-preferences')
            ->assertOk()->assertJsonPath('phone', null)->assertJsonPath('channels.whatsapp', false)
            ->assertJsonPath('categories.0.key', 'absence')->assertJsonPath('categories.0.critical', true);

        $this->putJson('/api/me/notification-preferences', ['channels' => ['whatsapp' => true]])
            ->assertStatus(422)->assertJsonValidationErrors('channels');

        $this->putJson('/api/me/notification-preferences', ['categories' => [['key' => 'absence', 'push' => false]]])
            ->assertStatus(422)->assertJsonValidationErrors('categories');

        ContactChannel::create(['user_id' => $user->id, 'channel' => 'whatsapp', 'address' => '+201012345678', 'verified_at' => now()]);
        $this->putJson('/api/me/notification-preferences', [
            'channels' => ['whatsapp' => true],
            'quiet_hours' => ['from' => '22:00', 'to' => '07:00'],
            'categories' => [['key' => 'duty', 'push' => false, 'whatsapp' => false]],
        ])->assertOk()
            ->assertJsonPath('channels.whatsapp', true)
            ->assertJsonPath('quiet_hours.from', '22:00')
            ->assertJsonPath('categories.3.key', 'duty')
            ->assertJsonPath('categories.3.push', false)
            ->assertJsonPath('categories.3.whatsapp', false);

        $this->putJson('/api/me/notification-preferences', ['quiet_hours' => null])
            ->assertOk()->assertJsonPath('quiet_hours', null);
    }

    // The fallback chain -----------------------------------------------------

    public function test_an_absence_alert_queues_whatsapp_then_sms_after_their_waits(): void
    {
        Queue::fake();
        [$student, $parent] = $this->parentOf();

        $this->alert($student);

        $this->assertSame(1, $parent->notifications()->count(), 'the inbox always gets it');
        Queue::assertPushed(SendChannelDelivery::class, 2);
        $this->assertEqualsWithDelta(10, now()->diffInMinutes($this->delivery($parent, 'whatsapp')->send_after, true), 1);
        $this->assertEqualsWithDelta(30, now()->diffInMinutes($this->delivery($parent, 'sms')->send_after, true), 1);
        $this->assertSame('queued', $this->delivery($parent, 'whatsapp')->state);
    }

    public function test_whatsapp_sends_the_template_then_provider_reports_move_it_forward_only(): void
    {
        Queue::fake();
        Http::fake(['graph.example/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        [$student, $parent] = $this->parentOf();
        $this->alert($student);

        $this->deliver('whatsapp', $delivery = $this->delivery($parent, 'whatsapp'));

        Http::assertSent(fn ($request) => $request->url() === 'https://graph.example/v20.0/1234/messages'
            && $request['to'] === '201012345678'
            && $request['template']['name'] === 'school_update'
            && $request['template']['components'][0]['parameters'][0]['text'] === 'تنبيه غياب');
        $delivery->refresh();
        $this->assertSame('sent', $delivery->state);
        $this->assertSame('wamid.1', $delivery->provider_id);

        $this->whatsappHook($this->statusReport('wamid.1', 'read'))->assertOk();
        $this->assertSame('read', $delivery->fresh()->state);
        $this->whatsappHook($this->statusReport('wamid.1', 'delivered'))->assertOk();
        $this->assertSame('read', $delivery->fresh()->state, 'a late report never undoes read');
    }

    public function test_a_failed_report_is_recorded_with_its_reason(): void
    {
        Queue::fake();
        Http::fake(['graph.example/*' => Http::response(['messages' => [['id' => 'wamid.2']]])]);
        [$student, $parent] = $this->parentOf();
        $this->alert($student);
        $this->deliver('whatsapp', $delivery = $this->delivery($parent, 'whatsapp'));

        $payload = ['entry' => [['changes' => [['value' => ['statuses' => [
            ['id' => 'wamid.2', 'status' => 'failed', 'errors' => [['code' => 131026]]],
        ]]]]]]];
        $this->whatsappHook($payload)->assertOk();

        $this->assertSame('failed', $delivery->fresh()->state);
        $this->assertSame('provider_131026', $delivery->fresh()->reason);
    }

    public function test_a_notice_already_read_in_the_app_is_not_sent_again_on_a_paid_channel(): void
    {
        Queue::fake();
        Http::fake();
        [$student, $parent] = $this->parentOf();
        $this->alert($student);

        $parent->notifications()->first()->markAsRead();
        $this->deliver('sms', $delivery = $this->delivery($parent, 'sms'));

        $this->assertSame('skipped', $delivery->fresh()->state);
        $this->assertSame('already_read', $delivery->fresh()->reason);
        Http::assertNothingSent();
    }

    public function test_running_a_delivery_twice_sends_once(): void
    {
        Queue::fake();
        Http::fake(['graph.example/*' => Http::response(['messages' => [['id' => 'wamid.3']]])]);
        [$student, $parent] = $this->parentOf();
        $this->alert($student);
        $delivery = $this->delivery($parent, 'whatsapp');

        $this->deliver('whatsapp', $delivery);
        $this->deliver('whatsapp', $delivery);

        Http::assertSentCount(1);
    }

    public function test_a_gateway_that_is_down_is_retried_and_a_rejection_is_not(): void
    {
        Queue::fake();
        Http::fake(['sms.example/*' => Http::sequence()->push([], 503)->push([], 400)]);
        [$student, $first] = $this->parentOf('+201011111111');
        [$other, $second] = $this->parentOf('+201022222222');
        $this->alert($student);
        $this->alert($other);

        $job = (new SendChannelDelivery($this->delivery($first, 'sms')->id, 'sms'))->withFakeQueueInteractions();
        $job->handle(app(ChannelRegistry::class));
        $job->assertReleased();
        $this->assertSame('queued', $this->delivery($first, 'sms')->state);
        $this->assertSame(1, $this->delivery($first, 'sms')->attempts);

        $this->deliver('sms', $rejected = $this->delivery($second, 'sms'));
        $this->assertSame('failed', $rejected->fresh()->state);
        $this->assertSame('http_400', $rejected->fresh()->reason);
    }

    public function test_the_daily_cap_stops_paid_sends_for_the_rest_of_the_day(): void
    {
        Queue::fake();
        Http::fake(['graph.example/*' => Http::response(['messages' => [['id' => 'wamid.4']]])]);
        config(['notifications.daily_caps.whatsapp' => 1]);
        [$student, $first] = $this->parentOf('+201011111111');
        [$other, $second] = $this->parentOf('+201022222222');
        $this->alert($student);
        $this->alert($other);

        $this->deliver('whatsapp', $this->delivery($first, 'whatsapp'));
        $this->deliver('whatsapp', $this->delivery($second, 'whatsapp'));

        $this->assertSame('sent', $this->delivery($first, 'whatsapp')->state);
        $this->assertSame('skipped', $this->delivery($second, 'whatsapp')->state);
        $this->assertSame('cap', $this->delivery($second, 'whatsapp')->reason);
    }

    public function test_quiet_hours_hold_a_normal_notice_but_not_a_critical_one(): void
    {
        Queue::fake();
        Http::fake(['graph.example/*' => Http::response(['messages' => [['id' => 'wamid.5']]])]);
        config(['notifications.chains.duty' => [['whatsapp', 0]]]);
        [$student, $parent] = $this->parentOf();
        $this->actingAs($parent);
        $this->putJson('/api/me/notification-preferences', ['quiet_hours' => [
            'from' => now()->subHour()->format('H:i'), 'to' => now()->addHour()->format('H:i'),
        ]])->assertOk();

        $this->alert($student, 'duty');
        $job = (new SendChannelDelivery($this->delivery($parent, 'whatsapp')->id, 'whatsapp'))->withFakeQueueInteractions();
        $job->handle(app(ChannelRegistry::class));
        $job->assertReleased();
        $this->assertSame('queued', $this->delivery($parent, 'whatsapp')->state);
        Http::assertNothingSent();

        NotificationDelivery::query()->delete();
        $this->alert($student, 'absence');
        $this->deliver('whatsapp', $critical = $this->delivery($parent, 'whatsapp'));
        $this->assertSame('sent', $critical->fresh()->state);
    }

    public function test_only_people_who_switched_a_channel_on_and_kept_the_category_get_it(): void
    {
        Queue::fake();
        [$student, $parent] = $this->parentOf();
        ContactChannel::where(['user_id' => $parent->id, 'channel' => 'sms'])->update(['opted_in_at' => null]);
        $this->actingAs($parent);
        $this->putJson('/api/me/notification-preferences', ['categories' => [['key' => 'absence', 'whatsapp' => false]]])->assertOk();

        $this->alert($student);

        $this->assertSame(0, NotificationDelivery::count());
        Queue::assertNothingPushed();
    }

    // Reaching people ---------------------------------------------------------

    public function test_a_stop_reply_switches_the_channel_off_and_later_notices_skip_it(): void
    {
        Queue::fake();
        [$student, $parent] = $this->parentOf('+201012345678');
        $reply = ['entry' => [['changes' => [['value' => ['messages' => [
            ['from' => '201012345678', 'text' => ['body' => ' STOP ']],
        ]]]]]]];

        $this->whatsappHook($reply)->assertOk();

        $contact = ContactChannel::where(['user_id' => $parent->id, 'channel' => 'whatsapp'])->first();
        $this->assertNotNull($contact->opted_out_at);
        $this->assertSame('stop-reply', $contact->source);
        $this->assertNotNull(ContactChannel::where(['user_id' => $parent->id, 'channel' => 'sms'])->first()->opted_in_at);

        $this->alert($student);
        $this->assertNull(NotificationDelivery::where('channel', 'whatsapp')->first());
        $this->assertNotNull(NotificationDelivery::where('channel', 'sms')->first());
    }

    public function test_sms_reports_and_replies_need_the_shared_secret(): void
    {
        Queue::fake();
        Http::fake(['sms.example/*' => Http::response(['id' => 'sms-1'])]);
        [$student, $parent] = $this->parentOf();
        $this->alert($student);
        $this->deliver('sms', $delivery = $this->delivery($parent, 'sms'));

        $this->postJson('/api/webhooks/sms', ['id' => 'sms-1', 'status' => 'delivered'])->assertForbidden();
        $this->postJson('/api/webhooks/sms', ['id' => 'sms-1', 'status' => 'delivered'], ['X-Webhook-Secret' => 'sms-secret'])->assertOk();
        $this->assertSame('delivered', $delivery->fresh()->state);

        $this->postJson('/api/webhooks/sms', ['from' => '+201012345678', 'message' => 'إيقاف'], ['X-Webhook-Secret' => 'sms-secret'])->assertOk();
        $this->assertNotNull(ContactChannel::where(['user_id' => $parent->id, 'channel' => 'sms'])->first()->opted_out_at);
    }

    public function test_whatsapp_webhooks_prove_themselves(): void
    {
        $this->getJson('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=4242')
            ->assertOk()->assertSee('4242');
        $this->getJson('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=4242')->assertForbidden();

        $this->postJson('/api/webhooks/whatsapp', $this->statusReport('x', 'read'), ['X-Hub-Signature-256' => 'sha256=bad'])
            ->assertForbidden();

        config(['services.whatsapp.app_secret' => null]);
        $this->postJson('/api/webhooks/whatsapp', $this->statusReport('x', 'read'))->assertNotFound();
    }

    public function test_push_can_be_turned_off_for_an_ordinary_category_only(): void
    {
        Queue::fake();
        [$student, $parent] = $this->parentOf();
        $this->actingAs($parent);
        $this->putJson('/api/me/notification-preferences', ['categories' => [['key' => 'duty', 'push' => false]]])->assertOk();

        $recipients = [];
        $this->mock(FcmPushSender::class, function ($mock) use (&$recipients) {
            $mock->shouldReceive('send')->andReturnUsing(function ($users) use (&$recipients) {
                $recipients[] = $users->count();
            });
        });

        $this->alert($student, 'duty');
        $this->alert($student, 'absence');

        $this->assertSame([0, 1], $recipients, 'duty push is off; the critical absence push is not');
        $this->assertSame(2, $parent->notifications()->count(), 'the inbox is never turned off');
    }
}
