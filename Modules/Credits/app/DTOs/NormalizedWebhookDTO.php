<?php

declare(strict_types=1);

namespace Modules\Credits\app\DTOs;

final readonly class NormalizedWebhookDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $gateway,
        public string $providerReference,
        public bool $isSuccessful,
        public int $amountKobo,
        public int $creditsAwarded,
        public ?string $walletUuid = null,
        public ?int $walletId = null,
        public array $metadata = [],
        public array $rawPayload = []
    ) {}
}
