<?php

namespace Modules\Attendance\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Policies\AttendanceRecordPolicy;
use Modules\Attendance\Services\AttendanceDomainService;

final class AttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AttendanceDomainService::class);
    }

    public function boot(): void
    {
        Route::middleware(['api', 'school'])->prefix('api')->group(__DIR__.'/../routes/api.php');
        Gate::policy(AttendanceRecord::class, AttendanceRecordPolicy::class);
    }
}
