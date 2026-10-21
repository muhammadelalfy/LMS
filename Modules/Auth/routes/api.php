<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;

// Public endpoints: each proves itself some other way than a bearer token.
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/admin/login', fn (Request $request, AuthController $controller) => $controller->loginAsRole($request, 'admin'));
Route::post('/auth/teacher/login', fn (Request $request, AuthController $controller) => $controller->loginAsRole($request, 'teacher'));
Route::post('/auth/parent/login', fn (Request $request, AuthController $controller) => $controller->loginAsRole($request, 'parent'));
Route::post('/auth/student/login', fn (Request $request, AuthController $controller) => $controller->loginAsRole($request, 'student'));

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/password', [AuthController::class, 'changePassword']);
});
