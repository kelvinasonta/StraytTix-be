<?php

use Illuminate\Support\Facades\Route;
use Modules\Organizer\App\Http\Controllers\OrganizerController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('organizers', OrganizerController::class)->names('organizer');
});
