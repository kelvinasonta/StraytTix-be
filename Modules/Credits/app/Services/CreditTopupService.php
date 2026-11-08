<?php

declare(strict_types=1);

namespace Modules\Credits\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Credits\app\DTOs\GatewayCheckoutDTO;
use Modules\Credits\app\Enums\CreditTxReason;
use Modules\Credits\app\Models\CreditTopupTransaction;
use Modules\Credits\app\Models\OrganizerCreditWallet;
use RuntimeException;

class CreditTopupService
{
    public function __construct(
        protected CreditLedgerService $ledgerService,
        protected CreditGatewayManager $gatewayManager
    ) {}

    /**
     * Initialize a credit top-up checkout session using the resolved gateway.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function initializeTopup(
        OrganizerCreditWallet $wallet,
        int $credits,
        string $callbackUrl,
        string $customerEmail,
        ?string $gatewayName = null,
        array $metadata = []
    ): GatewayCheckoutDTO {
        $gateway = $this->gatewayManager->driver($gatewayName);
        $ratePerCreditKobo = (int) (config('credits.rate_per_credit_kobo') ?: 1000);
        $amountKobo = $credits * $ratePerCreditKobo;

        $checkout = $gateway->initializeCheckout(
            wallet: $wallet,
            credits: $credits,
            amountKobo: $amountKobo,
            callbackUrl: $callbackUrl,
            customerEmail: $customerEmail,
            metadata: $metadata
        );

        // Pre-record pending topup transaction for tracing and idempotency lock
        CreditTopupTransaction::create([
            'wallet_id' => $wallet->id,
            'provider' => $gateway->getIdentifier(),
            'provider_ref' => $checkout->reference,
            'amount_kobo' => $amountKobo,
            'credits_awarded' => $credits,
            'status' => 'PENDING',
            'metadata' => array_merge($metadata, [
                'authorization_url' => $checkout->authorizationUrl,
            ]),
        ]);

        return $checkout;
    }

    /**
     * Process an incoming webhook for any supported gateway.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function processWebhook(string $gatewayName, array $payload, string $signature, string $rawPayload = ''): array
    {
        $gateway = $this->gatewayManager->driver($gatewayName);

        // 1. Verify Webhook Signature
        $rawToVerify = ! empty($rawPayload) ? $rawPayload : (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (! $gateway->verifyWebhookSignature($rawToVerify, $signature)) {
            // Fallback standard json_encode formatting check
            if (! $gateway->verifyWebhookSignature((string) json_encode($payload), $signature)) {
                Log::warning("Invalid {$gateway->getIdentifier()} webhook signature received.");

                return [
                    'status' => 'invalid_signature',
                    'message' => "Invalid webhook signature for gateway {$gateway->getIdentifier()}.",
                ];
            }
        }

        // 2. Parse & Normalize Event
        $eventDto = $gateway->parseWebhookEvent($payload);

        if (! $eventDto->isSuccessful) {
            return [
                'status' => 'ignored',
                'message' => "Webhook event for {$eventDto->providerReference} ignored (non-successful state).",
            ];
        }

        if (empty($eventDto->providerReference)) {
            return [
                'status' => 'error',
                'message' => 'Missing provider transaction reference.',
            ];
        }

        // 3. Atomically Ingest & Credit Wallet with Idempotency Guard
        return DB::transaction(function () use ($eventDto) {
            /** @var CreditTopupTransaction $topupTx */
            $topupTx = CreditTopupTransaction::where('provider_ref', $eventDto->providerReference)
                ->lockForUpdate()
                ->first();

            // Resolve wallet
            $wallet = null;
            if ($topupTx) {
                $wallet = OrganizerCreditWallet::where('id', $topupTx->wallet_id)->lockForUpdate()->first();
            } elseif ($eventDto->walletId) {
                $wallet = OrganizerCreditWallet::where('id', $eventDto->walletId)->lockForUpdate()->first();
            } elseif ($eventDto->walletUuid) {
                $wallet = OrganizerCreditWallet::where('uuid', $eventDto->walletUuid)->lockForUpdate()->first();
            }

            if (! $wallet) {
                Log::error("Credit Top-up webhook: Wallet not found for reference {$eventDto->providerReference}");

                throw new RuntimeException("Wallet not found for transaction reference {$eventDto->providerReference}");
            }

            // Create or update topup transaction row
            if (! $topupTx) {
                $topupTx = CreditTopupTransaction::create([
                    'wallet_id' => $wallet->id,
                    'provider' => $eventDto->gateway,
                    'provider_ref' => $eventDto->providerReference,
                    'amount_kobo' => $eventDto->amountKobo,
                    'credits_awarded' => $eventDto->creditsAwarded,
                    'status' => 'PENDING',
                    'metadata' => $eventDto->metadata,
                ]);
            }

            // Idempotency: Do not process twice
            if ($topupTx->isSuccess()) {
                Log::info("Credit top-up reference {$eventDto->providerReference} already processed.");

                return [
                    'status' => 'already_processed',
                    'credits' => $topupTx->credits_awarded,
                    'balance' => $wallet->balance,
                ];
            }

