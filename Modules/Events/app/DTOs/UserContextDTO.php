<?php

namespace Modules\Events\app\DTOs;

use Illuminate\Http\Request;

readonly class UserContextDTO
{
    /**
     * @param  array<string, float>  $categoryAffinities
     */
    public function __construct(
        public ?int $userId,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $city,
        public ?string $state,
        public string $country,
        public array $categoryAffinities = [],
        public ?string $preferredPriceTier = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $user = $request->user();

        $lat = $request->filled('lat') ? (float) $request->input('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->input('lng') : null;

        // Fallback to headers or query params
        $city = $request->input('city', $request->header('X-User-City', $request->header('CF-IPCity', 'Lagos')));
        $state = $request->input('state', $request->header('X-User-State', $request->header('CF-Region', 'Lagos')));
        $country = $request->header('CF-IPCountry', 'NG');

        return new self(
            userId: $user?->id,
            latitude: $lat,
            longitude: $lng,
            city: ! empty($city) ? trim($city) : null,
            state: ! empty($state) ? trim($state) : null,
            country: ! empty($country) ? trim($country) : 'NG',
            categoryAffinities: [],
            preferredPriceTier: null,
        );
    }

    /**
     * @param  array<string, float>  $affinities
     */
    public function withAffinities(array $affinities): self
    {
        return new self(
            userId: $this->userId,
            latitude: $this->latitude,
            longitude: $this->longitude,
            city: $this->city,
            state: $this->state,
            country: $this->country,
            categoryAffinities: $affinities,
            preferredPriceTier: $this->preferredPriceTier,
        );
    }
}
