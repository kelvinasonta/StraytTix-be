<?php

namespace Modules\Events\app\DTOs;

class EventLocationDTO
{
    public function __construct(
        public readonly ?string $venue_name = null,
        public readonly ?string $venue_address = null,
        public readonly ?string $street_address = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $zip = null,
        public readonly ?string $landmark = null,
        public readonly ?float $longitude = null,
        public readonly ?float $latitude = null,
        public readonly ?string $online_meeting_url = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            venue_name: $data['venueName'] ?? $data['venue_name'] ?? null,
            venue_address: $data['venueAddress'] ?? $data['venue_address'] ?? null,
            street_address: $data['streetAddress'] ?? $data['street_address'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zip: $data['zip'] ?? null,
            landmark: $data['landmark'] ?? null,
            longitude: $data['longitude'] ?? null,
            latitude: $data['latitude'] ?? null,
            online_meeting_url: $data['onlineMeetingUrl'] ?? $data['online_meeting_url'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'street_address' => $this->street_address,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'landmark' => $this->landmark,
            'longitude' => $this->longitude,
            'latitude' => $this->latitude,
            'online_meeting_url' => $this->online_meeting_url,
        ];
    }

    public function hasLocation(): bool
    {
        return ! empty($this->venue_name)
            || ! empty($this->venue_address)
            || ! empty($this->street_address)
            || ! empty($this->city)
            || ! empty($this->state)
            || ! empty($this->zip)
            || ! empty($this->landmark)
            || $this->longitude !== null
            || $this->latitude !== null
            || ! empty($this->online_meeting_url);
    }
}
