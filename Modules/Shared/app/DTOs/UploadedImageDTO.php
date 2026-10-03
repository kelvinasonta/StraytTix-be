<?php

namespace Modules\Shared\app\DTOs;

class UploadedImageDTO
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $path,
        public readonly string $disk,
        public readonly string $url,
        public readonly string $filename,
        public readonly string $mimeType,
        public readonly string $extension,
        public readonly int $sizeBytes,
        public readonly int $width,
        public readonly int $height,
        public readonly ?string $sha256 = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'disk' => $this->disk,
            'url' => $this->url,
            'filename' => $this->filename,
            'mime_type' => $this->mimeType,
            'extension' => $this->extension,
            'size_bytes' => $this->sizeBytes,
            'width' => $this->width,
            'height' => $this->height,
            'sha256' => $this->sha256,
            'meta' => $this->meta,
        ];
    }
}
