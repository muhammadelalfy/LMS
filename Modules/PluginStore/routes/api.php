<?php

use Illuminate\Support\Facades\Route;
use Modules\PluginStore\Http\Controllers\PluginStoreController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/plugins', [PluginStoreController::class, 'index']);
    Route::get('/plugins/installed', [PluginStoreController::class, 'installed']);
    Route::post('/plugins/{plugin}/purchase', [PluginStoreController::class, 'purchase']);
    Route::post('/plugins/{plugin}/install', [PluginStoreController::class, 'install']);
    Route::delete('/plugins/{plugin}/install', [PluginStoreController::class, 'uninstall']);
});
