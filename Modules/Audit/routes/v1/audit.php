<?php

use Illuminate\Support\Facades\Route;
use Modules\Audit\app\Http\Controllers\AuditController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::prefix('organizers/{organizerUuid}/audit-logs')
        ->middleware('organizer.role:owner,admin,manager')
        ->group(function () {
            Route::get('/', [AuditController::class, 'index']);
            Route::get('/{id}', [AuditController::class, 'show']);
        });
});
