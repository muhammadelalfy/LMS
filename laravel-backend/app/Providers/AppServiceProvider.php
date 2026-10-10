<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
    }
}
