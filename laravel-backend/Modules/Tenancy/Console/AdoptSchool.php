<?php

namespace Modules\Tenancy\Console;

use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Modules\Tenancy\Services\SchoolProvisioner;

class AdoptSchool extends Command
{
    protected $signature = 'school:adopt {slug : subdomain, e.g. alnour}
        {--database=database.sqlite : the existing database file, relative to the database/ folder}
        {--name= : the school name}
        {--plan=enterprise : trial, basic, pro or enterprise}';

    protected $description = 'Turn an existing single-school database into a school, keeping all its data';

    public function handle(SchoolProvisioner $provisioner): int
    {
        try {
            $school = $provisioner->adopt([
                'slug' => $this->argument('slug'),
                'name' => $this->option('name') ?: $this->argument('slug'),
                'plan' => $this->option('plan'),
                'database' => $this->option('database'),
            ]);
        } catch (ValidationException $error) {
            foreach ($error->errors() as $field => $messages) {
                $this->error("{$field}: ".implode(' ', $messages));
            }

            return self::FAILURE;
        }

        $this->info("School {$school->id} now uses database/{$school->database()->getName()} and is open at https://".$school->domains()->first()->domain);
        $this->line('Run `php artisan tenants:migrate --tenants='.$school->id.'` to bring its tables up to date.');

        return self::SUCCESS;
    }
}
