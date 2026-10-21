<?php

namespace Modules\Notifications\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Notifications\Services\ChannelRegistry;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Route::middleware(['api', 'school'])->prefix('api')->group(__DIR__.'/../routes/api.php');
        // All workers of a paid channel together stay under the provider's rate.
        foreach (ChannelRegistry::PAID as $channel) {
            RateLimiter::for(
                'notify-'.$channel,
                fn () => Limit::perSecond(max(1, (int) config("notifications.rate_per_second.{$channel}")))->by($channel),
            );
        }
    }
}
