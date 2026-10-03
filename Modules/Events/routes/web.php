<?php

use Illuminate\Support\Facades\Route;
use Modules\Events\App\Http\Controllers\EventsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('events', EventsController::class)->names('events');
});
