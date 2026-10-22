<?php

namespace Modules\Courses\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Courses\Models\Course;
use Modules\Courses\Policies\CoursePolicy;

final class CoursesServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::middleware(['api', 'school'])->prefix('api')->group(__DIR__.'/../routes/api.php');
        Gate::policy(Course::class, CoursePolicy::class);
    }
}
