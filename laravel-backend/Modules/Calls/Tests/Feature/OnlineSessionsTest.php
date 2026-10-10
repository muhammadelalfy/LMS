<?php

namespace Modules\Calls\Tests\Feature;

use Modules\Groups\Models\ClassGroup;
use Modules\Calls\Models\OnlineSession;
use Modules\Calls\Models\ProviderAccount;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentAccount;
use Modules\Auth\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnlineSessionsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $sara;

    private ClassGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00', 'Africa/Cairo'));
        config([
            'services.google.client_id' => 'g-id', 'services.google.client_secret' => 'g-secret', 'services.google.redirect' => 'https://api.example.com/api/integrations/google/callback',
            'services.zoom.client_id' => 'z-id', 'services.zoom.client_secret' => 'z-secret', 'services.zoom.redirect' => 'https://api.example.com/api/integrations/zoom/callback',
        ]);
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->group = ClassGroup::create(['name' => 'أ', 'start_time' => '15:00', 'end_time' => '16:30', 'days' => [1], 'late_after_minutes' => 10, 'teacher_id' => $this->teacher->id]);
        $student = Student::factory()->create(['group' => 'أ', 'name' => 'سارة']);
        $this->sara = User::factory()->create(['role' => 'student']);
        StudentAccount::create(['user_id' => $this->sara->id, 'student_id' => $student->id, 'relationship' => 'student']);
    }

    // Pasting a link ---------------------------------------------------------------------------

    public function test_a_teacher_sets_how_the_group_meets_and_students_read_it(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->putJson("/api/groups/{$this->group->id}/online-session", ['provider' => 'meet', 'link' => 'https://meet.google.com/abc-defg-hij'])
            ->assertOk()->assertJsonPath('session.provider', 'meet')->assertJsonPath('session.day', '2026-10-05');
        $this->putJson("/api/groups/{$this->group->id}/online-session", ['provider' => 'zoom', 'link' => 'https://us02web.zoom.us/j/123456789?pwd=x'])
            ->assertOk()->assertJsonPath('session.provider', 'zoom');
        $this->assertSame(1, OnlineSession::count(), 'one per group per day: the last choice wins');

        Sanctum::actingAs($this->sara);
        $this->getJson("/api/groups/{$this->group->id}/online-session")->assertOk()
            ->assertJsonPath('session.link', 'https://us02web.zoom.us/j/123456789?pwd=x');
        $this->getJson("/api/groups/{$this->group->id}/online-session?day=2026-10-12")->assertOk()->assertJsonPath('session', null);

        Sanctum::actingAs($this->teacher);
        $this->deleteJson("/api/groups/{$this->group->id}/online-session")->assertNoContent();
        $this->assertSame(0, OnlineSession::count());
    }

    public function test_the_built_in_app_needs_no_link_and_a_wrong_link_is_refused(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->putJson("/api/groups/{$this->group->id}/online-session", ['provider' => 'builtin'])
            ->assertOk()->assertJsonPath('session.link', null);

        foreach ([
            ['meet', 'https://zoom.us/j/1'], ['zoom', 'https://meet.google.com/abc'],
            ['meet', 'http://meet.google.com/abc'], ['zoom', 'https://evil.com/?zoom.us'],
            ['zoom', 'https://notzoom.us/j/1'], ['meet', ''],
        ] as [$provider, $link]) {
            $this->putJson("/api/groups/{$this->group->id}/online-session", ['provider' => $provider, 'link' => $link])
                ->assertStatus(422)->assertJsonValidationErrors('link');
        }
    }

    public function test_only_staff_of_the_group_change_it_and_only_its_members_read_it(): void
    {
        Sanctum::actingAs($this->sara);
        $this->putJson("/api/groups/{$this->group->id}/online-session", ['provider' => 'builtin'])->assertForbidden();

        $other = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($other);
        $this->putJson("/api/groups/{$this->group->id}/online-session", ['provider' => 'builtin'])->assertForbidden();
        $this->getJson("/api/groups/{$this->group->id}/online-session")->assertForbidden();

        $stranger = User::factory()->create(['role' => 'parent']);
        Sanctum::actingAs($stranger);
        $this->getJson("/api/groups/{$this->group->id}/online-session")->assertForbidden();
    }

    // Connecting an account ------------------------------------------------------------------

    public function test_connecting_google_stores_encrypted_tokens_and_reports_status(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'acc-1', 'refresh_token' => 'ref-1', 'expires_in' => 3600])]);
        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/integrations')->assertOk()
            ->assertJsonPath('data.0', ['provider' => 'google', 'available' => true, 'connected' => false]);

        $url = $this->postJson('/api/integrations/google/connect')->assertOk()->json('url');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('g-id', $query['client_id']);
        $this->assertStringContainsString('calendar.events', $query['scope']);

        // The provider sends the browser back to us (no bearer token there).
        $this->app['auth']->forgetGuards();
        $this->get('http://localhost/api/integrations/google/callback?code=abc&state='.urlencode($query['state']))
            ->assertOk()->assertSee('تم ربط الحساب');

        $account = ProviderAccount::first();
        $this->assertSame($this->teacher->id, $account->user_id);
        $this->assertSame('acc-1', $account->access_token);
        $this->assertStringNotContainsString('acc-1', (string) \DB::table('provider_accounts')->value('access_token'), 'encrypted at rest');
        Http::assertSent(fn ($request) => $request['grant_type'] === 'authorization_code' && $request['code'] === 'abc');

        Sanctum::actingAs($this->teacher);
        $this->getJson('/api/integrations')->assertJsonPath('data.0.connected', true);
        $this->deleteJson('/api/integrations/google')->assertNoContent();
        $this->assertSame(0, ProviderAccount::count());
    }

    public function test_a_forged_or_expired_connection_request_is_refused(): void
    {
        $this->get('http://localhost/api/integrations/google/callback?code=abc&state=forged')->assertForbidden();

        $expired = Crypt::encryptString(json_encode(['school' => 'demo', 'user' => $this->teacher->id, 'provider' => 'google', 'exp' => time() - 1]));
        $this->get('http://localhost/api/integrations/google/callback?code=abc&state='.urlencode($expired))->assertForbidden();

        $wrongProvider = Crypt::encryptString(json_encode(['school' => 'demo', 'user' => $this->teacher->id, 'provider' => 'zoom', 'exp' => time() + 600]));
        $this->get('http://localhost/api/integrations/google/callback?code=abc&state='.urlencode($wrongProvider))->assertForbidden();
        $this->assertSame(0, ProviderAccount::count());
    }

    public function test_a_provider_without_credentials_is_unavailable_and_learners_cannot_connect(): void
    {
        config(['services.zoom.client_id' => null]);
        Sanctum::actingAs($this->teacher);
        $this->getJson('/api/integrations')->assertJsonPath('data.1', ['provider' => 'zoom', 'available' => false, 'connected' => false]);
        $this->postJson('/api/integrations/zoom/connect')->assertStatus(503);

        Sanctum::actingAs($this->sara);
        $this->postJson('/api/integrations/google/connect')->assertForbidden();
    }

    // Creating the meeting ----------------------------------------------------------------------

    public function test_a_google_meet_is_created_in_the_teachers_calendar(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['hangoutLink' => 'https://meet.google.com/new-meet-link'])]);
        ProviderAccount::create(['user_id' => $this->teacher->id, 'provider' => 'google', 'access_token' => 'acc', 'refresh_token' => 'ref', 'expires_at' => now()->addHour()]);
        Sanctum::actingAs($this->teacher);

        $this->putJson("/api/groups/{$this->group->id}/online-session", ['provider' => 'meet', 'create' => true])
            ->assertOk()->assertJsonPath('session.link', 'https://meet.google.com/new-meet-link');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'calendars/primary/events?conferenceDataVersion=1')
            && $request->hasHeader('Authorization', 'Bearer acc')
            && $request['summary'] === 'حصة أ'
            && str_starts_with($request['start']['dateTime'], '2026-10-05T15:00:00')
            && str_starts_with($request['end']['dateTime'], '2026-10-05T16:30:00')
            && $request['conferenceData']['createRequest']['conferenceSolutionKey']['type'] === 'hangoutsMeet');
    }

    public function test_a_zoom_meeting_is_created_and_an_expired_token_is_refreshed_first(): void
    {
        Http::fake([
            'zoom.us/oauth/token' => Http::response(['access_token' => 'new-acc', 'refresh_token' => 'new-ref', 'expires_in' => 3600]),
            'api.zoom.us/*' => Http::response(['join_url' => 'https://us02web.zoom.us/j/999']),
        ]);
        ProviderAccount::create(['user_id' => $this->teacher->id, 'provider' => 'zoom', 'access_token' => 'old', 'refresh_token' => 'ref', 'expires_at' => now()->subMinute()]);
        Sanctum::actingAs($this->teacher);

        $this->putJson("/api/groups/{$this->group->id}/online-session", ['provider' => 'zoom', 'create' => true])
            ->assertOk()->assertJsonPath('session.link', 'https://us02web.zoom.us/j/999');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'zoom.us/oauth/token') && $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'ref');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.zoom.us/v2/users/me/meetings'
            && $request->hasHeader('Authorization', 'Bearer new-acc') && $request['duration'] === 90 && $request['topic'] === 'حصة أ');
        $this->assertSame('new-ref', ProviderAccount::first()->refresh_token);
    }

    public function test_creating_without_a_connected_account_or_when_the_provider_fails_says_what_to_do(): void
    {
        Sanctum::actingAs($this->teacher);
        $this->putJson("/api/groups/{$this->group->id}/online-session", ['provider' => 'meet', 'create' => true])
            ->assertStatus(422)->assertJsonPath('message', 'اربط حسابك أولاً، أو الصق رابط الاجتماع.');

        Http::fake(['www.googleapis.com/*' => Http::response([], 500)]);
        ProviderAccount::create(['user_id' => $this->teacher->id, 'provider' => 'google', 'access_token' => 'acc', 'expires_at' => now()->addHour()]);
        $this->putJson("/api/groups/{$this->group->id}/online-session", ['provider' => 'meet', 'create' => true])
            ->assertStatus(422)->assertJsonPath('message', 'تعذر إنشاء الاجتماع الآن. الصق رابطاً بدلاً من ذلك.');
        $this->assertSame(0, OnlineSession::count());
    }

    // The reminder carries the way in ----------------------------------------------------------

    public function test_the_session_reminder_tells_students_and_teachers_how_to_join(): void
    {
        OnlineSession::create(['group_id' => $this->group->id, 'day' => '2026-10-05', 'provider' => 'zoom', 'link' => 'https://us02web.zoom.us/j/999', 'created_by' => $this->teacher->id]);
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/attendance/session-reminders', ['now' => '2026-10-05T14:35:00+03:00'])->assertOk()->assertJsonPath('reminded', 1);

        $this->assertStringContainsString('الحصة عبر Zoom. رابط الانضمام: https://us02web.zoom.us/j/999', $this->sara->notifications()->first()->data['body']);
        $this->assertStringContainsString('رابط الانضمام', $this->teacher->notifications()->first()->data['body']);

        $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00:00', 'Africa/Cairo'));
        OnlineSession::query()->delete();
        OnlineSession::create(['group_id' => $this->group->id, 'day' => '2026-10-12', 'provider' => 'builtin', 'created_by' => $this->teacher->id]);
        $this->postJson('/api/attendance/session-reminders', ['now' => '2026-10-12T14:40:00+03:00'])->assertOk()->assertJsonPath('reminded', 1);
        $this->assertStringContainsString('افتح التطبيق واضغط انضمام', $this->sara->notifications()->latest('created_at')->get()->first()->data['body']);
    }
}
