<?php

namespace Modules\Attendance\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Attendance\Services\AttendanceDomainService;

final class AttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AttendanceDomainService::class);
    }
}
