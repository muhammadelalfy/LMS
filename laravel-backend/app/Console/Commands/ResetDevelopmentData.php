<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

#[Signature('lms:reset-development-data')]
#[Description('Reset the local LMS database and reseed Arabic development data')]
class ResetDevelopmentData extends Command
{
    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Blocked: this command is available only in local or testing environments.');
            return self::FAILURE;
        }

        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--seeder' => 'ArabicDemoSeeder',
            '--force' => true,
        ], $this->output);

        $this->info('Development database reset and Arabic demo data restored.');
        return self::SUCCESS;
    }
}
