<?php

namespace Modules\Calls\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CallsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Route::middleware(['api', 'school'])->prefix('api')->group(__DIR__.'/../routes/api.php');

        // Webhooks and OAuth redirects come from other companies to the central address.
        $this->app->booted(function () {
            foreach (config('tenancy.central_domains') as $domain) {
                Route::middleware('api')->domain($domain)->prefix('api')->group(__DIR__.'/../routes/central.php');
            }
        });
    }
}
