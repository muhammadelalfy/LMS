<?php

namespace Modules\Auth\Tests\Feature;

use Modules\Auth\Models\User;
use Tests\TestCase;

class TrialAccountTest extends TestCase
{
    private function teacher(array $extra = []): User
    {
        return User::create([
            'name' => 'معلّم تجريبي', 'email' => 't'.uniqid().'@mudarisy.com',
            'password' => 'Secret-pass-1', 'role' => 'teacher',
        ] + $extra);
    }

    public function test_an_active_trial_account_signs_in(): void
    {
        $user = $this->teacher(['expires_at' => now()->addDay()]);
        $this->postJson('/api/auth/teacher/login', ['email' => $user->email, 'password' => 'Secret-pass-1'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_a_switched_off_or_expired_account_cannot_sign_in(): void
    {
        $off = $this->teacher(['disabled_at' => now()]);
        $old = $this->teacher(['expires_at' => now()->subMinute()]);
        foreach ([$off, $old] as $user) {
            $this->postJson('/api/auth/teacher/login', ['email' => $user->email, 'password' => 'Secret-pass-1'])
                ->assertForbidden();
        }
    }

    public function test_switching_an_account_off_ends_its_open_session(): void
    {
        $user = $this->teacher();
        $token = $user->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();

        $user->forceFill(['disabled_at' => now()])->save();
        app('auth')->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
