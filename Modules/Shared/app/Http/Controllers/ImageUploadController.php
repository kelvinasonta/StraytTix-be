<?php

namespace Modules\Shared\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Modules\Shared\app\Contracts\ImageUploadServiceInterface;
use Modules\Shared\app\DTOs\ImageUploadOptions;

class ImageUploadController extends Controller
{
    public function __construct(
        protected ImageUploadServiceInterface $imageUploadService
    ) {}

    /**
     * Upload and sanitize an image file using security scanner and processing presets.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => ['required_without:file', 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp'],
            'file' => ['required_without:image', 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp'],
            'preset' => ['nullable', 'string', 'in:avatar,event_banner,organizer_logo,general'],
            'disk' => ['nullable', 'string'],
        ]);

        $file = $request->file('image') ?? $request->file('file');
        $preset = $request->input('preset', 'general');
        $disk = $request->input('disk', 'public');

        $options = ImageUploadOptions::forPreset($preset, [
            'disk' => $disk,
        ]);

        $dto = $this->imageUploadService->upload($file, $options);

        return ApiResponse::success(
            'Image uploaded successfully.',
            $dto->toArray(),
            201
        );
    }

    /**
     * Delete an uploaded image from storage.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'path' => ['required', 'string'],
            'disk' => ['nullable', 'string'],
        ]);

        $disk = $request->input('disk', 'public');

        $deleted = $this->imageUploadService->delete(
            $request->input('path'),
            $disk
        );

        if (! $deleted) {
            return ApiResponse::error('Failed to delete image or file not found.', 404);
        }

        return ApiResponse::success('Image deleted successfully.');
    }
}
