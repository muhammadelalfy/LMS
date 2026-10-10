<?php

namespace Modules\Tenancy\Console;

use Illuminate\Console\Command;
use Modules\Tenancy\Models\School;

class SetSchoolStatus extends Command
{
    protected $signature = 'school:status {slug} {status : active or suspended}';

    protected $description = 'Suspend a school (everyone is turned away) or activate it again';

    public function handle(): int
    {
        $status = $this->argument('status');
        if (! in_array($status, [School::ACTIVE, School::SUSPENDED], true)) {
            $this->error('Status must be active or suspended.');

            return self::INVALID;
        }
        School::query()->findOrFail($this->argument('slug'))->update(['status' => $status]);
        $this->info("School {$this->argument('slug')} is now {$status}.");

        return self::SUCCESS;
    }
}
