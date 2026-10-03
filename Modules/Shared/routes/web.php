<?php

use Illuminate\Support\Facades\Route;
use Modules\Shared\App\Http\Controllers\SharedController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('shareds', SharedController::class)->names('shared');
});
