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
}
