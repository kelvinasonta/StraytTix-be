<?php

namespace Modules\Shared\app\DTOs;

class ImageUploadOptions
{
    public function __construct(
        public readonly ?string $preset = null,
        public readonly ?string $disk = null,
        public readonly ?string $directory = null,
        public readonly ?string $convertTo = null,
        public readonly ?int $maxSizeBytes = null,
        public readonly ?int $maxWidth = null,
        public readonly ?int $maxHeight = null,
        public readonly int $quality = 85,
        public readonly string $visibility = 'public',
        public readonly bool $stripMetadata = true,
        public readonly bool $scanPolyglot = true,
        public readonly ?string $filenamePrefix = null,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function forPreset(string $preset, array $overrides = []): self
    {
        return new self(
            preset: $preset,
            disk: $overrides['disk'] ?? null,
            directory: $overrides['directory'] ?? null,
            convertTo: $overrides['convert_to'] ?? null,
            maxSizeBytes: isset($overrides['max_size_kb']) ? ((int) $overrides['max_size_kb']) * 1024 : ($overrides['max_size_bytes'] ?? null),
            maxWidth: $overrides['max_width'] ?? null,
            maxHeight: $overrides['max_height'] ?? null,
            quality: $overrides['quality'] ?? 85,
            visibility: $overrides['visibility'] ?? 'public',
            stripMetadata: $overrides['strip_metadata'] ?? true,
            scanPolyglot: $overrides['scan_polyglot'] ?? true,
            filenamePrefix: $overrides['filename_prefix'] ?? null,
        );
    }
}
