<?php

use Illuminate\Support\Facades\Route;
use Modules\Shared\app\Http\Controllers\AppSettingsController;
use Modules\Shared\app\Http\Controllers\ImageUploadController;

Route::prefix('v1')->group(function () {

    // Public Routes
    Route::get('app/settings', [AppSettingsController::class, 'index'])->name('app.settings');

    // Protected Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('uploads/images', [ImageUploadController::class, 'upload'])->name('uploads.images');
        Route::post('upload/image', [ImageUploadController::class, 'upload'])->name('upload.image');
        Route::delete('uploads/images', [ImageUploadController::class, 'destroy'])->name('uploads.images.destroy');
    });
});
