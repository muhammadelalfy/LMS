<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Auth\Models\User;
use Modules\Tenancy\Models\School;

#[Signature('trial:accounts {action : create | list | off | on | extend | remove} {--school=colons} {--email=} {--count=3} {--days=14}')]
#[Description('Create and control temporary teacher accounts (switch off/on, extend, remove)')]
class TrialAccounts extends Command
{
    public function handle(): int
    {
        $school = School::query()->find((string) $this->option('school'));
        if ($school === null) {
            $this->error("There is no school '{$this->option('school')}'.");

            return self::FAILURE;
        }

        return $school->run(fn () => $this->perform((string) $this->argument('action')));
    }

    private function trials()
    {
        return User::query()->where('role', 'teacher')->where('email', 'like', 'trial%@mudarisy.com');
    }

    private function perform(string $action): int
    {
        $email = (string) $this->option('email');
        $days = max(1, (int) $this->option('days'));

        if ($action === 'create') {
            $rows = [];
            for ($i = 0; $i < max(1, (int) $this->option('count')); $i++) {
                do {
                    $mail = 'trial'.random_int(100, 999).'@mudarisy.com';
                } while (User::where('email', $mail)->exists());
                $password = Str::password(14, symbols: false);
                User::create([
                    'name' => 'معلّم تجريبي',
                    'email' => $mail,
                    'password' => $password,
                    'role' => 'teacher',
                    'expires_at' => now()->addDays($days),
                ]);
                $rows[] = [$mail, $password, now()->addDays($days)->toDateString()];
            }
            $this->table(['email', 'password', 'expires'], $rows);

            return self::SUCCESS;
        }

        if ($action === 'list') {
            $this->table(['email', 'state', 'expires'], $this->trials()->get()->map(fn (User $u) => [
                $u->email,
                $u->disabled_at ? 'off' : ($u->isUsable() ? 'on' : 'expired'),
                $u->expires_at?->toDateString() ?? '-',
            ])->all());

            return self::SUCCESS;
        }

        $users = $email === '' ? $this->trials()->get() : $this->trials()->where('email', $email)->get();
        if ($users->isEmpty()) {
            $this->error('No matching trial account.');

            return self::FAILURE;
        }
        foreach ($users as $user) {
            match ($action) {
                'off' => $user->forceFill(['disabled_at' => now()])->save() && $user->tokens()->delete(),
                'on' => $user->forceFill(['disabled_at' => null])->save(),
                'extend' => $user->forceFill(['disabled_at' => null, 'expires_at' => now()->addDays($days)])->save(),
                'remove' => $user->tokens()->delete() | $user->delete(),
                default => throw new \InvalidArgumentException("Unknown action '$action'."),
            };
            $this->line("$action: {$user->email}");
        }

        return self::SUCCESS;
    }
}
