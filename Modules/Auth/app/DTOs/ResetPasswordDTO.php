<?php

namespace Modules\Auth\app\DTOs;

class ResetPasswordDTO
{
    public function __construct(
        public readonly string $token,
        public readonly string $password
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
            password: $data['password']
        );
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'password' => $this->password,
        ];
    }
}
