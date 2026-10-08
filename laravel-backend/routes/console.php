<?php

use App\Services\AbsenceSweeper;
use App\Services\DutyReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('attendance:sweep-absences', function (AbsenceSweeper $sweeper) {
    $this->info('Marked absent: '.$sweeper->sweep());
})->purpose('Mark students absent after their group session ends and notify parents');

Artisan::command('duties:send-reminders', function (DutyReminders $reminders) {
    $this->info('Duties reminded: '.$reminders->send());
})->purpose('Remind each group of the duties due on its lesson day');

// Needs the standard cron entry: * * * * * php artisan schedule:run
Schedule::command('attendance:sweep-absences')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('duties:send-reminders')->everyFiveMinutes()->withoutOverlapping();
