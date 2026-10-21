<?php

namespace Modules\Tenancy\Console;

use Illuminate\Console\Command;
use Modules\Tenancy\Jobs\RunForSchool;
use Modules\Tenancy\Models\School;

class DispatchToSchools extends Command
{
    protected $signature = 'schools:dispatch {schedulable : an artisan command to run inside every active school}';

    protected $description = 'Queue one job per active school that runs the given command in that school';

    public function handle(): int
    {
        $count = 0;
        School::query()->where('status', School::ACTIVE)->pluck('id')->each(function (string $id) use (&$count) {
            RunForSchool::dispatch($id, $this->argument('schedulable'));
            $count++;
        });
        $this->info("Queued for {$count} schools.");

        return self::SUCCESS;
    }
}
