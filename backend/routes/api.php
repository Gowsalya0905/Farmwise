<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FarmController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::middleware('web')->group(function () {
    Route::get('/auth/csrf', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store'));
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::middleware('auth')->group(function () {
        Route::get('/auth/user', fn (Request $request) => response()->json(['user' => $request->user()])->header('Cache-Control', 'no-store'));
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::apiResource('farms', FarmController::class);
    });
});
