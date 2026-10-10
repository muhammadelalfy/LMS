<?php

namespace Modules\Tenancy\Console;

use Illuminate\Console\Command;
use Modules\Tenancy\Models\School;

class ListSchools extends Command
{
    protected $signature = 'school:list';

    protected $description = 'List the schools, their plans and addresses';

    public function handle(): int
    {
        $this->table(
            ['id', 'name', 'plan', 'status', 'students', 'domains'],
            School::query()->with('domains')->orderBy('id')->get()->map(fn (School $school) => [
                $school->id, $school->name, $school->plan, $school->isActive() ? 'active' : 'locked',
                $school->studentLimit() ?? 'unlimited', $school->domains->pluck('domain')->implode(', '),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
