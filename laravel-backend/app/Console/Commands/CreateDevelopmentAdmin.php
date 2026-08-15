<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('lms:create-development-admin {--email=admin@local.test} {--password=AdminLocal!2026}')]
#[Description('Create or update a local-development-only LMS administrator')]
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

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'مدير التطوير',
                'password' => Hash::make($password),
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
        );

        $this->info('Local development admin is ready.');
        $this->line("Email: {$admin->email}");
        $this->line("Password: {$password}");
        $this->warn('Do not use these credentials in production.');

        return self::SUCCESS;
    }
}
