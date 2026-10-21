<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\User;
use Modules\Auth\Services\DemoAccounts;
use Modules\Students\Models\Student;
use Tests\TestCase;

/** The "try it" sign-in: off unless the operator turned it on, and never a way in by password. */
class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    private function demoOn(): void
    {
        tenant()->demo_logins = true;
        app(DemoAccounts::class)->ensure();
    }

    public function test_it_is_off_by_default_and_offers_nothing(): void
    {
        User::factory()->create(['role' => 'admin']);

        $this->getJson('/api/auth/options')->assertOk()->assertJsonPath('demo_logins', []);
        $this->postJson('/api/auth/demo-login', ['role' => 'admin'])->assertNotFound();
    }

    public function test_even_with_demo_accounts_present_it_stays_closed_until_switched_on(): void
    {
        app(DemoAccounts::class)->ensure();

        $this->getJson('/api/auth/options')->assertJsonPath('demo_logins', []);
        $this->postJson('/api/auth/demo-login', ['role' => 'teacher'])->assertNotFound();
    }

    public function test_every_role_can_be_tried_and_the_session_ends_on_its_own(): void
    {
        $this->demoOn();
        $this->getJson('/api/auth/options')->assertJsonPath('demo_logins', ['admin', 'teacher', 'parent', 'student']);

        foreach (['admin', 'teacher', 'parent', 'student'] as $role) {
            $response = $this->postJson('/api/auth/demo-login', ['role' => $role])->assertOk()
                ->assertJsonPath('user.role', $role)->assertJsonPath('user.is_demo', true)->assertJsonPath('login_type', $role);

            $token = PersonalAccessToken::findToken($response->json('token'));
            $this->assertNotNull($token->expires_at);
            $this->assertTrue($token->expires_at->between(now()->addHours(7), now()->addHours(9)));
        }
    }

    public function test_the_parent_and_the_student_are_linked_to_a_student_so_their_pages_have_data(): void
    {
        Student::factory()->create(['name' => 'علي']);
        $this->demoOn();

        foreach (['parent', 'student'] as $role) {
            $this->postJson('/api/auth/demo-login', ['role' => $role])->assertOk()
                ->assertJsonPath('user.student_account.student.name', 'علي');
        }
    }

    public function test_a_school_with_no_students_gets_one_for_the_demo(): void
    {
        $this->demoOn();

        $this->assertSame(['طالب تجريبي'], Student::pluck('name')->all());
    }

    public function test_it_never_signs_in_as_a_real_account(): void
    {
        $real = User::factory()->create(['role' => 'admin', 'email' => 'owner@school.test']);
        $this->demoOn();

        $user = $this->postJson('/api/auth/demo-login', ['role' => 'admin'])->assertOk()->json('user');

        $this->assertNotSame($real->id, $user['id']);
        $this->assertSame('demo-admin@demo.invalid', $user['email']);
    }

    public function test_the_demo_accounts_cannot_be_reached_with_a_password(): void
    {
        $this->demoOn();

        foreach (['/api/auth/login', '/api/auth/admin/login', '/api/auth/teacher/login'] as $path) {
            foreach (['password', 'demo', '', 'Demo!2026'] as $guess) {
                $this->postJson($path, ['email' => 'demo-admin@demo.invalid', 'password' => $guess])->assertStatus($guess === '' ? 422 : 422);
            }
        }
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_an_unknown_role_is_refused_and_a_switched_off_account_is_not_offered(): void
    {
        $this->demoOn();
        $this->postJson('/api/auth/demo-login', ['role' => 'owner'])->assertUnprocessable();
        $this->postJson('/api/auth/demo-login', [])->assertUnprocessable();

        app(DemoAccounts::class)->userFor('teacher')->forceFill(['disabled_at' => now()])->save();
        $this->getJson('/api/auth/options')->assertJsonPath('demo_logins', ['admin', 'parent', 'student']);
        $this->postJson('/api/auth/demo-login', ['role' => 'teacher'])->assertNotFound();
    }

    public function test_enabling_twice_makes_no_duplicates(): void
    {
        $this->demoOn();
        app(DemoAccounts::class)->ensure();

        $this->assertSame(4, User::where('is_demo', true)->count());
        $this->assertSame(1, Student::count());
    }

    public function test_a_demo_account_cannot_manage_accounts_or_change_its_own_sign_in_details(): void
    {
        $this->demoOn();
        $real = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs(app(DemoAccounts::class)->userFor('admin'));

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', ['name' => 'x', 'email' => 'x@x.test', 'password' => 'Teacher#2026pw', 'role' => 'admin'])->assertForbidden();
        $this->postJson("/api/users/{$real->id}/disable")->assertForbidden();
        $this->postJson("/api/users/{$real->id}/reset-password")->assertForbidden();
        $this->patchJson("/api/users/{$real->id}/role", ['role' => 'admin'])->assertForbidden();
        $this->getJson('/api/audit-logs')->assertForbidden();
        $this->putJson('/api/auth/profile', ['name' => 'x', 'email' => 'changed@x.test'])->assertForbidden();
        $this->postJson('/api/auth/password', ['current_password' => 'x', 'password' => 'Teacher#2026pw', 'password_confirmation' => 'Teacher#2026pw'])->assertForbidden();
        $this->assertSame('teacher', $real->fresh()->role);
        $this->assertNull($real->fresh()->disabled_at);
    }

    public function test_it_is_rate_limited(): void
    {
        $this->demoOn();
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/auth/demo-login', ['role' => 'student'])->assertOk();
        }

        $this->postJson('/api/auth/demo-login', ['role' => 'student'])->assertStatus(429);
    }
}
