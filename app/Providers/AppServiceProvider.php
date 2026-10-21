<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * Nothing feature-specific lives here: each module (Modules/<Name>) registers
 * its own routes, policies, bindings and rate limits in its service provider.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // A switched-off or expired account loses its open sessions at once.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $valid) => $valid
                && (! method_exists($token->tokenable, 'isUsable') || $token->tokenable->isUsable()),
        );
    }
}
