<?php

use Illuminate\Support\Facades\Route;
use Modules\Shared\app\Http\Controllers\ImageUploadController;
use Modules\Shared\App\Http\Controllers\SharedController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('uploads/images', [ImageUploadController::class, 'upload'])->name('uploads.images');
    Route::post('upload/image', [ImageUploadController::class, 'upload'])->name('upload.image');
    Route::delete('uploads/images', [ImageUploadController::class, 'destroy'])->name('uploads.images.destroy');

    Route::apiResource('shareds', SharedController::class)->names('shared');
});
