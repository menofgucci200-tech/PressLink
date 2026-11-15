<?php

use App\Http\Controllers\Api\V1\CustomerAddressController;
use App\Http\Controllers\Api\V1\CustomerAuthController;
use App\Http\Controllers\Api\V1\CustomerProfileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderDeliveryController;
use App\Http\Controllers\Api\V1\OrderIssueController;
use App\Http\Controllers\Api\V1\PickupRequestController;
use App\Http\Controllers\Api\V1\PressingController;
use App\Http\Controllers\AudienceController;
use App\Http\Controllers\GeniusPayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Notification signée de GeniusPay (paiement en ligne des demandes d'abonnement).
Route::post('/webhooks/geniuspay', [GeniusPayController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->name('webhooks.geniuspay');

// Audience du site vitrine (pages vues, clics) : menu Audience de la console.
Route::post('/audience', [AudienceController::class, 'store'])
    ->middleware('throttle:120,1')
    ->name('audience.store');

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
        Route::get('/orders/{order}/delivery', [OrderDeliveryController::class, 'offer']);
        Route::post('/orders/{order}/delivery', [OrderDeliveryController::class, 'store'])->middleware('throttle:10,1');
        Route::post('/orders/{order}/delivery/cancel', [OrderDeliveryController::class, 'cancel']);

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

        // Adresses de ramassage et de livraison.
        Route::get('/communes', [CustomerAddressController::class, 'communes']);
        Route::get('/customer/addresses', [CustomerAddressController::class, 'index']);
        Route::post('/customer/addresses', [CustomerAddressController::class, 'store'])->middleware('throttle:30,1');
        Route::put('/customer/addresses/{address}', [CustomerAddressController::class, 'update'])->middleware('throttle:30,1');
        Route::delete('/customer/addresses/{address}', [CustomerAddressController::class, 'destroy']);

        // Demandes de ramassage (mode « Mon pressing »).
        Route::get('/pickup-options', [PickupRequestController::class, 'options']);
        Route::get('/pressings/{pressing}/pickup-availability', [PickupRequestController::class, 'availability']);
        Route::get('/pickup-requests', [PickupRequestController::class, 'index']);
        Route::post('/pickup-requests', [PickupRequestController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/pickup-requests/{pickupRequest}', [PickupRequestController::class, 'show']);
        Route::post('/pickup-requests/{pickupRequest}/photos', [PickupRequestController::class, 'storePhoto'])->middleware('throttle:30,1');
        Route::post('/pickup-requests/{pickupRequest}/cancel', [PickupRequestController::class, 'cancel']);
    });
});
