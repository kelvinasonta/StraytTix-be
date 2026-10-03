<?php

namespace Modules\Auth\app\DTOs;

class RegisterDTO
{
    public function __construct(
        public readonly string $firstname,
        public readonly string $lastname,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $timezone,
        public readonly string $accountType,
        public readonly ?string $organizerType,
        public readonly ?string $organizationName,
        public readonly string $password,
    ) {}

    public static function fromArray(array $requestData): self
    {
        return new self(
            firstname: trim($requestData['first_name']),
            lastname: trim($requestData['last_name']),
            email: trim($requestData['email']),
            phone: isset($requestData['phone']) ? trim($requestData['phone']) : null,
            timezone: isset($requestData['timezone']) ? trim($requestData['timezone']) : 'UTC',
            accountType: isset($requestData['account_type']) ? trim($requestData['account_type']) : (isset($requestData['accounttype']) ? trim($requestData['accounttype']) : 'customer'),
            organizerType: isset($requestData['organizer_type']) ? trim($requestData['organizer_type']) : (isset($requestData['organizerType']) ? trim($requestData['organizerType']) : null),
            organizationName: isset($requestData['organization_name']) ? trim($requestData['organization_name']) : (isset($requestData['organizationName']) ? trim($requestData['organizationName']) : null),
            password: $requestData['password'],
        );
    }

    public function toArray(): array
    {
        return [
            'first_name' => $this->firstname,
            'last_name' => $this->lastname,
            'email' => $this->email,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'account_type' => $this->accountType,
            'organizer_type' => $this->organizerType,
            'organization_name' => $this->organizationName,
            'password' => $this->password,
        ];
    }
}
