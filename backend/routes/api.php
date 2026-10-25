<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CropController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FarmController;
use App\Http\Controllers\Api\HarvestController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PlotController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SeasonController;
use App\Http\Middleware\EnsureCurrentPasswordSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::middleware('web')->group(function () {
    Route::get('/auth/csrf', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store'));
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'request'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
    Route::middleware(['auth', EnsureCurrentPasswordSession::class])->group(function () {
        Route::get('/auth/user', fn (Request $request) => response()->json(['user' => $request->user()])->header('Cache-Control', 'no-store'));
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::patch('/auth/profile', [ProfileController::class, 'update'])->middleware('throttle:10,1');
        Route::put('/auth/password', [ProfileController::class, 'password'])->middleware('throttle:5,1');
        Route::apiResource('farms', FarmController::class);
        Route::apiResource('plots', PlotController::class);
        Route::apiResource('crops', CropController::class);
        Route::apiResource('seasons', SeasonController::class);
        Route::apiResource('activities', ActivityController::class);
        Route::apiResource('expenses', ExpenseController::class);
        Route::apiResource('harvests', HarvestController::class);
        Route::apiResource('sales', SaleController::class);
        Route::get('reports', ReportController::class);
        Route::get('dashboard', DashboardController::class);
    });
});
