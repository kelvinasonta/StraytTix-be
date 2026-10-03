<?php

namespace Modules\Shared\app\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageUploadException extends Exception
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        string $message,
        protected int $status = 422,
        protected string $errorCode = 'IMAGE_UPLOAD_ERROR',
        protected array $meta = [],
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    public function render(Request $request): ?JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'code' => $this->errorCode(),
                'errors' => null,
                'meta' => ! empty($this->meta) ? (object) $this->meta : null,
            ], $this->status());
        }

        return null;
    }

    public static function invalidMimeType(string $detectedMime): self
    {
        return new self(
            message: "Disallowed or spoofed file MIME type detected: [{$detectedMime}]. Only trusted raster image formats are permitted.",
            status: 422,
            errorCode: 'INVALID_IMAGE_MIME_TYPE',
            meta: ['detected_mime' => $detectedMime]
        );
    }

    public static function fileTooLarge(int $sizeBytes, int $maxSizeBytes): self
    {
        $sizeKb = round($sizeBytes / 1024, 2);
        $maxKb = round($maxSizeBytes / 1024, 2);

        return new self(
            message: "File size ({$sizeKb} KB) exceeds the maximum allowed limit of {$maxKb} KB.",
            status: 422,
            errorCode: 'IMAGE_FILE_TOO_LARGE',
            meta: ['size_kb' => $sizeKb, 'max_kb' => $maxKb]
        );
    }

    public static function dimensionsExceeded(int $width, int $height, int $maxWidth, int $maxHeight): self
    {
        return new self(
            message: "Image dimensions ({$width}x{$height}) exceed allowed maximum ({$maxWidth}x{$maxHeight}).",
            status: 422,
            errorCode: 'IMAGE_DIMENSIONS_EXCEEDED',
            meta: [
                'width' => $width,
                'height' => $height,
                'max_width' => $maxWidth,
                'max_height' => $maxHeight,
            ]
        );
    }

    public static function maliciousPayloadDetected(string $detail): self
    {
        return new self(
            message: 'Security violation: Potential malicious payload or script signature detected in image data. Upload rejected.',
            status: 422,
            errorCode: 'MALICIOUS_PAYLOAD_DETECTED',
            meta: ['reason' => $detail]
        );
    }

    public static function processingFailed(string $reason): self
    {
        return new self(
            message: "Failed to securely process and sanitize image: {$reason}",
            status: 500,
            errorCode: 'IMAGE_PROCESSING_FAILED',
            meta: ['error' => $reason]
        );
    }

    public static function storageFailed(string $disk, string $reason): self
    {
        return new self(
            message: "Failed to persist image to storage disk [{$disk}]: {$reason}",
            status: 500,
            errorCode: 'IMAGE_STORAGE_FAILED',
            meta: ['disk' => $disk, 'error' => $reason]
        );
    }

    public static function presetNotFound(string $preset): self
    {
        return new self(
            message: "Unknown image upload preset [{$preset}].",
            status: 400,
            errorCode: 'IMAGE_PRESET_NOT_FOUND',
            meta: ['preset' => $preset]
        );
    }
}
