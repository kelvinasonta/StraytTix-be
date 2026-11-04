<?php

use Illuminate\Support\Facades\Route;
use Modules\Organizer\app\Http\Controllers\OrganizerBankAccountController;
use Modules\Organizer\app\Http\Controllers\OrganizerController;
use Modules\Organizer\app\Http\Controllers\OrganizerInvitationController;
use Modules\Organizer\app\Http\Controllers\OrganizerMemberController;
use Modules\Organizer\app\Http\Controllers\OrganizerVerificationController;

// Public unauthenticated routes
Route::prefix('v1')->group(function () {
    Route::get('public/organizers/{slug}', [OrganizerController::class, 'showPublic']);
    Route::get('explore/organizers/{slug}', [OrganizerController::class, 'showPublic']);
    Route::get('organizers/public/{slug}', [OrganizerController::class, 'showPublic']);
});

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {

    Route::prefix('organizers')->group(function () {
        Route::get('/', [OrganizerController::class, 'index']);
        Route::post('/', [OrganizerController::class, 'store']);
        Route::get('/{organizerUuid}', [OrganizerController::class, 'show']);
        Route::put('/{organizerUuid}', [OrganizerController::class, 'update'])->middleware('organizer.role:owner,admin');
        Route::delete('/{organizerUuid}', [OrganizerController::class, 'destroy'])->middleware('organizer.role:owner');
    });

    // Bank resolution and supported banks list
    Route::prefix('banks')->group(function () {
        Route::get('/', [OrganizerBankAccountController::class, 'getBanks']);
        Route::post('/resolve', [OrganizerBankAccountController::class, 'resolveAccount']);
    });

    Route::prefix('organizers/{organizerUuid}')->group(function () {
        // Settlement Bank Account
        Route::prefix('bank-account')->group(function () {
            Route::get('/', [OrganizerBankAccountController::class, 'show'])->middleware('organizer.role:owner,admin,finance');
            Route::post('/', [OrganizerBankAccountController::class, 'store'])->middleware('organizer.role:owner,admin,finance');
        });

        // Verification
        Route::prefix('verification')->group(function () {
            Route::get('/', [OrganizerVerificationController::class, 'show'])->middleware('organizer.role:owner,admin');
            Route::post('/', [OrganizerVerificationController::class, 'store'])->middleware('organizer.role:owner,admin');
        });

        // Team Members
        Route::prefix('members')->group(function () {
            Route::get('/', [OrganizerMemberController::class, 'index'])->middleware('organizer.role:owner,admin');
            Route::post('/', [OrganizerMemberController::class, 'store'])->middleware('organizer.role:owner,admin');
            Route::put('/{memberUuid}', [OrganizerMemberController::class, 'update'])->middleware('organizer.role:owner,admin');
            Route::delete('/{memberUuid}', [OrganizerMemberController::class, 'destroy'])->middleware('organizer.role:owner,admin');
        });

        // Team Invitations
        Route::prefix('invitations')->group(function () {
            Route::get('/', [OrganizerInvitationController::class, 'index'])->middleware('organizer.role:owner,admin');
            Route::post('/', [OrganizerInvitationController::class, 'store'])->middleware('organizer.role:owner,admin');
            Route::post('/{invitationUuid}/resend', [OrganizerInvitationController::class, 'resend'])->middleware('organizer.role:owner,admin');
            Route::delete('/{invitationUuid}', [OrganizerInvitationController::class, 'destroy'])->middleware('organizer.role:owner,admin');
        });
    });
});
