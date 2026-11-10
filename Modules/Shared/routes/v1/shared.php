<?php

use Illuminate\Support\Facades\Route;
use Modules\Shared\app\Http\Controllers\AppSettingsController;
use Modules\Shared\app\Http\Controllers\FeatureFlagController;
use Modules\Shared\app\Http\Controllers\HealthCheckController;
use Modules\Shared\app\Http\Controllers\ImageUploadController;

Route::prefix('v1')->group(function () {

    // Health Checks (Liveness & Readiness for load balancers, uptime monitors, kubernetes)
    Route::get('health', [HealthCheckController::class, 'check'])->name('health.check');
    Route::get('ping', [HealthCheckController::class, 'ping'])->name('health.ping');

    // Public Routes
    Route::get('app/settings', [AppSettingsController::class, 'index'])->name('api.app.settings');
    Route::get('settings', [AppSettingsController::class, 'index'])->name('app.settings');
    Route::get('features', [FeatureFlagController::class, 'index'])->name('features.index');
    Route::get('meta/features', [FeatureFlagController::class, 'index'])->name('features.meta');

    // Protected Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('uploads/images', [ImageUploadController::class, 'upload'])->name('uploads.images');
        Route::post('upload/image', [ImageUploadController::class, 'upload'])->name('upload.image');
        Route::delete('uploads/images', [ImageUploadController::class, 'destroy'])->name('uploads.images.destroy');
    });
});
