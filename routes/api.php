<?php

use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/plans', [PlanController::class, 'index']);
        Route::get('/plans/{plan}', [PlanController::class, 'show']);

        Route::apiResource('subscriptions', SubscriptionController::class);

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);

        Route::prefix('admin')->middleware('admin')->group(function () {
            Route::patch('plans/{plan}/activate', [AdminPlanController::class, 'activate']);
            Route::apiResource('plans', AdminPlanController::class);

            Route::get('/payments', [AdminPaymentController::class, 'index']);
            Route::get('/payments/{payment}', [AdminPaymentController::class, 'show']);
            Route::post('/payments/{payment}/confirm', [AdminPaymentController::class, 'confirm']);
            Route::post('/payments/{payment}/fail', [AdminPaymentController::class, 'fail']);
        });
    });
});
