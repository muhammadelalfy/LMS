<?php

namespace Modules\Students\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Students\Models\Student;
use Modules\Students\Policies\StudentPolicy;

final class StudentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Route::middleware(['api', 'school'])->prefix('api')->group(__DIR__.'/../routes/api.php');
        Gate::policy(Student::class, StudentPolicy::class);
    }
}
