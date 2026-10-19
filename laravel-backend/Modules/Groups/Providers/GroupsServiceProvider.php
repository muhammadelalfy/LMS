<?php

namespace Modules\Groups\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Groups\Models\ClassGroup;
use Modules\Groups\Policies\ClassGroupPolicy;

final class GroupsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::middleware(['api', 'school'])->prefix('api')->group(__DIR__.'/../routes/api.php');
        Gate::policy(ClassGroup::class, ClassGroupPolicy::class);
    }
}
