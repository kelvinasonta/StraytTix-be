<?php

namespace Modules\Credits\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Credits\app\Enums\CreditTxReason;
use Modules\Credits\app\Models\CreditTopupTransaction;
use Modules\Credits\app\Models\OrganizerCreditWallet;

class CreditTopupService
{
    public function __construct(
        protected CreditLedgerService $ledgerService
    ) {}

    /**
     * Verify Paystack HMAC SHA-512 webhook signature.
     */
    public function verifySignature(string $rawPayload, string $signature): bool
    {
        $secret = config('paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY');

        if (empty($secret)) {
            Log::warning('Paystack secret key is not set; cannot verify webhook signature.');

            return false;
        }

        $computedSignature = hash_hmac('sha512', $rawPayload, $secret);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * Process Paystack Webhook payload for credit top-ups.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function processPaystackWebhook(array $payload, string $signature): array
    {
        // 1. Verify signature if signature is passed
        $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (! $this->verifySignature((string) $rawPayload, $signature)) {
            // Also check standard json_encode in case formatting differs
            if (! $this->verifySignature(json_encode($payload), $signature)) {
                Log::warning('Invalid Paystack webhook signature received.');

                return [
                    'status' => 'invalid_signature',
                    'message' => 'Invalid webhook signature.',
                ];
            }
        }

        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        // 2. Only process charge.success events
        if ($event !== 'charge.success') {
            return [
                'status' => 'ignored',
                'event' => $event,
            ];
        }

        $reference = $data['reference'] ?? null;
        if (! $reference) {
            return [
                'status' => 'error',
                'message' => 'Missing transaction reference.',
            ];
        }

        // 3. Extract wallet information and calculate credits
        $metadata = $data['metadata'] ?? [];
        $walletId = $metadata['wallet_id'] ?? null;
        $organizerId = $metadata['organizer_id'] ?? null;

        // Find wallet by wallet_id or organizer_id
        $wallet = null;
        if ($walletId) {
            $wallet = OrganizerCreditWallet::find($walletId);
        } elseif ($organizerId) {
            $wallet = OrganizerCreditWallet::where('organizer_id', $organizerId)->first();
        }

        if (! $wallet) {
            Log::error("Unable to find organizer wallet for Paystack topup reference {$reference}", [
                'metadata' => $metadata,
            ]);

            return [
                'status' => 'error',
                'message' => 'Wallet not found for transaction metadata.',
            ];
        }

        $amountKobo = (int) ($data['amount'] ?? 0);

        // Determine credits awarded:
        // Use credits_purchased in metadata if explicitly specified,
        // otherwise default to standard rate (1 credit = 1000 kobo / 10 NGN)
        $creditsAwarded = isset($metadata['credits_purchased'])
            ? (int) $metadata['credits_purchased']
            : (int) floor($amountKobo / 1000);

        if ($creditsAwarded <= 0) {
            Log::warning("Zero or negative credits awarded for topup {$reference} (amount: {$amountKobo} kobo)");

            return [
                'status' => 'error',
                'message' => 'Invalid credit conversion amount.',
            ];
        }

        // 4. Idempotency guard and atomic transaction
        return DB::transaction(function () use ($wallet, $reference, $amountKobo, $creditsAwarded, $data, $metadata): array {
            /** @var CreditTopupTransaction $topup */
            $topup = CreditTopupTransaction::firstOrCreate(
                ['provider_ref' => $reference],
                [
                    'wallet_id' => $wallet->id,
                    'provider' => 'paystack',
                    'amount_kobo' => $amountKobo,
                    'credits_awarded' => $creditsAwarded,
                    'status' => 'PENDING',
                    'metadata' => $data,
                ]
            );

            // Re-lock record to prevent race conditions
            $topup = CreditTopupTransaction::where('id', $topup->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($topup->status === 'SUCCESS') {
                return [
                    'status' => 'already_processed',
                    'reference' => $reference,
                    'credits_awarded' => $topup->credits_awarded,
                ];
            }

            // Credit the wallet
            $idempotencyKey = 'topup_'.$reference;
            $ledgerEntry = $this->ledgerService->creditWallet(
                wallet: $wallet,
                amount: $creditsAwarded,
                reason: CreditTxReason::TOPUP,
                idempotencyKey: $idempotencyKey,
                reference: $reference,
                description: sprintf('Credit top-up via Paystack (%s credits)', number_format($creditsAwarded)),
                metadata: [
                    'provider' => 'paystack',
                    'provider_ref' => $reference,
                    'amount_kobo' => $amountKobo,
                    'topup_id' => $topup->id,
                    'custom_metadata' => $metadata,
                ]
            );

            // Mark topup successful
            $topup->update([
                'status' => 'SUCCESS',
                'metadata' => array_merge($topup->metadata ?? [], [
                    'ledger_id' => $ledgerEntry->id,
                    'processed_at' => now()->toIso8601String(),
                ]),
            ]);

            return [
                'status' => 'success',
                'reference' => $reference,
                'credits_awarded' => $creditsAwarded,
                'new_balance' => $ledgerEntry->balance_after,
            ];
        });
    }
}
