<?php

use App\Http\Controllers\Api\V1\CustomerAuthController;
use App\Http\Controllers\Api\V1\CustomerProfileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderIssueController;
use App\Http\Controllers\Api\V1\PressingController;
use App\Http\Controllers\GeniusPayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Notification signée de GeniusPay (paiement en ligne des demandes d'abonnement).
Route::post('/webhooks/geniuspay', [GeniusPayController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->name('webhooks.geniuspay');

Route::prefix('v1')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware(['auth:sanctum', 'customer.active']);

    // Routes sensibles/non authentifiées (vérif. de numéro, connexion,
    // inscription) : throttle IP strict contre le brute-force.
    Route::prefix('auth/customer')->middleware('throttle:15,1')->group(function () {
        Route::post('/check-phone', [CustomerAuthController::class, 'checkPhone']);
        Route::post('/login', [CustomerAuthController::class, 'login']);
        Route::post('/register', [CustomerAuthController::class, 'register']);
    });

    // /me et /logout sont déjà protégées par auth:sanctum (un jeton valide
    // suffit) et appelées fréquemment par une app connectée légitime — les
    // laisser dans le throttle partagé avec login/register pénalisait des
    // utilisateurs déjà authentifiés dès qu'une IP partagée (NAT, réseau
    // d'entreprise) épuisait le quota via d'autres connexions (voir
    // load-testing/RAPPORT.md, finding C).
    Route::prefix('auth/customer')->middleware(['auth:sanctum', 'customer.active', 'throttle:120,1'])->group(function () {
        Route::get('/me', [CustomerAuthController::class, 'me']);
        Route::post('/logout', [CustomerAuthController::class, 'logout']);
    });

    Route::middleware(['auth:sanctum', 'customer.active'])->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
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
        // Le mot de passe est vérifié à chaque tentative : limite stricte.
        Route::put('/customer/phone', [CustomerProfileController::class, 'updatePhone'])->middleware('throttle:10,1');
        Route::post('/customer/photo', [CustomerProfileController::class, 'updatePhoto']);
        Route::delete('/customer/photo', [CustomerProfileController::class, 'deletePhoto']);
        Route::put('/customer/onesignal-player-id', [CustomerProfileController::class, 'updateOnesignalPlayerId']);
    });
});
