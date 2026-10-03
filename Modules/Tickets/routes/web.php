<?php

use Illuminate\Support\Facades\Route;
use Modules\Tickets\App\Http\Controllers\TicketsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('tickets', TicketsController::class)->names('tickets');
});
