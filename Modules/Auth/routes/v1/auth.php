<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Http\Controllers\AcceptInvitationController;
use Modules\Auth\app\Http\Controllers\EmailVerificationController;
use Modules\Auth\app\Http\Controllers\ForgotPasswordController;
use Modules\Auth\app\Http\Controllers\LoginController;
use Modules\Auth\app\Http\Controllers\LogoutController;
use Modules\Auth\app\Http\Controllers\MeController;
use Modules\Auth\app\Http\Controllers\ProfileController;
use Modules\Auth\app\Http\Controllers\RefreshTokenController;
use Modules\Auth\app\Http\Controllers\RegisterController;
use Modules\Auth\app\Http\Controllers\ResetPasswordController;
use Modules\Auth\app\Http\Controllers\SessionController;
use Modules\Auth\app\Http\Controllers\UserActivityController;
use Modules\Shared\app\Http\Controllers\FeatureFlagController as SharedFeatureFlagController;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        // Feature flag status alias for auth
        Route::get('features', [SharedFeatureFlagController::class, 'index'])->name('auth.features');

        // Login and Register routes
        Route::post('login', [LoginController::class, 'store'])->name('auth.login');
        Route::post('register', [RegisterController::class, 'store'])
            ->middleware('feature:auth-registration')
            ->name('auth.register');

        // Password Reset
        Route::middleware('feature:auth-password-reset')->group(function () {
            Route::post('password/forgot', [ForgotPasswordController::class, 'store'])
                ->middleware('throttle:password-reset')
                ->name('auth.password.forgot');
            Route::post('password/reset', [ResetPasswordController::class, 'store'])->name('auth.password.reset');
        });

        // Email Verification
        Route::post('email/verify', [EmailVerificationController::class, 'store'])->name('auth.verify');

        // Accept Staff Invitation
        Route::post('invitations/verify', [AcceptInvitationController::class, 'verify'])->name('auth.invitations.verify');
        Route::post('invitations/accept', [AcceptInvitationController::class, 'store'])->name('auth.invitations.accept');

        // Protected routes that require authentication
        Route::middleware(['auth:sanctum'])->group(function () {

            Route::get('me', MeController::class)->name('auth.me');
            Route::post('logout', [LogoutController::class, 'store'])->name('auth.logout');
            Route::post('refresh', [RefreshTokenController::class, 'store'])->name('auth.refresh');

            Route::post('email/resend', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:verification-resend')
                ->name('auth.resend');

            // Verified user routes
            Route::middleware(['verified'])->group(function () {
                // Profile Self-Service
                Route::put('profile', [ProfileController::class, 'update'])->name('auth.profile.update');
                Route::put('password', [ProfileController::class, 'updatePassword'])->name('auth.password.update');
                Route::delete('account', [ProfileController::class, 'destroy'])->name('auth.account.destroy');

                // User-Facing Activity Logs / Audit Trail
                Route::get('activities', [UserActivityController::class, 'index'])->name('auth.activities');
                Route::get('activity-logs', [UserActivityController::class, 'index'])->name('auth.activity-logs');

                // Session & Device Management
                Route::middleware('feature:auth-session-management')->group(function () {
                    Route::get('sessions', [SessionController::class, 'index'])->name('auth.sessions.index');
                    Route::delete('sessions/{id}', [SessionController::class, 'destroy'])->name('auth.sessions.destroy');
                    Route::post('sessions/logout-others', [SessionController::class, 'destroyOthers'])->name('auth.sessions.logout-others');
                    Route::post('logout-others', [SessionController::class, 'destroyOthers'])->name('auth.sessions.logout-others.alias');
                    Route::get('sessions/history', [SessionController::class, 'history'])->name('auth.sessions.history');
                    Route::get('login-history', [SessionController::class, 'history'])->name('auth.login-history');
                });
            });
        });
    });
});
