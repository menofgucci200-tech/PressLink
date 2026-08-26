<?php

use App\Http\Controllers\Api\V1\CustomerAuthController;
use App\Http\Controllers\Api\V1\CustomerProfileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderIssueController;
use App\Http\Controllers\Api\V1\PressingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');

    Route::prefix('auth/customer')->middleware('throttle:15,1')->group(function () {
        Route::post('/check-phone', [CustomerAuthController::class, 'checkPhone']);
        Route::post('/login', [CustomerAuthController::class, 'login']);
        Route::post('/register', [CustomerAuthController::class, 'register']);
        Route::get('/me', [CustomerAuthController::class, 'me'])->middleware('auth:sanctum');
        Route::post('/logout', [CustomerAuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::get('/orders/{order}/issues', [OrderIssueController::class, 'index']);
        Route::post('/orders/{order}/issues', [OrderIssueController::class, 'store']);

        Route::get('/pressings/mine', [PressingController::class, 'mine']);
        Route::post('/pressings/join', [PressingController::class, 'join']);
        Route::delete('/pressings/{pressing}/leave', [PressingController::class, 'leave']);

        Route::put('/customer/profile', [CustomerProfileController::class, 'update']);
        Route::put('/customer/password', [CustomerProfileController::class, 'updatePassword']);
        Route::post('/customer/photo', [CustomerProfileController::class, 'updatePhoto']);
        Route::delete('/customer/photo', [CustomerProfileController::class, 'deletePhoto']);
        Route::put('/customer/fcm-token', [CustomerProfileController::class, 'updateFcmToken']);
    });
});
