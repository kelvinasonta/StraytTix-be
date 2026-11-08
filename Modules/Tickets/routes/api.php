<?php

use Illuminate\Support\Facades\Route;
use Modules\Tickets\app\Http\Controllers\AttendeeController;
use Modules\Tickets\app\Http\Controllers\TicketsController;
use Modules\Tickets\app\Http\Controllers\TicketTypesController;

// Public route to view ticket types for an event
Route::prefix('v1')->group(function () {
    Route::get('events/{eventUuid}/ticket-types', [TicketTypesController::class, 'index']);
});

// Authenticated routes
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // User Tickets
    Route::get('tickets', [TicketsController::class, 'index']);
    Route::post('tickets', [TicketsController::class, 'store']);
    Route::get('tickets/{ticketUuid}', [TicketsController::class, 'show']);

    // Organizer Ticket Type management
    Route::prefix('events/{eventUuid}/ticket-types')->group(function () {
        Route::post('/', [TicketTypesController::class, 'store']);
    });

    Route::prefix('ticket-types')->group(function () {
        Route::put('/{ticketTypeUuid}', [TicketTypesController::class, 'update']);
        Route::delete('/{ticketTypeUuid}', [TicketTypesController::class, 'destroy']);
    });

    // Organizer Attendees & Check-in Scanner
    Route::prefix('organizers/{organizerUuid}/events/{eventUuid}')->group(function () {
        Route::middleware('organizer.role:org.attendees.view')->group(function () {
            Route::get('attendees', [AttendeeController::class, 'index']);
            Route::post('attendees/{ticketUuid}/undo-check-in', [AttendeeController::class, 'undoCheckInByUuid']);
            Route::post('check-in', [AttendeeController::class, 'checkIn']);
            Route::post('check-in/undo', [AttendeeController::class, 'undoCheckIn']);
            Route::get('check-in/stats', [AttendeeController::class, 'stats']);
        });

        Route::get('attendees/export', [AttendeeController::class, 'export'])->middleware('organizer.role:org.attendees.manage');
    });
});
