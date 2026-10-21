<?php

namespace Modules\Calls\Tests\Feature;

use Modules\Calls\Events\CallEnded;
use Modules\Calls\Events\CallInvited;
use Modules\Calls\Models\Call;
use Modules\Calls\Models\CallParticipant;
use Modules\Groups\Models\ClassGroup;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentAccount;
use Modules\Auth\Models\User;
use Modules\Calls\Services\CallService;
use Modules\Calls\Services\Jwt;
use Modules\Notifications\Services\FcmPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $sara;

    private User $ali;

    private ClassGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.livekit.url' => 'wss://media.example.com',
            'services.livekit.key' => 'lk-key',
            'services.livekit.secret' => 'lk-secret-that-is-long-enough-for-hs256',
        ]);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'name' => 'أ. سمير']);
        $this->group = ClassGroup::create(['name' => 'أ', 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1], 'late_after_minutes' => 10, 'teacher_id' => $this->teacher->id]);
        $this->sara = $this->learner('أ', 'سارة');
        $this->ali = $this->learner('أ', 'علي');
    }

    private function learner(string $group, string $name): User
    {
        $student = Student::factory()->create(['group' => $group, 'name' => $name]);
        $user = User::factory()->create(['role' => 'student', 'name' => $name]);
        StudentAccount::create(['user_id' => $user->id, 'student_id' => $student->id, 'relationship' => 'student']);

        return $user;
    }

    private function directWithSara(): int
    {
        Sanctum::actingAs($this->teacher);

        return $this->postJson('/api/conversations', ['user_id' => $this->sara->id])->json('id');
    }

    private function classChannel(): int
    {
        Sanctum::actingAs($this->teacher);

        return $this->postJson('/api/conversations', ['group_id' => $this->group->id])->json('id');
    }

    private function claims(string $token): array
    {
        return Jwt::decode($token, config('services.livekit.secret'));
    }

    public function test_calling_rings_the_other_side_and_gives_the_caller_a_scoped_token(): void
    {
        Event::fake([CallInvited::class]);
        $pushed = [];
        $this->mock(FcmPushSender::class, function ($mock) use (&$pushed) {
            $mock->shouldReceive('send')->andReturnUsing(function ($users, $title, $body, $data) use (&$pushed) {
                $pushed[] = [$users->pluck('id')->all(), $title, $data['type'] ?? null];
            });
        });
        $conversation = $this->directWithSara();

        $response = $this->postJson('/api/calls', ['conversation_id' => $conversation, 'media' => 'video'])
            ->assertCreated()->assertJsonPath('call.state', 'ringing')->assertJsonPath('call.media', 'video')
            ->assertJsonPath('url', 'wss://media.example.com');

        $claims = $this->claims($response->json('token'));
        $room = Call::first()->room;
        $this->assertSame('lk-key', $claims['iss']);
        $this->assertSame((string) $this->teacher->id, $claims['sub']);
        $this->assertSame($room, $claims['video']['room']);
        $this->assertTrue($claims['video']['roomJoin']);
        $this->assertLessThanOrEqual(660, $claims['exp'] - time(), 'tokens live for minutes only');

        Event::assertDispatched(CallInvited::class, fn ($event) => $event->userId === $this->sara->id
            && $event->call['starter_name'] === 'أ. سمير');
        $this->assertSame([[[$this->sara->id], 'مكالمة من أ. سمير', 'call_invite']], $pushed);
    }

    public function test_the_callee_answers_and_the_call_becomes_active_with_its_own_token(): void
    {
        $conversation = $this->directWithSara();
        $callId = $this->postJson('/api/calls', ['conversation_id' => $conversation, 'media' => 'audio'])->json('call.id');

        Sanctum::actingAs($this->sara);
        $join = $this->postJson("/api/calls/{$callId}/join")->assertOk()->assertJsonPath('call.state', 'active');
        $this->assertSame((string) $this->sara->id, $this->claims($join->json('token'))['sub']);
        $this->assertNotNull(Call::first()->answered_at);
    }

    public function test_starting_twice_in_one_conversation_reuses_the_live_call(): void
    {
        $conversation = $this->directWithSara();
        $first = $this->postJson('/api/calls', ['conversation_id' => $conversation, 'media' => 'audio'])->json('call.id');
        $second = $this->postJson('/api/calls', ['conversation_id' => $conversation, 'media' => 'audio'])->json('call.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Call::count());
    }

    public function test_declining_a_direct_call_ends_it_and_tells_the_caller(): void
    {
        Event::fake([CallInvited::class, CallEnded::class]);
        $conversation = $this->directWithSara();
        $callId = $this->postJson('/api/calls', ['conversation_id' => $conversation, 'media' => 'audio'])->json('call.id');

        Sanctum::actingAs($this->sara);
        $this->postJson("/api/calls/{$callId}/decline")->assertNoContent();

        $this->assertSame('declined', Call::first()->state);
        Event::assertDispatched(CallEnded::class, fn ($event) => $event->userId === $this->teacher->id && $event->call['state'] === 'declined');
        $this->postJson("/api/calls/{$callId}/join")->assertStatus(409);
    }

    public function test_leaving_a_direct_call_ends_it_for_both(): void
    {
        $conversation = $this->directWithSara();
        $callId = $this->postJson('/api/calls', ['conversation_id' => $conversation, 'media' => 'audio'])->json('call.id');
        Sanctum::actingAs($this->sara);
        $this->postJson("/api/calls/{$callId}/join")->assertOk();

        $this->postJson("/api/calls/{$callId}/leave")->assertNoContent();

        $call = Call::first();
        $this->assertSame('ended', $call->state);
        $this->assertNotNull($call->ended_at);
    }

    public function test_an_unanswered_call_becomes_missed_and_the_callee_is_told(): void
    {
        $conversation = $this->directWithSara();
        $this->postJson('/api/calls', ['conversation_id' => $conversation, 'media' => 'audio'])->assertCreated();

        $this->assertSame(0, app(CallService::class)->expireUnanswered(), 'still ringing');
        $this->travel(CallService::RING_SECONDS + 5)->seconds();
        $this->assertSame(1, app(CallService::class)->expireUnanswered());
        $this->assertSame(0, app(CallService::class)->expireUnanswered(), 'only once');

        $this->assertSame('missed', Call::first()->state);
        $this->assertSame('missed', CallParticipant::where('user_id', $this->sara->id)->value('state'));
        $note = $this->sara->notifications()->first();
        $this->assertSame('مكالمة فائتة', $note->data['title']);

        Sanctum::actingAs($this->sara);
        $this->getJson('/api/calls')->assertOk()
            ->assertJsonPath('data.0.missed', true)->assertJsonPath('data.0.direction', 'incoming');
    }

    public function test_strangers_and_parents_cannot_call_or_join(): void
    {
        $conversation = $this->directWithSara();
        $callId = $this->postJson('/api/calls', ['conversation_id' => $conversation, 'media' => 'audio'])->json('call.id');

        Sanctum::actingAs($this->ali);
        $this->postJson('/api/calls', ['conversation_id' => $conversation, 'media' => 'audio'])->assertForbidden();
        $this->postJson("/api/calls/{$callId}/join")->assertForbidden();
    }

    public function test_calls_are_off_until_the_media_server_is_configured(): void
    {
        $conversation = $this->directWithSara();
        $this->getJson('/api/calls/status')->assertOk()->assertJson(['enabled' => true]);

        config(['services.livekit.url' => null]);
        $this->getJson('/api/calls/status')->assertOk()->assertJson(['enabled' => false]);
        $this->postJson('/api/calls', ['conversation_id' => $conversation, 'media' => 'audio'])->assertStatus(503);
    }

    // Online class -------------------------------------------------------------------------

    public function test_the_teacher_starts_a_class_room_and_students_join_as_listeners(): void
    {
        Event::fake([CallInvited::class]);
        $channel = $this->classChannel();

        $start = $this->postJson('/api/calls', ['conversation_id' => $channel, 'media' => 'video'])->assertCreated()
            ->assertJsonPath('call.class_room', true)->assertJsonPath('can_publish', true);
        $callId = $start->json('call.id');
        $this->assertTrue($this->claims($start->json('token'))['video']['canPublish']);
        Event::assertDispatched(CallInvited::class, 2); // both students

        Sanctum::actingAs($this->sara);
        $join = $this->postJson("/api/calls/{$callId}/join")->assertOk()->assertJsonPath('can_publish', false);
        $this->assertFalse($this->claims($join->json('token'))['video']['canPublish'], 'students listen until the teacher lets them speak');
        $this->assertTrue($this->claims($join->json('token'))['video']['canPublishData'], 'but can raise a hand');

        // A student cannot start the class, a leaver does not end it for others.
        $this->postJson('/api/calls', ['conversation_id' => $channel, 'media' => 'video'])->assertOk()->assertJsonPath('call.id', $callId);
        $this->postJson("/api/calls/{$callId}/leave")->assertNoContent();
        $this->assertSame('active', Call::first()->state);
    }

    public function test_only_the_teacher_runs_a_class_room(): void
    {
        $channel = $this->classChannel();
        Sanctum::actingAs($this->sara);

        $this->postJson('/api/calls', ['conversation_id' => $channel, 'media' => 'video'])->assertForbidden();
    }

    public function test_the_teacher_lets_a_student_speak_removes_one_and_ends_the_class(): void
    {
        Http::fake(['media.example.com/*' => Http::response([])]);
        config(['services.livekit.url' => 'wss://media.example.com']);
        $channel = $this->classChannel();
        $callId = $this->postJson('/api/calls', ['conversation_id' => $channel, 'media' => 'video'])->json('call.id');
        Sanctum::actingAs($this->sara);
        $this->postJson("/api/calls/{$callId}/join")->assertOk();

        $this->postJson("/api/calls/{$callId}/participants/{$this->ali->id}/speak", ['allowed' => true])->assertForbidden();

        Sanctum::actingAs($this->teacher);
        $this->postJson("/api/calls/{$callId}/participants/{$this->sara->id}/speak", ['allowed' => true])->assertNoContent();
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/twirp/livekit.RoomService/UpdateParticipant')
            && $request->url() === 'https://media.example.com/twirp/livekit.RoomService/UpdateParticipant'
            && $request['identity'] === (string) $this->sara->id
            && $request['permission']['can_publish'] === true
            && Jwt::decode($request->header('Authorization')[0] ? substr($request->header('Authorization')[0], 7) : '', config('services.livekit.secret'))['video']['roomAdmin'] === true);

        $this->deleteJson("/api/calls/{$callId}/participants/{$this->sara->id}")->assertNoContent();
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/RemoveParticipant'));

        $this->postJson("/api/calls/{$callId}/end")->assertNoContent();
        $this->assertSame('ended', Call::first()->state);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/DeleteRoom'));

        Sanctum::actingAs($this->sara);
        $this->postJson("/api/calls/{$callId}/end")->assertForbidden();
    }

    // LiveKit webhooks ---------------------------------------------------------------------

    private function webhook(array $event, ?string $authorization = null)
    {
        $body = json_encode($event);
        $authorization ??= Jwt::encode([
            'iss' => 'lk-key', 'nbf' => time(), 'exp' => time() + 300, 'sha256' => base64_encode(hash('sha256', $body, true)),
        ], config('services.livekit.secret'));

        return $this->call('POST', 'http://localhost/api/webhooks/livekit', [], [], [], [
            'HTTP_AUTHORIZATION' => $authorization, 'CONTENT_TYPE' => 'application/webhook+json',
        ], $body);
    }

    public function test_livekit_reports_joins_leaves_and_the_room_closing(): void
    {
        $conversation = $this->directWithSara();
        $callId = $this->postJson('/api/calls', ['conversation_id' => $conversation, 'media' => 'audio'])->json('call.id');
        $room = Call::first()->room;

        $this->webhook(['event' => 'participant_joined', 'room' => ['name' => $room], 'participant' => ['identity' => (string) $this->sara->id]])->assertOk();
        $this->assertSame('joined', CallParticipant::where(['call_id' => $callId, 'user_id' => $this->sara->id])->value('state'));

        $this->webhook(['event' => 'participant_left', 'room' => ['name' => $room], 'participant' => ['identity' => (string) $this->sara->id]])->assertOk();
        $this->assertSame('left', CallParticipant::where(['call_id' => $callId, 'user_id' => $this->sara->id])->value('state'));

        $this->webhook(['event' => 'room_finished', 'room' => ['name' => $room]])->assertOk();
        $this->assertSame('ended', Call::first()->state);
    }

    public function test_a_webhook_needs_a_valid_signature_for_exactly_this_body(): void
    {
        $event = ['event' => 'room_finished', 'room' => ['name' => 'x']];

        $this->webhook($event, 'not-a-jwt')->assertForbidden();
        $other = Jwt::encode(['iss' => 'lk-key', 'exp' => time() + 300, 'sha256' => base64_encode(hash('sha256', 'another body', true))], config('services.livekit.secret'));
        $this->webhook($event, $other)->assertForbidden();
        $wrongKey = Jwt::encode(['iss' => 'lk-key', 'exp' => time() + 300, 'sha256' => base64_encode(hash('sha256', json_encode($event), true))], 'a-different-secret');
        $this->webhook($event, $wrongKey)->assertForbidden();

        config(['services.livekit.url' => null]);
        $this->webhook($event)->assertNotFound();
    }

    public function test_tokens_expire_and_cannot_be_forged(): void
    {
        $token = Jwt::encode(['exp' => time() - 1], 'secret');
        $this->assertNull(Jwt::decode($token, 'secret'));
        $this->assertNull(Jwt::decode(Jwt::encode(['exp' => time() + 60], 'secret'), 'other'));
        $this->assertSame(['exp' => $exp = time() + 60], Jwt::decode(Jwt::encode(['exp' => $exp], 'secret'), 'secret'));
    }
}
