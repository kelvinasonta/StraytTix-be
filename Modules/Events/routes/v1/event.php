<?php

use Illuminate\Support\Facades\Route;
use Modules\Events\app\Http\Controllers\EventDiscoverController;
use Modules\Events\app\Http\Controllers\EventMetadataController;
use Modules\Events\app\Http\Controllers\EventsController;
use Modules\Events\app\Http\Controllers\EventStaffController;
use Modules\Events\app\Http\Controllers\FavouriteController;

Route::prefix('v1')->group(function () {
    Route::get('events/discover', [EventDiscoverController::class, 'index'])->name('events.discover');
    Route::get('explore/events', [EventsController::class, 'explore']);
    Route::get('explore/events/{slug}', [EventsController::class, 'showPublic']);

    // Event metadata & options
    Route::prefix('meta')->group(function () {
        Route::get('event-formats', [EventMetadataController::class, 'eventFormats']);
        Route::get('event-types', [EventMetadataController::class, 'eventTypes']);
        Route::get('categories', [EventMetadataController::class, 'categories']);
    });
});

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::prefix('organizers/{organizerUuid}/events')->group(function () {
        Route::middleware('organizer.role:owner,admin,manager,staff')->group(function () {
            Route::get('/', [EventsController::class, 'index']);
            Route::get('/{eventUuid}', [EventsController::class, 'show']);
            Route::get('/{eventUuid}/stats', [EventsController::class, 'stats']);
            Route::get('/{eventUuid}/staff', [EventStaffController::class, 'index']);
        });

        // Event management (manager, admin, owner)
        Route::middleware('organizer.role:owner,admin,manager')->group(function () {
            Route::post('/', [EventsController::class, 'store']);
            Route::put('/{eventUuid}', [EventsController::class, 'update']);
            Route::delete('/{eventUuid}', [EventsController::class, 'destroy']);
            Route::post('/{eventUuid}/publish', [EventsController::class, 'publish']);
            Route::post('/{eventUuid}/unpublish', [EventsController::class, 'unpublish']);
            Route::post('/{eventUuid}/archive', [EventsController::class, 'archive']);
            Route::post('/{eventUuid}/cancel', [EventsController::class, 'cancel']);
            Route::post('/{eventUuid}/duplicate', [EventsController::class, 'duplicate']);

            // Event staff assignment management
            Route::post('/{eventUuid}/staff', [EventStaffController::class, 'store']);
            Route::put('/{eventUuid}/staff/{userId}', [EventStaffController::class, 'update']);
            Route::delete('/{eventUuid}/staff/{userId}', [EventStaffController::class, 'destroy']);
        });
    });

    // Favorites
    Route::prefix('events/favorites')->group(function () {
        Route::get('/', [FavouriteController::class, 'index']);
        Route::get('/ids', [FavouriteController::class, 'ids']);
        Route::post('/', [FavouriteController::class, 'store']);
        Route::post('/sync', [FavouriteController::class, 'sync']);
        Route::delete('/{eventUuid}', [FavouriteController::class, 'destroy']);
        Route::post('/{eventUuid}/toggle', [FavouriteController::class, 'toggle']);
        Route::get('/{eventUuid}/check', [FavouriteController::class, 'check']);
    });
});
