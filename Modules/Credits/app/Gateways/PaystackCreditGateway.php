<?php

declare(strict_types=1);

namespace Modules\Credits\app\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Credits\app\Contracts\CreditPaymentGatewayInterface;
use Modules\Credits\app\DTOs\GatewayCheckoutDTO;
use Modules\Credits\app\DTOs\NormalizedWebhookDTO;
use Modules\Credits\app\Models\OrganizerCreditWallet;
use RuntimeException;

class PaystackCreditGateway implements CreditPaymentGatewayInterface
{
    public function getIdentifier(): string
    {
        return 'paystack';
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function initializeCheckout(
        OrganizerCreditWallet $wallet,
        int $credits,
        int $amountKobo,
        string $callbackUrl,
        string $customerEmail,
        array $metadata = []
    ): GatewayCheckoutDTO {
        $secretKey = config('credits.gateways.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY');
        $baseUrl = rtrim((string) (config('credits.gateways.paystack.base_url') ?: 'https://api.paystack.co'), '/');
        $reference = 'CREDIT-PSTK-'.bin2hex(random_bytes(8)).'-'.time();

        $payload = [
            'email' => $customerEmail,
            'amount' => $amountKobo,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => array_merge($metadata, [
                'wallet_id' => $wallet->id,
                'wallet_uuid' => $wallet->uuid,
                'credits_requested' => $credits,
                'type' => 'organizer_credit_topup',
                'gateway' => $this->getIdentifier(),
            ]),
        ];

        // Testing fallback simulation if credentials are not configured in test environment
        if (app()->environment('testing') && empty($secretKey)) {
            return new GatewayCheckoutDTO(
                gateway: $this->getIdentifier(),
                reference: $reference,
                authorizationUrl: "https://checkout.paystack.com/{$reference}",
                amountKobo: $amountKobo,
                credits: $credits,
                accessCode: 'pstk_mock_access_code',
                rawResponse: ['status' => true, 'mock' => true]
            );
        }

        if (empty($secretKey)) {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        $response = Http::withToken($secretKey)
            ->timeout(15)
            ->post("{$baseUrl}/transaction/initialize", $payload);

        if (! $response->successful() || ! ($response->json('status') === true)) {
            Log::error('Paystack Credit Top-up initialization failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            throw new RuntimeException($response->json('message') ?: 'Failed to initialize payment with Paystack.');
        }

        $data = $response->json('data') ?? [];

        return new GatewayCheckoutDTO(
            gateway: $this->getIdentifier(),
            reference: $data['reference'] ?? $reference,
            authorizationUrl: $data['authorization_url'] ?? '',
            amountKobo: $amountKobo,
            credits: $credits,
            accessCode: $data['access_code'] ?? null,
            rawResponse: $data
        );
    }

    public function verifyWebhookSignature(string $rawPayload, string $signature): bool
    {
        $secretKey = config('paystack.secret_key')
            ?: config('credits.gateways.paystack.secret_key')
            ?: env('PAYSTACK_SECRET_KEY');

        if (empty($secretKey)) {
            Log::warning('Paystack secret key is empty; cannot verify webhook signature.');

            return false;
        }

        $computed = hash_hmac('sha512', $rawPayload, $secretKey);

        return hash_equals($computed, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhookEvent(array $payload): NormalizedWebhookDTO
    {
        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];
        $dataStatus = strtolower((string) ($data['status'] ?? ''));
        // In Paystack webhooks, charge.success is the canonical event; data.status may be 'success' or omitted
        $isSuccessful = ($event === 'charge.success') && (empty($dataStatus) || $dataStatus === 'success');

        $metadata = $data['metadata'] ?? [];
        $amountKobo = (int) ($data['amount'] ?? 0);
        $reference = (string) ($data['reference'] ?? '');

        // Calculate credits awarded (from metadata or default conversion rate)
        $ratePerCreditKobo = (int) (config('credits.rate_per_credit_kobo') ?: 1000);
        $creditsAwarded = isset($metadata['credits_purchased'])
            ? (int) $metadata['credits_purchased']
            : (isset($metadata['credits_requested'])
                ? (int) $metadata['credits_requested']
                : (int) floor($amountKobo / max(1, $ratePerCreditKobo)));

        return new NormalizedWebhookDTO(
            gateway: $this->getIdentifier(),
            providerReference: $reference,
            isSuccessful: $isSuccessful,
            amountKobo: $amountKobo,
            creditsAwarded: $creditsAwarded,
            walletUuid: $metadata['wallet_uuid'] ?? null,
            walletId: isset($metadata['wallet_id']) ? (int) $metadata['wallet_id'] : null,
            metadata: $metadata,
            rawPayload: $payload
        );
    }
}
