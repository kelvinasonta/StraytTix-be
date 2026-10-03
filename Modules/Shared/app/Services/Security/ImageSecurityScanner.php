<?php

namespace Modules\Shared\app\Services\Security;

use Modules\Shared\app\Exceptions\ImageUploadException;

class ImageSecurityScanner
{
    /**
     * Known malicious script patterns and signatures for polyglot detection.
     *
     * @var array<string>
     */
    protected array $maliciousSignatures = [
        '/<\?php/i',
        '/<\?=/i',
        '/<\s*script\b/i',
        '/language\s*=\s*["\']?php["\']?/i',
        '/__halt_compiler\s*\(/i',
        '/\b(?:passthru|shell_exec|system|exec|popen|proc_open)\s*\(/i',
        '/\bbase64_decode\s*\(/i',
        '/\beval\s*\(/i',
        '/\bassert\s*\(/i',
        '/<!--\s*#exec/i', // Server Side Includes (SSI) injection
    ];

    /**
     * Scan and thoroughly validate an image file against national security threat models.
     *
     * @param  array<string, array<string>>  $allowedMimes  Map of MIME type to allowed extensions
     * @return array{mime: string, extension: string, width: int, height: int, size: int, sha256: string}
     *
     * @throws ImageUploadException
     */
    public function scan(
        string $filePath,
        int $maxSizeBytes,
        int $maxWidth,
        int $maxHeight,
        array $allowedMimes,
        bool $scanPolyglot = true,
        ?int $explicitSize = null
    ): array {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw ImageUploadException::processingFailed('The image file does not exist or is unreadable.');
        }

        $realSize = filesize($filePath) ?: 0;
        $size = max($realSize, $explicitSize ?? 0);

        if ($size === 0) {
            throw ImageUploadException::invalidMimeType('empty_file');
        }

        // 1. Enforce strict size ceiling
        if ($size > $maxSizeBytes) {
            throw ImageUploadException::fileTooLarge($size, $maxSizeBytes);
        }

        // 2. Server-side magic byte inspection
        $detectedMime = $this->detectMimeType($filePath);
        if (! array_key_exists($detectedMime, $allowedMimes)) {
            throw ImageUploadException::invalidMimeType($detectedMime);
        }

        // Strict ban on vector / script formats (SVG, HTML, XML)
        if (in_array($detectedMime, ['image/svg+xml', 'image/svg', 'text/html', 'text/xml', 'application/xml'], true)) {
            throw ImageUploadException::invalidMimeType($detectedMime);
        }

        // 3. Decompression bomb & dimension guard
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false) {
            throw ImageUploadException::invalidMimeType('unrecognized_image_data');
        }

        [$width, $height] = $imageInfo;
        if ($width <= 0 || $height <= 0) {
            throw ImageUploadException::invalidMimeType('corrupted_image_dimensions');
        }

        if ($width > $maxWidth || $height > $maxHeight) {
            throw ImageUploadException::dimensionsExceeded($width, $height, $maxWidth, $maxHeight);
        }

        // Total pixel ceiling (e.g. 30 Megapixels max) to prevent memory exhaustion during decompression
        $maxPixels = max($maxWidth * $maxHeight, 30_000_000);
        if (($width * $height) > $maxPixels) {
            throw ImageUploadException::dimensionsExceeded($width, $height, $maxWidth, $maxHeight);
        }

        // 4. Polyglot and malicious binary payload inspection
        if ($scanPolyglot) {
            $this->scanForMaliciousSignatures($filePath);
        }

        $primaryExtension = $allowedMimes[$detectedMime][0] ?? 'jpg';
        $sha256 = hash_file('sha256', $filePath) ?: '';

        return [
            'mime' => $detectedMime,
            'extension' => $primaryExtension,
            'width' => $width,
            'height' => $height,
            'size' => $size,
            'sha256' => $sha256,
        ];
    }

    /**
     * Inspect file headers via finfo magic bytes.
     */
    protected function detectMimeType(string $filePath): string
    {
        $mime = false;

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $filePath);
            if ($mime === false || $mime === 'application/octet-stream') {
                $sample = @file_get_contents($filePath, false, null, 0, 4096);
                if ($sample !== false && $sample !== '') {
                    $bufMime = @finfo_buffer($finfo, $sample);
                    if (is_string($bufMime) && $bufMime !== '') {
                        $mime = $bufMime;
                    }
                }
            }
            @finfo_close($finfo);
        }

        if ($mime === false || ! is_string($mime) || $mime === '') {
            $mime = @mime_content_type($filePath);
        }

        return is_string($mime) && $mime !== '' ? strtolower($mime) : 'application/octet-stream';
    }

    /**
     * Scan binary stream for embedded web shell strings or polyglot code blocks.
     *
     * @throws ImageUploadException
     */
    protected function scanForMaliciousSignatures(string $filePath): void
    {
        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            throw ImageUploadException::processingFailed('Could not open file for security scanning.');
        }

        // Read in 64KB overlapping blocks to catch signatures spanning chunk boundaries
        $overlap = 512;
        $buffer = '';

        while (! feof($handle)) {
            $chunk = fread($handle, 65536);
            if ($chunk === false) {
                break;
            }

            $searchBuffer = $buffer.$chunk;

            foreach ($this->maliciousSignatures as $signature) {
                if (preg_match($signature, $searchBuffer, $matches)) {
                    fclose($handle);
                    throw ImageUploadException::maliciousPayloadDetected("Signature matched: {$matches[0]}");
                }
            }

            $buffer = substr($chunk, -$overlap);
        }

        fclose($handle);
    }
}
