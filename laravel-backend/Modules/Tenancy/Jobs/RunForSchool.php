<?php

namespace Modules\Tenancy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Modules\Tenancy\Models\School;

/**
 * Runs one console command inside one school. The scheduler dispatches one
 * of these per active school, so a thousand schools are a thousand small
 * jobs spread over the workers instead of one long loop.
 */
class RunForSchool implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $schoolId, public readonly string $command)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $school = School::query()->find($this->schoolId);
        if ($school === null || ! $school->isActive()) {
            return;
        }
        $school->run(fn () => Artisan::call($this->command));
    }
}
