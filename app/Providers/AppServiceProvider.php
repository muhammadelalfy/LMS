<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Support\QueryLoopGuard;

/**
 * Nothing feature-specific lives here: each module (Modules/<Name>) registers
 * its own routes, policies, bindings and rate limits in its service provider.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QueryLoopGuard::class);
    }

    public function boot(): void
    {
        // A relation that was not eager-loaded (the N+1 problem) is an error while developing and
        // testing, and only a log line in production so a missed case never breaks a school.
        Model::preventLazyLoading();
        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(
                fn (Model $model, string $relation) => logger()->warning(
                    'Lazy loading '.$relation.' on '.$model::class,
                ),
            );
        }

        $this->app->make(QueryLoopGuard::class)->listen();

        // A switched-off or expired account loses its open sessions at once.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $valid) => $valid
                && (! method_exists($token->tokenable, 'isUsable') || $token->tokenable->isUsable()),
        );
    }
}
