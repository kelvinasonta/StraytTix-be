<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\app\Http\Controllers\AdminController;
use Modules\Admin\app\Http\Controllers\AdminVerificationController;
use Modules\Admin\app\Http\Controllers\FeatureFlagController;

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::apiResource('admins', AdminController::class)->names('admin');
    Route::post('feature-flags/{feature}/toggle', [FeatureFlagController::class, 'toggle']);

    Route::prefix('verifications')->group(function () {
        Route::get('/', [AdminVerificationController::class, 'index']);
        Route::get('/{uuid}', [AdminVerificationController::class, 'show']);
        Route::post('/{uuid}/review', [AdminVerificationController::class, 'review']);
    });
});
