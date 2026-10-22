<?php

declare(strict_types=1);

namespace Modules\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Modules\Auth\Models\User;
use Modules\Tenancy\Models\School;
use Tests\TestCase;

/** The operator decides which schools show "try it" sign-in buttons. */
class DemoLoginSwitchTest extends TestCase
{
    private const CENTRAL = 'http://localhost/api/central';

    private function operator(): static
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        config(['tenancy.operator_token' => 'op-secret']);

        return $this->withToken('op-secret');
    }

    /** Looks at the school the way its own visitors do. */
    private function asVisitor(string $path, string $method = 'getJson', array $body = []): TestResponse
    {
        tenancy()->initialize(School::findOrFail('demo'));
        URL::forceRootUrl('http://demo.mudarisy.test');

        return $this->flushHeaders()->{$method}($path, $body);
    }

    public function test_a_school_starts_without_demo_logins(): void
    {
        $this->operator()->getJson(self::CENTRAL.'/schools/demo')->assertOk()->assertJsonPath('demo_logins', false);
        $this->asVisitor('/api/auth/options')->assertJsonPath('demo_logins', []);
    }

    public function test_the_operator_switches_it_on_and_the_demo_accounts_appear_in_that_school_only(): void
    {
        $this->operator()->patchJson(self::CENTRAL.'/schools/demo', ['demo_logins' => true])->assertOk()->assertJsonPath('demo_logins', true);

        $this->asVisitor('/api/auth/options')->assertJsonPath('demo_logins', ['admin', 'teacher', 'parent', 'student']);
        $this->asVisitor('/api/auth/demo-login', 'postJson', ['role' => 'parent'])->assertOk()->assertJsonPath('user.role', 'parent');
        $this->assertSame(4, User::where('is_demo', true)->count());

        // Another school is untouched.
        $beta = $this->openSchool('beta');
        $this->assertSame(0, User::where('is_demo', true)->count());
        $this->assertFalse((bool) $beta->fresh()->demo_logins);
    }

    public function test_switching_it_off_closes_the_buttons_again_and_it_can_be_switched_on_again(): void
    {
        $this->operator()->patchJson(self::CENTRAL.'/schools/demo', ['demo_logins' => true])->assertOk();
        $this->operator()->patchJson(self::CENTRAL.'/schools/demo', ['demo_logins' => false])->assertOk()->assertJsonPath('demo_logins', false);

        $this->asVisitor('/api/auth/options')->assertJsonPath('demo_logins', []);
        $this->asVisitor('/api/auth/demo-login', 'postJson', ['role' => 'admin'])->assertNotFound();

        $this->operator()->patchJson(self::CENTRAL.'/schools/demo', ['demo_logins' => true])->assertOk();
        $this->assertSame(4, School::findOrFail('demo')->run(fn () => User::where('is_demo', true)->count()));
    }

    public function test_only_the_operator_can_switch_it(): void
    {
        $this->operator();
        $this->withToken('wrong')->patchJson(self::CENTRAL.'/schools/demo', ['demo_logins' => true])->assertUnauthorized();
        $this->operator()->patchJson(self::CENTRAL.'/schools/demo', ['demo_logins' => 'maybe'])->assertUnprocessable();
    }
}
