<?php

use App\Services\AbsenceSweeper;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('attendance:sweep-absences', function (AbsenceSweeper $sweeper) {
    $this->info('Marked absent: '.$sweeper->sweep());
})->purpose('Mark students absent after their group session ends and notify parents');

// Needs the standard cron entry: * * * * * php artisan schedule:run
Schedule::command('attendance:sweep-absences')->everyFiveMinutes()->withoutOverlapping();
