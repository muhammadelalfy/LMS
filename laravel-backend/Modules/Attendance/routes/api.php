<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\AttendanceController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('attendance', AttendanceController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/attendance/scan', [AttendanceController::class, 'scan']);
    Route::post('/attendance/sweep', [AttendanceController::class, 'sweep']);
    Route::post('/attendance/session-reminders', [AttendanceController::class, 'remindSessions']);
});
