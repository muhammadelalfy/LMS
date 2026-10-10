<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\ReportController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/reports/summary', [ReportController::class, 'summary']);
});
