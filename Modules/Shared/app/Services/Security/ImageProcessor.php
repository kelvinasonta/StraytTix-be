<?php

namespace Modules\Shared\app\Services\Security;

use GdImage;
use Modules\Shared\app\Exceptions\ImageUploadException;

class ImageProcessor
{
    /**
     * Securely re-encode the image to strip metadata (EXIF/GPS/comments) and polyglot payloads.
     *
     * @param  string  $sourcePath  Local path to validated source file
     * @param  string  $sourceMime  Verified MIME type
     * @param  string  $targetFormat  Output format: 'webp', 'jpg', 'jpeg', or 'png'
     * @param  int  $quality  Compression quality (1-100)
     * @param  int|null  $maxWidth  Optional downscaling target width
     * @param  int|null  $maxHeight  Optional downscaling target height
     * @return array{path: string, mime: string, extension: string, width: int, height: int, size: int}
     *
     * @throws ImageUploadException
     */
    public function processAndSanitize(
        string $sourcePath,
        string $sourceMime,
        string $targetFormat = 'webp',
        int $quality = 85,
        ?int $maxWidth = null,
        ?int $maxHeight = null
    ): array {
        $sourceImage = $this->createGdImageFromSource($sourcePath, $sourceMime);

        try {
            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            [$targetWidth, $targetHeight] = $this->calculateTargetDimensions(
                $origWidth,
                $origHeight,
                $maxWidth,
                $maxHeight
            );

            // Create fresh truecolor canvas
            $cleanCanvas = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($cleanCanvas === false) {
                throw ImageUploadException::processingFailed('Failed to initialize sanitized image canvas.');
            }

            // Preserve alpha channel for transparent PNGs/WebPs
            $this->configureAlphaTransparency($cleanCanvas, $targetFormat);

            // Re-sample pixels into clean canvas (strips all EXIF, polyglots, and comments)
            $copied = imagecopyresampled(
                $cleanCanvas,
                $sourceImage,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $origWidth,
                $origHeight
            );

            if (! $copied) {
                imagedestroy($cleanCanvas);
                throw ImageUploadException::processingFailed('Failed to resample image pixels.');
            }

            // Write to fresh temporary file
            $tempSanitizedPath = tempnam(sys_get_temp_dir(), 'sanitized_img_');
            if ($tempSanitizedPath === false) {
                imagedestroy($cleanCanvas);
                throw ImageUploadException::processingFailed('Failed to allocate temporary storage for sanitized image.');
            }

            $outputFormat = strtolower($targetFormat);
            $saved = $this->saveGdImage($cleanCanvas, $tempSanitizedPath, $outputFormat, $quality);
            imagedestroy($cleanCanvas);

            if (! $saved) {
                @unlink($tempSanitizedPath);
                throw ImageUploadException::processingFailed("Failed to encode sanitized image to format [{$outputFormat}].");
            }

            $outSize = filesize($tempSanitizedPath) ?: 0;
            $outMime = $this->resolveMimeForFormat($outputFormat);
            $outExt = $outputFormat === 'jpeg' ? 'jpg' : $outputFormat;

            return [
                'path' => $tempSanitizedPath,
                'mime' => $outMime,
                'extension' => $outExt,
                'width' => $targetWidth,
                'height' => $targetHeight,
                'size' => $outSize,
            ];
        } finally {
            if ($sourceImage instanceof GdImage || is_resource($sourceImage)) {
                imagedestroy($sourceImage);
            }
        }
    }

    /**
     * Create GD resource safely from source file.
     *
     * @throws ImageUploadException
     */
    protected function createGdImageFromSource(string $filePath, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/webp' => @imagecreatefromwebp($filePath),
            default => false,
        };

        if ($image === false) {
            throw ImageUploadException::processingFailed("Could not decode image with MIME type [{$mime}].");
        }

        return $image;
    }

    /**
     * Calculate proportional dimensions if downscaling is required.
     *
     * @return array{0: int, 1: int}
     */
    protected function calculateTargetDimensions(
        int $origWidth,
        int $origHeight,
        ?int $maxWidth,
        ?int $maxHeight
    ): array {
        if (! $maxWidth && ! $maxHeight) {
            return [$origWidth, $origHeight];
        }

        $targetWidth = $origWidth;
        $targetHeight = $origHeight;

        $scaleW = $maxWidth ? $maxWidth / $origWidth : 1;
        $scaleH = $maxHeight ? $maxHeight / $origHeight : 1;
        $scale = min($scaleW, $scaleH);

        if ($scale < 1) {
            $targetWidth = max(1, (int) round($origWidth * $scale));
            $targetHeight = max(1, (int) round($origHeight * $scale));
        }

        return [$targetWidth, $targetHeight];
    }

    /**
     * Setup alpha blending and transparency settings.
     */
    protected function configureAlphaTransparency(GdImage $canvas, string $format): void
    {
        if (in_array(strtolower($format), ['png', 'webp'], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($canvas, 0, 0, imagesx($canvas), imagesy($canvas), $transparent);
            }
        }
    }

    /**
     * Output clean image to file.
     */
    protected function saveGdImage(GdImage $image, string $destination, string $format, int $quality): bool
    {
        return match ($format) {
            'webp' => imagewebp($image, $destination, max(1, min(100, $quality))),
            'jpg', 'jpeg' => imagejpeg($image, $destination, max(1, min(100, $quality))),
            'png' => imagepng($image, $destination, (int) max(0, min(9, round((100 - $quality) / 11)))),
            default => imagewebp($image, $destination, 85),
        };
    }

    /**
     * Resolve MIME type for output format.
     */
    protected function resolveMimeForFormat(string $format): string
    {
        return match (strtolower($format)) {
            'webp' => 'image/webp',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/webp',
        };
    }
}
