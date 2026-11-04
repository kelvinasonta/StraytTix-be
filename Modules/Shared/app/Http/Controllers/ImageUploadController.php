<?php

namespace Modules\Shared\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Shared\app\Contracts\ImageUploadServiceInterface;
use Modules\Shared\app\DTOs\ImageUploadOptions;
use Modules\Shared\app\Http\Requests\DeleteImageRequest;
use Modules\Shared\app\Http\Requests\UploadImageRequest;

class ImageUploadController extends Controller
{
    public function __construct(
        protected ImageUploadServiceInterface $imageUploadService
    ) {}

    /**
     * Upload and sanitize an image file using security scanner and processing presets.
     */
    public function upload(UploadImageRequest $request): JsonResponse
    {
        $file = $request->file('image') ?? $request->file('file');
        $preset = $request->input('preset', 'general');
        $disk = $request->input('disk', config('shared.images.default_disk', 's3'));

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
    public function destroy(DeleteImageRequest $request): JsonResponse
    {
        $disk = $request->input('disk', config('shared.images.default_disk', 's3'));

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
