<?php

namespace Modules\Events\app\DTOs;

use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;

class EventDTO
{
    /**
     * @param  array<string>|null  $event_category
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?string $event_country = null,
        public readonly string|EventType|null $event_type = EventType::PHYSICAL,
        public readonly ?string $event_format = null,
        public readonly ?array $event_category = null,
        public readonly string|EventStatus|null $status = EventStatus::DRAFT,
        public readonly string|EventVisibility|null $visibility = EventVisibility::PUBLIC,
        public readonly ?string $banner_url = null,
        public readonly bool $is_featured = false,
        public readonly ?array $meta = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $eventType = $data['eventType'] ?? $data['event_type'] ?? EventType::PHYSICAL->value;
        if ($eventType instanceof EventType) {
            $eventType = $eventType->value;
        }

        $status = $data['status'] ?? EventStatus::DRAFT->value;
        if ($status instanceof EventStatus) {
            $status = $status->value;
        }

        $visibility = $data['visibility'] ?? EventVisibility::PUBLIC->value;
        if ($visibility instanceof EventVisibility) {
            $visibility = $visibility->value;
        }

        $meta = $data['meta'] ?? [];
        if (isset($data['faqs'])) {
            $meta['faqs'] = $data['faqs'];
        }
        if (isset($data['galleryUrls'])) {
            $meta['galleryUrls'] = $data['galleryUrls'];
        } elseif (isset($data['gallery'])) {
            $meta['galleryUrls'] = $data['gallery'];
        }

        return new self(
            title: $data['title'],
            description: $data['description'] ?? null,
            event_country: $data['eventCountry'] ?? $data['event_country'] ?? null,
            event_type: $eventType,
            event_format: $data['eventFormat'] ?? $data['event_format'] ?? null,
            event_category: $data['eventCategory'] ?? $data['event_category'] ?? null,
            status: $status,
            visibility: $visibility,
            banner_url: $data['bannerUrl'] ?? $data['banner_url'] ?? null,
            is_featured: (bool) ($data['isFeatured'] ?? $data['is_featured'] ?? false),
            meta: ! empty($meta) ? $meta : null,
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'event_country' => $this->event_country,
            'event_type' => $this->event_type instanceof EventType ? $this->event_type->value : $this->event_type,
            'event_format' => $this->event_format,
            'event_category' => $this->event_category,
            'status' => $this->status instanceof EventStatus ? $this->status->value : $this->status,
            'visibility' => $this->visibility instanceof EventVisibility ? $this->visibility->value : $this->visibility,
            'banner_url' => $this->banner_url,
            'is_featured' => $this->is_featured,
            'meta' => $this->meta,
        ];
    }
}
