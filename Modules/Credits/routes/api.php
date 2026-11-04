<?php

use Illuminate\Support\Facades\Route;
use Modules\Credits\app\Http\Controllers\CreditTopupController;
use Modules\Credits\app\Http\Controllers\CreditWalletController;

Route::prefix('v1')->group(function () {
    // Public webhook route
    Route::post('credits/webhooks/paystack', [CreditTopupController::class, 'webhook']);

    // Authenticated Organizer Scoped Credit Routes
    Route::middleware(['auth:sanctum', 'organizer.role:owner,admin,manager'])
        ->prefix('organizers/{organizerUuid}/credits')
        ->group(function () {
            Route::get('wallet', [CreditWalletController::class, 'show']);
            Route::get('ledger', [CreditWalletController::class, 'ledger']);
            Route::post('top-up/initialize', [CreditTopupController::class, 'initialize']);
            Route::get('reconcile', [CreditWalletController::class, 'reconcile']);
        });
});
