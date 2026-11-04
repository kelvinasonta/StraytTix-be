<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\app\Http\Controllers\AdminAuditLogController;
use Modules\Admin\app\Http\Controllers\AdminAuthController;
use Modules\Admin\app\Http\Controllers\AdminVerificationController;
use Modules\Admin\app\Http\Controllers\FeatureFlagController;

Route::prefix('v1/admin')->group(function () {
    // Public administrative authentication (Rate-limited: 5 attempts per minute)
    Route::middleware(['throttle:5,1'])->group(function () {
        Route::post('auth/login', [AdminAuthController::class, 'login']);
    });

    // Protected administrative routes (Requires Sanctum + Active Administrator)
    Route::middleware(['auth:sanctum', 'admin.auth'])->group(function () {
        Route::get('auth/me', [AdminAuthController::class, 'me']);
        Route::post('auth/logout', [AdminAuthController::class, 'logout']);

        // System feature flags (Super-admin or system_settings.manage)
        Route::post('feature-flags/{feature}/toggle', [FeatureFlagController::class, 'toggle'])
            ->middleware('admin.auth:system_settings.manage');

        // KYC & Organizer verifications (Requires organizers.verify permission)
        Route::prefix('verifications')->group(function () {
            Route::get('/', [AdminVerificationController::class, 'index'])
                ->middleware('admin.auth:organizers.view');
            Route::get('/{uuid}', [AdminVerificationController::class, 'show'])
                ->middleware('admin.auth:organizers.view');
            Route::post('/{uuid}/review', [AdminVerificationController::class, 'review'])
                ->middleware('admin.auth:organizers.verify');
        });

        // Audit Trail (Requires audit_logs.view permission)
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AdminAuditLogController::class, 'index'])
                ->middleware('admin.auth:audit_logs.view');
            Route::get('/{id}', [AdminAuditLogController::class, 'show'])
                ->middleware('admin.auth:audit_logs.view');
        });
    });
});
