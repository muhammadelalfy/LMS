<?php

use Illuminate\Support\Facades\Route;
use Modules\Groups\Http\Controllers\ClassGroupController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('groups', ClassGroupController::class)->only(['index', 'store', 'update', 'destroy']);
});
