<?php

namespace Modules\Chat\Tests\Feature;

use Modules\Chat\Events\ConversationOpened;
use Modules\Chat\Events\MessageDeleted;
use Modules\Chat\Events\MessageSent;
use Modules\Chat\Models\ChatAuditLog;
use Modules\Groups\Models\ClassGroup;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentAccount;
use Modules\Auth\Models\User;
use Modules\Notifications\Services\FcmPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $otherTeacher;

    /** The teacher's group ("أ") and another teacher's ("ب"). */
    private ClassGroup $groupA;

    private ClassGroup $groupB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'المدير']);
        $this->teacher = User::factory()->create(['role' => 'teacher', 'name' => 'أ. سمير']);
        $this->otherTeacher = User::factory()->create(['role' => 'teacher', 'name' => 'أ. منى']);
        $this->groupA = ClassGroup::create(['name' => 'أ', 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1], 'late_after_minutes' => 10, 'teacher_id' => $this->teacher->id]);
        $this->groupB = ClassGroup::create(['name' => 'ب', 'start_time' => '17:00', 'end_time' => '18:30', 'days' => [1], 'late_after_minutes' => 10, 'teacher_id' => $this->otherTeacher->id]);
    }

    private function learner(string $group, string $name): User
    {
        $student = Student::factory()->create(['group' => $group, 'name' => $name]);
        $user = User::factory()->create(['role' => 'student', 'name' => $name]);
        StudentAccount::create(['user_id' => $user->id, 'student_id' => $student->id, 'relationship' => 'student']);

        return $user;
    }

    private function parentOf(string $group): User
    {
        $student = Student::factory()->create(['group' => $group]);
        $parent = User::factory()->create(['role' => 'parent']);
        StudentAccount::create(['user_id' => $parent->id, 'student_id' => $student->id, 'relationship' => 'parent']);

        return $parent;
    }

    private function open(User $as, User $with): int
    {
        Sanctum::actingAs($as);

        return $this->postJson('/api/conversations', ['user_id' => $with->id])->assertCreated()->json('id');
    }

    private function say(User $as, int $conversation, string $body, ?string $clientId = null)
    {
        Sanctum::actingAs($as);

        return $this->postJson("/api/conversations/{$conversation}/messages", ['body' => $body, 'client_id' => $clientId]);
    }

    // Who may talk to whom ----------------------------------------------------

    public function test_management_and_teachers_and_a_teacher_with_their_own_students_may_chat(): void
    {
        $mine = $this->learner('أ', 'سارة');

        $this->open($this->admin, $this->teacher);
        $this->open($this->teacher, $this->admin);
        $this->open($this->teacher, $mine);
        $this->open($mine, $this->teacher);

        $this->assertSame(2, Conversation::count(), 'each pair has one conversation, however it is opened');
    }

    public function test_nobody_else_can_start_a_conversation(): void
    {
        $mine = $this->learner('أ', 'سارة');
        $theirs = $this->learner('ب', 'علي');
        $parent = $this->parentOf('أ');

        foreach ([
            [$this->teacher, $theirs], // another teacher's student
            [$this->teacher, $this->otherTeacher],
            [$this->admin, $mine], // management does not message students
            [$mine, $theirs], // students among themselves
            [$mine, $parent],
            [$parent, $this->teacher],
            [$mine, $this->admin],
            [$mine, $this->otherTeacher],
        ] as [$from, $to]) {
            Sanctum::actingAs($from);
            $this->postJson('/api/conversations', ['user_id' => $to->id])->assertForbidden();
        }
        $this->assertSame(0, Conversation::count());
    }

    public function test_a_group_with_no_teacher_named_is_open_to_every_teacher(): void
    {
        $this->groupB->update(['teacher_id' => null]);
        $theirs = $this->learner('ب', 'علي');

        $this->open($this->teacher, $theirs);
        $this->open($this->otherTeacher, $theirs);

        $this->assertSame(2, Conversation::count());
    }

    public function test_contacts_list_only_who_each_person_may_message(): void
    {
        $mine = $this->learner('أ', 'سارة');
        $this->learner('ب', 'علي');

        Sanctum::actingAs($this->teacher);
        $names = collect($this->getJson('/api/chat/contacts')->json('data'))->pluck('name')->all();
        $this->assertSame(['المدير', 'سارة'], $names);

        Sanctum::actingAs($mine);
        $this->assertSame(['أ. سمير'], collect($this->getJson('/api/chat/contacts')->json('data'))->pluck('name')->all());

        Sanctum::actingAs($this->admin);
        $this->assertSame(['أ. سمير', 'أ. منى'], collect($this->getJson('/api/chat/contacts')->json('data'))->pluck('name')->all());

        Sanctum::actingAs($this->parentOf('أ'));
        $this->assertSame([], $this->getJson('/api/chat/contacts')->json('data'));
    }

    // Messages ----------------------------------------------------------------

    public function test_a_message_is_stored_broadcast_and_read_by_the_other_side(): void
    {
        Event::fake([MessageSent::class, ConversationOpened::class]);
        $mine = $this->learner('أ', 'سارة');
        $conversation = $this->open($this->teacher, $mine);
        Event::assertDispatched(ConversationOpened::class, fn ($event) => $event->userId === $mine->id);

        $this->say($this->teacher, $conversation, 'مرحباً سارة', 'c-1')->assertCreated()
            ->assertJsonPath('body', 'مرحباً سارة')->assertJsonPath('sender_name', 'أ. سمير');
        Event::assertDispatched(MessageSent::class, fn ($event) => $event->message->body === 'مرحباً سارة'
            && $event->broadcastOn()[0]->name === 'private-school.demo.conversation.'.$conversation);

        Sanctum::actingAs($mine);
        $list = $this->getJson('/api/conversations')->assertOk()->json('data');
        $this->assertSame(1, $list[0]['unread']);
        $this->assertSame('أ. سمير', $list[0]['title']);
        $this->assertSame('مرحباً سارة', $list[0]['last_message']['body']);

        $this->getJson("/api/conversations/{$conversation}/messages")->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/conversations/{$conversation}/read", ['message_id' => Message::first()->id])->assertNoContent();
        $this->assertSame(0, $this->getJson('/api/conversations')->json('data.0.unread'));

        Sanctum::actingAs($this->teacher);
        $this->assertSame(0, $this->getJson('/api/conversations')->json('data.0.unread'), 'your own message is not unread');
    }

    public function test_sending_again_with_the_same_client_id_stores_one_message(): void
    {
        Event::fake([MessageSent::class]);
        $conversation = $this->open($this->admin, $this->teacher);

        $first = $this->say($this->admin, $conversation, 'اجتماع غداً', 'same')->assertCreated()->json('id');
        $again = $this->say($this->admin, $conversation, 'اجتماع غداً', 'same')->assertOk()->json('id');

        $this->assertSame($first, $again);
        $this->assertSame(1, Message::count());
        Event::assertDispatchedTimes(MessageSent::class, 1);
    }

    public function test_history_pages_both_ways_oldest_first(): void
    {
        $conversation = $this->open($this->admin, $this->teacher);
        foreach (range(1, 7) as $i) {
            $this->say($this->admin, $conversation, "رسالة {$i}");
        }
        $ids = Message::orderBy('id')->pluck('id')->all();

        $newest = $this->getJson("/api/conversations/{$conversation}/messages?limit=3")->assertOk();
        $this->assertSame(['رسالة 5', 'رسالة 6', 'رسالة 7'], collect($newest->json('data'))->pluck('body')->all());
        $this->assertTrue($newest->json('has_more'));

        $older = $this->getJson("/api/conversations/{$conversation}/messages?limit=3&before_id={$ids[4]}")->assertOk();
        $this->assertSame(['رسالة 2', 'رسالة 3', 'رسالة 4'], collect($older->json('data'))->pluck('body')->all());

        $since = $this->getJson("/api/conversations/{$conversation}/messages?after_id={$ids[4]}")->assertOk();
        $this->assertSame(['رسالة 6', 'رسالة 7'], collect($since->json('data'))->pluck('body')->all());
        $this->assertFalse($since->json('has_more'));
    }

    public function test_only_people_in_a_conversation_can_read_or_write_in_it(): void
    {
        $mine = $this->learner('أ', 'سارة');
        $conversation = $this->open($this->teacher, $mine);
        $this->say($this->teacher, $conversation, 'سر');

        foreach ([$this->otherTeacher, $this->learner('ب', 'علي'), $this->parentOf('أ')] as $stranger) {
            Sanctum::actingAs($stranger);
            $this->getJson("/api/conversations/{$conversation}/messages")->assertForbidden();
            $this->postJson("/api/conversations/{$conversation}/messages", ['body' => 'هاي'])->assertForbidden();
        }
        Sanctum::actingAs($this->admin);
        $this->getJson("/api/conversations/{$conversation}/messages")->assertForbidden();
    }

    public function test_a_blank_or_oversized_message_is_refused(): void
    {
        $conversation = $this->open($this->admin, $this->teacher);

        $this->say($this->admin, $conversation, '   ')->assertStatus(422);
        $this->say($this->admin, $conversation, str_repeat('ا', 4001))->assertStatus(422);
    }

    // Safety --------------------------------------------------------------------

    public function test_blocking_stops_messages_both_ways_and_unblocking_restores_them(): void
    {
        $mine = $this->learner('أ', 'سارة');
        $conversation = $this->open($this->teacher, $mine);

        Sanctum::actingAs($mine);
        $this->postJson("/api/users/{$this->teacher->id}/block")->assertNoContent();
        $this->say($this->teacher, $conversation, 'هاي')->assertForbidden();
        $this->say($mine, $conversation, 'هاي')->assertForbidden();

        Sanctum::actingAs($mine);
        $this->deleteJson("/api/users/{$this->teacher->id}/block")->assertNoContent();
        $this->say($this->teacher, $conversation, 'هاي')->assertCreated();
    }

    public function test_reports_and_management_oversight_are_audited(): void
    {
        $mine = $this->learner('أ', 'سارة');
        $conversation = $this->open($this->teacher, $mine);
        $id = $this->say($this->teacher, $conversation, 'رسالة مشكوك فيها')->json('id');

        Sanctum::actingAs($mine);
        $this->postJson("/api/messages/{$id}/report", ['reason' => 'مزعجة'])->assertCreated();
        $this->postJson("/api/messages/{$id}/report", ['reason' => 'مزعجة'])->assertCreated();
        $this->assertSame(1, \Modules\Chat\Models\MessageReport::count(), 'one report per person per message');

        $this->getJson('/api/admin/conversations')->assertForbidden();
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/conversations')->assertOk()
            ->assertJsonPath('data.0.title', 'أ. سمير ↔ سارة')->assertJsonPath('data.0.reports', 1);
        $this->getJson("/api/admin/conversations/{$conversation}/messages")->assertOk()
            ->assertJsonPath('data.0.body', 'رسالة مشكوك فيها');
        $this->assertSame(1, ChatAuditLog::where(['staff_id' => $this->admin->id, 'conversation_id' => $conversation])->count());
    }

    public function test_a_message_can_be_deleted_by_its_sender_or_management_and_keeps_its_place(): void
    {
        Event::fake([MessageDeleted::class, MessageSent::class]);
        $mine = $this->learner('أ', 'سارة');
        $conversation = $this->open($this->teacher, $mine);
        $id = $this->say($mine, $conversation, 'غلطة')->json('id');

        Sanctum::actingAs($this->teacher);
        $this->deleteJson("/api/messages/{$id}")->assertForbidden();
        Sanctum::actingAs($mine);
        $this->deleteJson("/api/messages/{$id}")->assertNoContent();
        Event::assertDispatched(MessageDeleted::class, fn ($event) => $event->messageId === $id);

        $row = $this->getJson("/api/conversations/{$conversation}/messages")->json('data.0');
        $this->assertTrue($row['deleted']);
        $this->assertSame('', $row['body']);
    }

    // Group channels ------------------------------------------------------------

    public function test_a_group_channel_has_the_teacher_management_and_the_groups_students_only(): void
    {
        $sara = $this->learner('أ', 'سارة');
        $ali = $this->learner('ب', 'علي');

        Sanctum::actingAs($this->teacher);
        $channel = $this->postJson('/api/conversations', ['group_id' => $this->groupA->id])->assertCreated()
            ->assertJsonPath('title', 'أ')->json('id');
        $this->postJson('/api/conversations', ['group_id' => $this->groupB->id])->assertForbidden();

        $this->say($this->teacher, $channel, 'واجب اليوم صفحة ٤٢')->assertCreated();
        $this->say($sara, $channel, 'تمام يا أستاذ')->assertCreated();
        $this->say($ali, $channel, 'هاي')->assertForbidden();
        $this->say($this->otherTeacher, $channel, 'هاي')->assertForbidden();
        $this->say($this->admin, $channel, 'مرحباً بالجميع')->assertCreated();

        Sanctum::actingAs($sara);
        $this->assertSame(['أ'], collect($this->getJson('/api/conversations')->json('data'))->pluck('title')->all());
        Sanctum::actingAs($ali);
        $this->assertSame([], $this->getJson('/api/conversations')->json('data'));
    }

    public function test_announcement_only_channels_silence_students_but_not_staff(): void
    {
        $sara = $this->learner('أ', 'سارة');
        Sanctum::actingAs($this->teacher);
        $channel = $this->postJson('/api/conversations', ['group_id' => $this->groupA->id])->json('id');

        $this->putJson("/api/conversations/{$channel}", ['announce_only' => true])->assertOk()->assertJsonPath('announce_only', true);
        $this->say($this->teacher, $channel, 'إعلان')->assertCreated();
        $this->say($sara, $channel, 'سؤال')->assertForbidden();

        Sanctum::actingAs($sara);
        $this->putJson("/api/conversations/{$channel}", ['announce_only' => false])->assertForbidden();
        $this->assertFalse($this->getJson('/api/conversations')->json('data.0.can_post'));
    }

    // Reaching offline people ------------------------------------------------------

    public function test_the_other_side_is_pushed_but_not_the_sender_and_not_when_muted_push_is_off(): void
    {
        $mine = $this->learner('أ', 'سارة');
        $conversation = $this->open($this->teacher, $mine);
        $pushed = [];
        $this->mock(FcmPushSender::class, function ($mock) use (&$pushed) {
            $mock->shouldReceive('send')->andReturnUsing(function ($users, $title, $body, $data) use (&$pushed) {
                $pushed[] = [$users->pluck('id')->all(), $title, $body, $data['conversation_id'] ?? null];
            });
        });

        $this->say($this->teacher, $conversation, 'هل حللتِ الواجب؟');
        $this->assertSame([[[$mine->id], 'أ. سمير', 'هل حللتِ الواجب؟', (string) $conversation]], $pushed);

        // The student turns chat push off: nothing is sent to them.
        Sanctum::actingAs($mine);
        $this->putJson('/api/me/notification-preferences', ['categories' => [['key' => 'chat', 'push' => false]]])->assertOk();
        $pushed = [];
        $this->say($this->teacher, $conversation, 'ردّي لو سمحتِ');
        $this->assertSame([[[], 'أ. سمير', 'ردّي لو سمحتِ', (string) $conversation]], $pushed);
    }

    // Realtime -----------------------------------------------------------------------

    public function test_private_channels_admit_only_those_the_chat_rules_admit(): void
    {
        $mine = $this->learner('أ', 'سارة');
        $conversation = $this->open($this->teacher, $mine);
        $channels = \Illuminate\Support\Facades\Broadcast::driver()->getChannels();

        $conversationRule = $channels['school.{school}.conversation.{id}'];
        foreach ([[$mine, true], [$this->teacher, true], [$this->otherTeacher, false], [$this->admin, false]] as [$user, $allowed]) {
            $this->assertSame($allowed, (bool) $conversationRule($user, 'demo', $conversation), $user->name);
        }
        $this->assertFalse((bool) $conversationRule($mine, 'demo', 999999), 'a conversation that does not exist');

        $userRule = $channels['school.{school}.user.{id}'];
        $this->assertTrue((bool) $userRule($mine, 'demo', $mine->id));
        $this->assertFalse((bool) $userRule($mine, 'demo', $this->teacher->id));

        // The app signs in to these with its Sanctum token, never anonymously.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-school.demo.user.1'])->assertUnauthorized();
    }

    public function test_the_realtime_config_tells_the_app_where_to_connect(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'app-key',
            'broadcasting.connections.reverb.options' => ['host' => 'ws.example.com', 'port' => 443, 'scheme' => 'https'],
        ]);
        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/realtime/config')->assertOk()
            ->assertJson(['enabled' => true, 'key' => 'app-key', 'host' => 'ws.example.com', 'port' => 443, 'tls' => true]);

        config(['broadcasting.default' => 'log']);
        $this->getJson('/api/realtime/config')->assertOk()->assertJson(['enabled' => false, 'key' => null]);
    }
}
