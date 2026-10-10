<?php

namespace Modules\Learning\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Learning\Models\Worksheet;
use Modules\Learning\Policies\WorksheetPolicy;
use Modules\Learning\Services\Ocr\ClaudeHandwritingReader;
use Modules\Learning\Services\Ocr\HandwritingReader;

final class LearningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HandwritingReader::class, ClaudeHandwritingReader::class);
    }

    public function boot(): void
    {
        Route::middleware(['api', 'school'])->prefix('api')->group(__DIR__.'/../routes/api.php');
        Gate::policy(Worksheet::class, WorksheetPolicy::class);
    }
}
