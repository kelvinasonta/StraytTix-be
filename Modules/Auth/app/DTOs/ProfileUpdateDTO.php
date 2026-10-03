<?php

namespace Modules\Auth\app\DTOs;

class ProfileUpdateDTO
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $phone = null,
        public readonly ?string $timezone = 'UTC',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            phone: $data['phone'] ?? null,
            timezone: $data['timezone'] ?? $data['time_zone'] ?? 'UTC',
        );
    }
}
