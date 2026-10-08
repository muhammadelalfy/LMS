<?php

namespace App\Providers;

use App\Services\Ocr\ClaudeHandwritingReader;
use App\Services\Ocr\HandwritingReader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HandwritingReader::class, ClaudeHandwritingReader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
