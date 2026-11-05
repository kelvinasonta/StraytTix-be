<?php

declare(strict_types=1);

namespace Modules\Credits\app\DTOs;

final readonly class GatewayCheckoutDTO
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $gateway,
        public string $reference,
        public string $authorizationUrl,
        public int $amountKobo,
        public int $credits,
        public ?string $accessCode = null,
        public array $rawResponse = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'reference' => $this->reference,
            'authorization_url' => $this->authorizationUrl,
            'checkout_url' => $this->authorizationUrl,
            'amount_kobo' => $this->amountKobo,
            'currency' => 'NGN',
            'credits' => $this->credits,
            'access_code' => $this->accessCode,
        ];
    }
}
