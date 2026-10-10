<?php

namespace Modules\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Modules\Auth\Models\User;
use Modules\Students\Models\Student;
use Modules\Tenancy\Models\School;
use Modules\Tenancy\Support\SchoolChannel;
use Tests\TestCase;

/**
 * Two schools on one server must not see each other: not their rows, their
 * sign-ins, their cached values or their realtime channels.
 */
class SchoolIsolationTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('storage/framework/testing/cache'));
        parent::tearDown();
    }

    private function enter(School $school): void
    {
        tenancy()->initialize($school);
        URL::forceRootUrl("http://{$school->id}.zewal.test");
    }

    public function test_each_school_keeps_its_own_rows(): void
    {
        Student::factory()->count(3)->create();
        $beta = $this->openSchool('beta');

        $this->assertSame(0, Student::query()->count(), 'a new school starts empty');
        Student::factory()->create();
        $this->assertSame(1, Student::query()->count());

        $this->enter($this->school);
        $this->assertSame(3, Student::query()->count());
        $this->enter($beta);
        $this->assertSame(1, Student::query()->count());
    }

    public function test_a_token_from_one_school_means_nothing_in_another(): void
    {
        $token = User::factory()->create(['role' => 'admin'])->createToken('app')->plainTextToken;
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();

        $beta = $this->openSchool('beta');
        User::factory()->create(['role' => 'admin']);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();

        $this->enter($this->school);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
        $this->assertNotSame($beta->id, tenant('id'));
    }

    public function test_cached_values_are_kept_apart(): void
    {
        // The array store forgets everything when it is rebuilt, so use a real one.
        tenancy()->end();
        config(['cache.default' => 'file', 'cache.stores.file.path' => base_path('storage/framework/testing/cache')]);
        $this->enter($this->school);
        Cache::flush();
        Cache::put('report-version', 'demo-1', 600);
        $beta = $this->openSchool('beta');

        $this->assertNull(Cache::get('report-version'));
        Cache::put('report-version', 'beta-1', 600);

        $this->enter($this->school);
        $this->assertSame('demo-1', Cache::get('report-version'));
        $this->enter($beta);
        $this->assertSame('beta-1', Cache::get('report-version'));
    }

    public function test_realtime_channels_carry_the_school(): void
    {
        $this->assertSame('school.demo.', SchoolChannel::prefix());
        $this->assertSame('school.demo.user.5', SchoolChannel::name('user.5'));

        $this->openSchool('beta');
        $this->assertSame('school.beta.user.5', SchoolChannel::name('user.5'));
    }

    public function test_a_school_cannot_join_the_channels_of_another(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $rule = \Illuminate\Support\Facades\Broadcast::driver()->getChannels()['school.{school}.user.{id}'];

        $this->assertTrue((bool) $rule($admin, 'demo', $admin->id));
        $this->assertFalse((bool) $rule($admin, 'beta', $admin->id), 'the same number in another school');
    }

    public function test_an_address_that_belongs_to_no_school_is_answered(): void
    {
        URL::forceRootUrl('http://nobody.zewal.test');

        $this->getJson('/api/auth/me')->assertNotFound()->assertJsonPath('code', 'unknown_school');
    }

    public function test_while_developing_one_plain_address_can_serve_one_school(): void
    {
        tenancy()->end();
        config(['tenancy.default_school' => 'demo']);

        $this->postJson('http://localhost/api/auth/login', ['email' => 'a@b.c', 'password' => 'x'])
            ->assertStatus(422);
        $this->assertSame('demo', tenant('id'));
    }

    public function test_that_shortcut_does_nothing_in_production(): void
    {
        tenancy()->end();
        config(['tenancy.default_school' => 'demo']);
        $this->app['env'] = 'production';

        $this->postJson('http://localhost/api/auth/login', ['email' => 'a@b.c', 'password' => 'x'])
            ->assertNotFound();
    }

    public function test_the_schools_api_is_not_served_on_the_central_address(): void
    {
        $this->postJson('http://localhost/api/auth/login', ['email' => 'a@b.c', 'password' => 'x'])->assertNotFound();
    }
}
