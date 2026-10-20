<?php

namespace Modules\Exams\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Exams\Models\ExamResult;
use Modules\Exams\Policies\ExamResultPolicy;

final class ExamsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::middleware(['api', 'school'])->prefix('api')->group(__DIR__.'/../routes/api.php');
        Gate::policy(ExamResult::class, ExamResultPolicy::class);
    }
}
