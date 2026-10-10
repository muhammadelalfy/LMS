<?php

use Illuminate\Support\Facades\Route;
use Modules\Learning\Http\Controllers\DutyController;
use Modules\Learning\Http\Controllers\HandwritingController;
use Modules\Learning\Http\Controllers\WorksheetController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/duties/remind', [DutyController::class, 'remind']);
    Route::apiResource('duties', DutyController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['duties' => 'duty']);
    Route::apiResource('worksheets', WorksheetController::class)->only(['index', 'store', 'show']);
    Route::post('/worksheets/{worksheet}/assign', [WorksheetController::class, 'assign']);
    Route::post('/assignments/{assignment}/submit', [WorksheetController::class, 'submit']);
    Route::post('/ocr/handwriting', HandwritingController::class);
});
