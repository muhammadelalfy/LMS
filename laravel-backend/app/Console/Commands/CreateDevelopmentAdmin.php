<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\User;
use Modules\Tenancy\Models\School;

#[Signature('lms:create-development-admin {--school=demo : the school to add the administrator to} {--email=admin@local.test} {--password=AdminLocal!2026}')]
#[Description('Create or update a local-development-only administrator in a school')]
class CreateDevelopmentAdmin extends Command
{
    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Blocked: this command is available only in local or testing environments.');
            return self::FAILURE;
        }

        $email = (string) $this->option('email');
        $password = (string) $this->option('password');

        if ($email === '' || $password === '') {
            $this->error('Email and password must not be empty.');
            return self::FAILURE;
        }

        $school = School::query()->find((string) $this->option('school'));
        if ($school === null) {
            $this->error("There is no school '{$this->option('school')}'. Open one first: php artisan school:create <slug> --demo");
            return self::FAILURE;
        }

        $admin = $school->run(fn () => User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'مدير التطوير',
                'password' => Hash::make($password),
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
        ));

        $this->info("Local development admin is ready in school '{$school->id}'.");
        $this->line("Email: {$admin->email}");
        $this->line("Password: {$password}");
        $this->warn('Do not use these credentials in production.');

        return self::SUCCESS;
    }
}
