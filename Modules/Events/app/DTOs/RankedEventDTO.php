<?php

namespace Modules\Events\app\DTOs;

use Modules\Events\app\Models\Event;

readonly class RankedEventDTO
{
    /**
     * @param  array<string, float>  $signals
     */
    public function __construct(
        public Event $event,
        public float $finalScore,
        public string $primaryCategory,
        public array $signals = [],
        public bool $isSponsored = false,
        public ?float $distanceKm = null,
    ) {}

    public function withSponsored(bool $isSponsored): self
    {
        return new self(
            event: $this->event,
            finalScore: $this->finalScore,
            primaryCategory: $this->primaryCategory,
            signals: $this->signals,
            isSponsored: $isSponsored,
            distanceKm: $this->distanceKm,
        );
    }
}
