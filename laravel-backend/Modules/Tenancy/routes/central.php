<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Http\Controllers\SchoolController;
use Modules\Tenancy\Http\Middleware\OperatorToken;

// The platform operator's API: only on the central domains, only with the operator's token.
foreach (config('tenancy.central_domains') as $domain) {
    Route::middleware(['api', OperatorToken::class])
        ->domain($domain)
        ->prefix('api/central')
        ->group(function () {
            Route::get('/schools', [SchoolController::class, 'index']);
            Route::post('/schools', [SchoolController::class, 'store']);
            Route::get('/schools/{school}', [SchoolController::class, 'show']);
            Route::patch('/schools/{school}', [SchoolController::class, 'update']);
            Route::post('/schools/{school}/domains', [SchoolController::class, 'addDomain']);
            Route::delete('/schools/{school}', [SchoolController::class, 'destroy']);
        });
}
