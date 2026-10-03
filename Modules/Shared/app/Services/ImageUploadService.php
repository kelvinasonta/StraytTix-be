<?php

namespace Modules\Shared\app\Services;

use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Shared\app\Contracts\ImageUploadServiceInterface;
use Modules\Shared\app\DTOs\ImageUploadOptions;
use Modules\Shared\app\DTOs\UploadedImageDTO;
use Modules\Shared\app\Exceptions\ImageUploadException;
use Modules\Shared\app\Services\Security\ImageProcessor;
use Modules\Shared\app\Services\Security\ImageSecurityScanner;
use Throwable;

class ImageUploadService implements ImageUploadServiceInterface
{
    public function __construct(
        protected ImageSecurityScanner $scanner,
        protected ImageProcessor $processor
    ) {}

    /**
     * Inspect, sanitize, re-encode, and persist an uploaded image.
     *
     * @throws ImageUploadException
     */
    public function upload(UploadedFile|string $file, ?ImageUploadOptions $options = null): UploadedImageDTO
    {
        $options ??= new ImageUploadOptions;
        $config = config('shared.images', []);

        // 1. Resolve preset configuration
        $preset = $options->preset;
        $presetConfig = [];
        if ($preset !== null) {
            $presets = $config['presets'] ?? [];
            if (! isset($presets[$preset])) {
                throw ImageUploadException::presetNotFound($preset);
            }
            $presetConfig = $presets[$preset];
        }

        // 2. Resolve limits and destinations
        $targetDisk = $options->disk
            ?? $presetConfig['disk']
            ?? $config['default_disk']
            ?? 'public';

        $directory = $options->directory
            ?? $presetConfig['directory']
            ?? 'uploads/images';

        $maxSizeBytes = $options->maxSizeBytes
            ?? (isset($presetConfig['max_size_kb']) ? $presetConfig['max_size_kb'] * 1024 : null)
            ?? (($config['max_file_size_kb'] ?? 10240) * 1024);

        $maxWidth = $options->maxWidth
            ?? $presetConfig['max_width']
            ?? ($config['max_dimensions']['width'] ?? 4096);

        $maxHeight = $options->maxHeight
            ?? $presetConfig['max_height']
            ?? ($config['max_dimensions']['height'] ?? 4096);

        $targetFormat = $options->convertTo
            ?? $presetConfig['convert_to']
            ?? 'webp';

        $quality = $options->quality ?? ($presetConfig['quality'] ?? 85);
        $stripMetadata = $options->stripMetadata ?? ($config['strip_metadata'] ?? true);
        $scanPolyglot = $options->scanPolyglot ?? ($config['scan_polyglot'] ?? true);
        $allowedMimes = $config['allowed_mimes'] ?? [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ];

        // 3. Resolve source local file path
        $sourcePath = $this->resolveLocalFilePath($file);
        $explicitSize = $file instanceof UploadedFile ? (int) $file->getSize() : null;

        // 4. Run national security scan (Magic bytes, dimensions, decompression guard, polyglot scan)
        $scanResult = $this->scanner->scan(
            filePath: $sourcePath,
            maxSizeBytes: $maxSizeBytes,
            maxWidth: $maxWidth,
            maxHeight: $maxHeight,
            allowedMimes: $allowedMimes,
            scanPolyglot: $scanPolyglot,
            explicitSize: $explicitSize
        );

        $tempSanitizedPath = null;

        try {
            // 5. Pixel re-encoding & metadata stripping
            if ($stripMetadata || $targetFormat !== null) {
                $processed = $this->processor->processAndSanitize(
                    sourcePath: $sourcePath,
                    sourceMime: $scanResult['mime'],
                    targetFormat: $targetFormat,
                    quality: $quality,
                    maxWidth: $maxWidth,
                    maxHeight: $maxHeight
                );

                $tempSanitizedPath = $processed['path'];
                $finalLocalPath = $processed['path'];
                $finalMime = $processed['mime'];
                $finalExtension = $processed['extension'];
                $finalWidth = $processed['width'];
                $finalHeight = $processed['height'];
                $finalSize = $processed['size'];
            } else {
                $finalLocalPath = $sourcePath;
                $finalMime = $scanResult['mime'];
                $finalExtension = $scanResult['extension'];
                $finalWidth = $scanResult['width'];
                $finalHeight = $scanResult['height'];
                $finalSize = $scanResult['size'];
            }

            // 6. Generate cryptographically safe path and filename (never trust client input)
            $filename = (string) Str::uuid();
            if ($options->filenamePrefix !== null) {
                $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $options->filenamePrefix);
                if ($safePrefix !== '') {
                    $filename = $safePrefix.'_'.$filename;
                }
            }

            $storageRelativePath = sprintf(
                '%s/%s/%s.%s',
                trim($directory, '/'),
                date('Y/m'),
                $filename,
                $finalExtension
            );

            // 7. Persist to storage disk (S3, public, local) with security headers
            $this->persistToStorage(
                disk: $targetDisk,
                relativePath: $storageRelativePath,
                localFilePath: $finalLocalPath,
                mimeType: $finalMime,
                visibility: $options->visibility
            );

            // 8. Generate accessible URL
            $url = $this->getUrl($storageRelativePath, $targetDisk);
            $sha256 = hash_file('sha256', $finalLocalPath) ?: '';

            return new UploadedImageDTO(
                path: $storageRelativePath,
                disk: $targetDisk,
                url: $url,
                filename: basename($storageRelativePath),
                mimeType: $finalMime,
                extension: $finalExtension,
                sizeBytes: $finalSize,
                width: $finalWidth,
                height: $finalHeight,
                sha256: $sha256,
                meta: [
                    'preset' => $preset,
                    'visibility' => $options->visibility,
                    'sanitized' => true,
                ]
            );
        } catch (Throwable $e) {
            Log::error('Image upload failed during processing or storage', [
                'error' => $e->getMessage(),
                'disk' => $targetDisk,
                'preset' => $preset,
            ]);

            if ($e instanceof ImageUploadException) {
                throw $e;
            }

            throw ImageUploadException::storageFailed($targetDisk, $e->getMessage());
        } finally {
            // Guarantee immediate cleanup of temporary scratch file
            if ($tempSanitizedPath !== null && file_exists($tempSanitizedPath)) {
                @unlink($tempSanitizedPath);
            }
        }
    }

    /**
     * Delete an image from storage.
     */
    public function delete(string $path, ?string $disk = null): bool
    {
        $targetDisk = $disk ?? config('shared.images.default_disk', 'public');

        return Storage::disk($targetDisk)->delete($path);
    }

    /**
     * Check if an image exists in storage.
     */
    public function exists(string $path, ?string $disk = null): bool
    {
        $targetDisk = $disk ?? config('shared.images.default_disk', 'public');

        return Storage::disk($targetDisk)->exists($path);
    }

    /**
     * Get the public URL for an image.
     */
    public function getUrl(string $path, ?string $disk = null): string
    {
        $targetDisk = $disk ?? config('shared.images.default_disk', 'public');

        return Storage::disk($targetDisk)->url($path);
    }

    /**
     * Get a signed temporary URL for private images (e.g. S3).
     */
    public function getTemporaryUrl(string $path, DateTimeInterface|int $expiration, ?string $disk = null): string
    {
        $targetDisk = $disk ?? config('shared.images.default_disk', 'public');
        $storage = Storage::disk($targetDisk);

        if (method_exists($storage, 'temporaryUrl')) {
            return $storage->temporaryUrl($path, $expiration);
        }

        return $storage->url($path);
    }

    /**
     * Resolve the absolute local file path from UploadedFile or string.
     */
    protected function resolveLocalFilePath(UploadedFile|string $file): string
    {
        if ($file instanceof UploadedFile) {
            $realPath = $file->getRealPath();
            if ($realPath === false || ! file_exists($realPath)) {
                throw ImageUploadException::processingFailed('Uploaded file could not be read from temporary directory.');
            }

            return $realPath;
        }

        if (! file_exists($file)) {
            throw ImageUploadException::processingFailed("Source file does not exist at [{$file}].");
        }

        return $file;
    }

    /**
     * Persist file stream to storage disk with hardened HTTP headers.
     *
     * @throws ImageUploadException
     */
    protected function persistToStorage(
        string $disk,
        string $relativePath,
        string $localFilePath,
        string $mimeType,
        string $visibility
    ): void {
        $stream = @fopen($localFilePath, 'rb');
        if ($stream === false) {
            throw ImageUploadException::processingFailed('Could not open sanitized image for storage stream.');
        }

        try {
            $storage = Storage::disk($disk);

            $putOptions = [
                'visibility' => $visibility,
                'mimetype' => $mimeType,
                'headers' => [
                    'ContentType' => $mimeType,
                    'CacheControl' => 'max-age=31536000, public, immutable',
                    'X-Content-Type-Options' => 'nosniff',
                ],
            ];

            $stored = $storage->put($relativePath, $stream, $putOptions);

            if (! $stored) {
                throw ImageUploadException::storageFailed($disk, 'Disk driver returned false during write.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