            // Credit the Wallet
            $idempotencyKey = "topup_{$eventDto->gateway}_{$eventDto->providerReference}";
            $ledgerEntry = $this->ledgerService->creditWallet(
                wallet: $wallet,
                amount: $eventDto->creditsAwarded,
                reason: CreditTxReason::TOPUP,
                idempotencyKey: $idempotencyKey,
                reference: $eventDto->providerReference,
                description: "Credit top-up via {$eventDto->gateway} ({$eventDto->creditsAwarded} credits)",
                metadata: [
                    'gateway' => $eventDto->gateway,
                    'provider_ref' => $eventDto->providerReference,
                    'amount_kobo' => $eventDto->amountKobo,
                ]
            );

            // Mark transaction as SUCCESS
            $topupTx->update([
                'status' => 'SUCCESS',
                'amount_kobo' => $eventDto->amountKobo > 0 ? $eventDto->amountKobo : $topupTx->amount_kobo,
                'credits_awarded' => $eventDto->creditsAwarded > 0 ? $eventDto->creditsAwarded : $topupTx->credits_awarded,
                'metadata' => array_merge($topupTx->metadata ?? [], [
                    'ledger_uuid' => $ledgerEntry->uuid,
                    'processed_at' => now()->toIso8601String(),
                ]),
            ]);

            return [
                'status' => 'success',
                'credits_awarded' => $eventDto->creditsAwarded,
                'new_balance' => $wallet->fresh()->balance,
                'reference' => $eventDto->providerReference,
            ];
        });
    }

    /**
     * Synchronously verify a transaction using the provider API and credit the wallet.
     * Used when the organizer returns to the browser redirect URL.
     *
     * @return array<string, mixed>
     */
    public function verifyAndSettle(OrganizerCreditWallet $wallet, string $reference, ?string $gatewayName = null): array
    {
        /** @var CreditTopupTransaction|null $topupTx */
        $topupTx = CreditTopupTransaction::where('provider_ref', $reference)->first();

        // If already completed via webhook, return immediately
        if ($topupTx && $topupTx->isSuccess()) {
            return [
                'status' => 'success',
                'already_processed' => true,
                'credits_awarded' => $topupTx->credits_awarded,
                'balance' => $wallet->fresh()->balance,
                'reference' => $reference,
            ];
        }

        $resolvedGateway = $topupTx ? $topupTx->provider : ($gatewayName ?: $this->gatewayManager->getDefaultDriverName());
        $gateway = $this->gatewayManager->driver($resolvedGateway);

        $verification = $gateway->verifyTransaction($reference);

        if (! ($verification['is_successful'] ?? false)) {
            if ($topupTx) {
                $topupTx->update(['status' => 'FAILED']);
            }

            return [
                'status' => 'failed',
                'message' => $verification['message'] ?? 'Payment verification failed on gateway.',
                'reference' => $reference,
            ];
        }

        $creditsAwarded = (int) ($verification['credits_awarded'] ?? 0);
        $amountKobo = (int) ($verification['amount_kobo'] ?? 0);

        return DB::transaction(function () use ($wallet, $topupTx, $resolvedGateway, $reference, $creditsAwarded, $amountKobo, $verification) {
            $lockedWallet = OrganizerCreditWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if (! $topupTx) {
                $topupTx = CreditTopupTransaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'provider' => $resolvedGateway,
                    'provider_ref' => $reference,
                    'amount_kobo' => $amountKobo,
                    'credits_awarded' => $creditsAwarded,
                    'status' => 'PENDING',
                    'metadata' => $verification['raw_data'] ?? [],
                ]);
            } else {
                $topupTx = CreditTopupTransaction::where('id', $topupTx->id)->lockForUpdate()->firstOrFail();
            }

            if ($topupTx->isSuccess()) {
                return [
                    'status' => 'success',
                    'already_processed' => true,
                    'credits_awarded' => $topupTx->credits_awarded,
                    'balance' => $lockedWallet->balance,
                    'reference' => $reference,
                ];
            }

            $idempotencyKey = "topup_{$resolvedGateway}_{$reference}";
            $ledgerEntry = $this->ledgerService->creditWallet(
                wallet: $lockedWallet,
                amount: $creditsAwarded,
                reason: CreditTxReason::TOPUP,
                idempotencyKey: $idempotencyKey,
                reference: $reference,
                description: "Credit top-up via {$resolvedGateway} ({$creditsAwarded} credits)",
                metadata: [
                    'gateway' => $resolvedGateway,
                    'provider_ref' => $reference,
                    'amount_kobo' => $amountKobo,
                    'verified_via' => 'direct_verification_api',
                ]
            );

            $topupTx->update([
                'status' => 'SUCCESS',
                'amount_kobo' => $amountKobo > 0 ? $amountKobo : $topupTx->amount_kobo,
                'credits_awarded' => $creditsAwarded > 0 ? $creditsAwarded : $topupTx->credits_awarded,
                'metadata' => array_merge($topupTx->metadata ?? [], [
                    'ledger_uuid' => $ledgerEntry->uuid,
                    'verified_at' => now()->toIso8601String(),
                ]),
            ]);

            return [
                'status' => 'success',
                'credits_awarded' => $creditsAwarded,
                'balance' => $lockedWallet->fresh()->balance,
                'reference' => $reference,
            ];
        });
    }

    /**
     * Backward-compatibility proxy for Paystack specific calls.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function processPaystackWebhook(array $payload, string $signature): array
    {
        return $this->processWebhook('paystack', $payload, $signature);
    }
}
