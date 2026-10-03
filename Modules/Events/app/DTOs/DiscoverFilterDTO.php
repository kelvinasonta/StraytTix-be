<?php

namespace Modules\Events\app\DTOs;

readonly class DiscoverFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $category = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $eventType = null,
        public ?string $date = null,
        public ?string $priceRange = null,
        public ?float $radiusKm = null,
        public string $sort = 'recommended',
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            search: ! empty($data['query']) ? trim($data['query']) : (! empty($data['search']) ? trim($data['search']) : null),
            category: ! empty($data['category']) ? trim($data['category']) : null,
            city: ! empty($data['city']) ? trim($data['city']) : null,
            state: ! empty($data['state']) ? trim($data['state']) : null,
            eventType: ! empty($data['event_type']) ? trim($data['event_type']) : null,
            date: ! empty($data['date']) ? trim($data['date']) : null,
            priceRange: ! empty($data['price_range']) ? trim($data['price_range']) : null,
            radiusKm: isset($data['radius']) ? (float) $data['radius'] : null,
            sort: ! empty($data['sort']) ? trim($data['sort']) : 'recommended',
            page: max(1, (int) ($data['page'] ?? 1)),
            perPage: min(50, max(1, (int) ($data['per_page'] ?? 20))),
        );
    }
}
