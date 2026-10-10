<?php

namespace Modules\Chat\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Route::middleware(['api', 'school'])->prefix('api')->group(__DIR__.'/../routes/api.php');
        // Chat is bursty by nature: a person may send ~1.5 messages a second.
        RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(90)->by($request->user()?->id ?: $request->ip()));
        require __DIR__.'/../routes/channels.php';
    }
}
