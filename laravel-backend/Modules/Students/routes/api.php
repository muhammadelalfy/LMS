<?php

use Illuminate\Support\Facades\Route;
use Modules\Students\Http\Controllers\FaceTemplateController;
use Modules\Students\Http\Controllers\StudentController;

Route::middleware('auth:sanctum')->group(function () {
    // Before the resource, or "qr-directory" would be read as a student id.
    Route::get('/students/qr-directory', [StudentController::class, 'qrDirectory']);
    Route::apiResource('students', StudentController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::get('/face-templates', [FaceTemplateController::class, 'index']);
    Route::put('/students/{student}/face-template', [FaceTemplateController::class, 'update']);
    Route::delete('/students/{student}/face-template', [FaceTemplateController::class, 'destroy']);
    Route::get('/students/{student}/qr', [StudentController::class, 'qr']);
    Route::post('/students/{student}/qr/regenerate', [StudentController::class, 'regenerateQr']);
});
