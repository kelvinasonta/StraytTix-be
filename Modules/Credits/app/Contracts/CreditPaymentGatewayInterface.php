<?php

declare(strict_types=1);

namespace Modules\Credits\app\Contracts;

use Modules\Credits\app\DTOs\GatewayCheckoutDTO;
use Modules\Credits\app\DTOs\NormalizedWebhookDTO;
use Modules\Credits\app\Models\OrganizerCreditWallet;

interface CreditPaymentGatewayInterface
{
    /**
     * Unique gateway identifier (e.g. 'paystack', 'flutterwave', 'stripe').
     */
    public function getIdentifier(): string;

    /**
     * Initialize a checkout session with the provider.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function initializeCheckout(
        OrganizerCreditWallet $wallet,
        int $credits,
        int $amountKobo,
        string $callbackUrl,
        string $customerEmail,
        array $metadata = []
    ): GatewayCheckoutDTO;

    /**
     * Verify the cryptographic webhook signature from the incoming HTTP request.
     */
    public function verifyWebhookSignature(string $rawPayload, string $signature): bool;

    /**
     * Normalize the webhook payload into a uniform standard DTO.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhookEvent(array $payload): NormalizedWebhookDTO;

    /**
     * Synchronously verify transaction status with provider API when client redirects back.
     *
     * @param  array<string, mixed>  $options
     * @return array{is_successful: bool, amount_kobo: int, credits_awarded: int, status: string, message: string, raw_data: array<string, mixed>}
     */
    public function verifyTransaction(string $reference, array $options = []): array;
}

