<?php

namespace Modules\Auth\app\DTOs;

class VerifyEmailDTO
{
    public function __construct(
        public readonly string $token
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
        );
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
        ];
    }
}
