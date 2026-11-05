<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Credits\app\Http\Controllers\CreditTopupController;
use Modules\Credits\app\Http\Controllers\CreditWalletController;

Route::prefix('v1')->group(function () {
    // Dynamic Multi-Gateway Webhook Route (e.g. /credits/webhooks/paystack, /credits/webhooks/flutterwave)
    Route::post('credits/webhooks/{gateway}', [CreditTopupController::class, 'webhook']);

    // Authenticated Organizer Scoped Credit Routes
    Route::middleware(['auth:sanctum', 'organizer.role:owner,admin,manager'])
        ->prefix('organizers/{organizerUuid}/credits')
        ->group(function () {
            Route::get('wallet', [CreditWalletController::class, 'show']);
            Route::get('ledger', [CreditWalletController::class, 'ledger']);
            Route::get('gateways', [CreditTopupController::class, 'gateways']);
            Route::post('top-up/initialize', [CreditTopupController::class, 'initialize']);
            Route::get('top-up/verify/{reference}', [CreditTopupController::class, 'verify']);
            Route::get('reconcile', [CreditWalletController::class, 'reconcile']);
        });
});
