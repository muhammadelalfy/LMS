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

    public function test_a_trial_account_stays_inside_its_limits(): void
    {
        config(['trial.max_groups' => 1]);
        $user = $this->teacher(['expires_at' => now()->addDay()]);
        $token = $user->createToken('t')->plainTextToken;
        $group = ['name' => 'مجموعة', 'grade' => 'الأول', 'days' => [1], 'start_time' => '10:00', 'end_time' => '11:00'];

        $this->withToken($token)->postJson('/api/groups', $group)->assertCreated();
        $this->withToken($token)->postJson('/api/groups', ['name' => 'ثانية'] + $group)->assertForbidden();
        $this->withToken($token)->postJson('/api/payments', [])->assertForbidden();
        $this->withToken($token)->postJson('/api/exams', [])->assertForbidden();
        $this->withToken($token)->postJson('/api/students', ['name' => 'س', 'group' => 'غير مجموعتي'])->assertForbidden();
    }

    public function test_a_trial_account_keeps_one_device(): void
    {
        config(['trial.max_devices' => 1]);
        $user = $this->teacher(['expires_at' => now()->addDay()]);
        foreach (range(1, 2) as $_) {
            $this->postJson('/api/auth/teacher/login', ['email' => $user->email, 'password' => 'Secret-pass-1'])->assertOk();
        }
        $this->assertSame(1, $user->tokens()->count());
    }
}
