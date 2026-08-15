<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ExamResultController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\WorksheetController;
use Illuminate\Support\Facades\Route;
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::apiResource('students', StudentController::class)->only(['index','store','show']);
    Route::apiResource('worksheets', WorksheetController::class)->only(['index','store','show']);
    Route::post('/worksheets/{worksheet}/assign', [WorksheetController::class, 'assign']);
    Route::post('/assignments/{assignment}/submit', [WorksheetController::class, 'submit']);
    Route::apiResource('attendance', AttendanceController::class)->only(['index','store','update','destroy']);
    Route::apiResource('exams', ExamResultController::class)->only(['index','store','update','destroy']);
    Route::apiResource('payments', PaymentController::class)->only(['index','store','update','destroy']);
    Route::get('/reports/summary', [ReportController::class, 'summary']);
});
