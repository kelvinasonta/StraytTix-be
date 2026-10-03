<?php

namespace Modules\Shared\app\Contracts;

use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Modules\Shared\app\DTOs\ImageUploadOptions;
use Modules\Shared\app\DTOs\UploadedImageDTO;

interface ImageUploadServiceInterface
{
    /**
     * Inspect, sanitize, re-encode, and persist an uploaded image.
     */
    public function upload(UploadedFile|string $file, ?ImageUploadOptions $options = null): UploadedImageDTO;

    /**
     * Delete an existing image from disk.
     */
    public function delete(string $path, ?string $disk = null): bool;

    /**
     * Check if an image exists on the specified disk.
     */
    public function exists(string $path, ?string $disk = null): bool;

    /**
     * Get the public URL for an image.
     */
    public function getUrl(string $path, ?string $disk = null): string;

    /**
     * Get a signed temporary URL for private images (e.g. on S3).
     */
    public function getTemporaryUrl(string $path, DateTimeInterface|int $expiration, ?string $disk = null): string;
}
