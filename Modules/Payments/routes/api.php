<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Payments\app\Http\Controllers\CheckoutPaymentController;
use Modules\Payments\app\Http\Controllers\OrderController;

Route::prefix('v1')->group(function () {
    // Payment Webhook
    Route::post('webhooks/payments/{gateway}', [CheckoutPaymentController::class, 'webhook']);

    // Authenticated Customer & Buyer Endpoints
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('events/{eventUuid}/orders', [OrderController::class, 'store']);
        Route::get('orders', [OrderController::class, 'userOrders']);
        Route::get('orders/{orderUuid}', [OrderController::class, 'show']);

        Route::post('orders/{orderUuid}/payments', [CheckoutPaymentController::class, 'initialize']);
        Route::get('payments/verify/{reference}', [CheckoutPaymentController::class, 'verify']);
    });

    // Organizer Scoped Order Management
    Route::middleware(['auth:sanctum', 'organizer.role:owner,admin,finance,manager'])->prefix('organizers/{organizerUuid}')->group(function () {
        Route::get('orders', [OrderController::class, 'organizerOrders']);
        Route::get('orders/export', [OrderController::class, 'exportOrganizerOrders']);
        Route::get('events/{eventUuid}/orders', [OrderController::class, 'organizerOrders']);
        Route::get('events/{eventUuid}/orders/export', [OrderController::class, 'exportOrganizerOrders']);
    });
});
