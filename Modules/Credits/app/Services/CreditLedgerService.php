<?php

namespace Modules\Credits\app\Services;

use Illuminate\Support\Facades\DB;
use Modules\Credits\app\Enums\CreditTxReason;
use Modules\Credits\app\Enums\CreditTxType;
use Modules\Credits\app\Enums\ReservationStatus;
use Modules\Credits\app\Exceptions\InsufficientCreditsException;
use Modules\Credits\app\Exceptions\InvalidReservationException;
use Modules\Credits\app\Models\CreditLedger;
use Modules\Credits\app\Models\CreditReservation;
use Modules\Credits\app\Models\OrganizerCreditWallet;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Models\Organizer;

class CreditLedgerService
{
    /**
     * Get or create the credit wallet for an organizer.
     */
    public function getOrCreateWallet(Organizer $organizer): OrganizerCreditWallet
    {
        return OrganizerCreditWallet::firstOrCreate(
            ['organizer_id' => $organizer->id],
            [
                'balance' => 0,
                'reserved_balance' => 0,
                'currency' => 'NGN',
                'version' => 1,
            ]
        );
    }

    /**
     * Credit the organizer's wallet and record a ledger entry.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function creditWallet(
        OrganizerCreditWallet $wallet,
        int $amount,
        CreditTxReason $reason,
        string $idempotencyKey,
        string $reference,
        string $description,
        array $metadata = [],
        ?Event $event = null
    ): CreditLedger {
        return DB::transaction(function () use ($wallet, $amount, $reason, $idempotencyKey, $reference, $description, $metadata, $event): CreditLedger {
            // Check idempotency first: if already processed, return existing ledger entry
            $existing = CreditLedger::where('wallet_id', $wallet->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            // Pessimistic Row Lock
            /** @var OrganizerCreditWallet $lockedWallet */
            $lockedWallet = OrganizerCreditWallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = $lockedWallet->balance;
            $balanceAfter = $balanceBefore + $amount;

            $lockedWallet->update([
                'balance' => $balanceAfter,
                'version' => $lockedWallet->version + 1,
            ]);

            return CreditLedger::create([
                'wallet_id' => $lockedWallet->id,
                'organizer_id' => $lockedWallet->organizer_id,
                'event_id' => $event?->id,
                'type' => CreditTxType::CREDIT,
                'reason' => $reason,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'idempotency_key' => $idempotencyKey,
                'reference' => $reference,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Synchronously debit the organizer's wallet and record a ledger entry.
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws InsufficientCreditsException
     */
    public function debitCreditsSync(
        OrganizerCreditWallet $wallet,
        int $amount,
        CreditTxReason $reason,
        string $idempotencyKey,
        string $reference,
        string $description,
        array $metadata = [],
        ?Event $event = null
    ): CreditLedger {
        return DB::transaction(function () use ($wallet, $amount, $reason, $idempotencyKey, $reference, $description, $metadata, $event): CreditLedger {
            // Check idempotency first: if already processed, return existing ledger entry
            $existing = CreditLedger::where('wallet_id', $wallet->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            // Pessimistic Row Lock
            /** @var OrganizerCreditWallet $lockedWallet */
            $lockedWallet = OrganizerCreditWallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $available = $lockedWallet->balance - $lockedWallet->reserved_balance;
            if ($available < $amount) {
                throw new InsufficientCreditsException($amount, $available);
            }

            $balanceBefore = $lockedWallet->balance;
            $balanceAfter = $balanceBefore - $amount;

            $lockedWallet->update([
                'balance' => $balanceAfter,
                'version' => $lockedWallet->version + 1,
            ]);

            return CreditLedger::create([
                'wallet_id' => $lockedWallet->id,
                'organizer_id' => $lockedWallet->organizer_id,
                'event_id' => $event?->id,
                'type' => CreditTxType::DEBIT,
                'reason' => $reason,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'idempotency_key' => $idempotencyKey,
                'reference' => $reference,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Reserve credits from the wallet (Phase 1).
     *
     * @throws InsufficientCreditsException
     */
    public function reserveCredits(
        OrganizerCreditWallet $wallet,
        int $amount,
        string $referenceType,
        string $referenceId,
        int $ttlMinutes = 60
    ): CreditReservation {
        return DB::transaction(function () use ($wallet, $amount, $referenceType, $referenceId, $ttlMinutes): CreditReservation {
            /** @var OrganizerCreditWallet $lockedWallet */
            $lockedWallet = OrganizerCreditWallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $available = $lockedWallet->balance - $lockedWallet->reserved_balance;
            if ($available < $amount) {
                throw new InsufficientCreditsException($amount, $available);
            }

            $lockedWallet->increment('reserved_balance', $amount);

            return CreditReservation::create([
                'wallet_id' => $lockedWallet->id,
                'amount' => $amount,
                'status' => ReservationStatus::PENDING,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);
        });
    }

    /**
     * Settle a pending credit reservation (Phase 2 Commit).
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws InvalidReservationException
     */
    public function settleReservation(
        CreditReservation $reservation,
        int $actualConsumedAmount,
        CreditTxReason $reason,
        string $idempotencyKey,
        string $reference,
        string $description,
        array $metadata = [],
        ?Event $event = null
    ): ?CreditLedger {
        return DB::transaction(function () use (
            $reservation,
            $actualConsumedAmount,
            $reason,
            $idempotencyKey,
            $reference,
            $description,
            $metadata,
            $event
        ): ?CreditLedger {
            /** @var CreditReservation $lockedReservation */
            $lockedReservation = CreditReservation::where('id', $reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReservation->status !== ReservationStatus::PENDING) {
                throw new InvalidReservationException('Reservation is not in PENDING state.');
            }

            if ($actualConsumedAmount > $lockedReservation->amount) {
                throw new InvalidReservationException(
                    "Actual consumed amount ({$actualConsumedAmount}) cannot exceed reserved amount ({$lockedReservation->amount})."
                );
            }

            if ($actualConsumedAmount < 0) {
                throw new InvalidReservationException('Actual consumed amount cannot be negative.');
            }

            /** @var OrganizerCreditWallet $lockedWallet */
            $lockedWallet = OrganizerCreditWallet::where('id', $lockedReservation->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = $lockedWallet->balance;
            $newBalance = $balanceBefore - $actualConsumedAmount;
            $newReserved = max(0, $lockedWallet->reserved_balance - $lockedReservation->amount);

            $lockedWallet->update([
                'balance' => $newBalance,
                'reserved_balance' => $newReserved,
                'version' => $lockedWallet->version + 1,
            ]);

            $lockedReservation->update([
                'status' => ReservationStatus::COMMITTED,
            ]);

            if ($actualConsumedAmount > 0) {
                $ledgerMetadata = array_merge($metadata, [
                    'reservation_id' => $lockedReservation->id,
                    'reserved_amount' => $lockedReservation->amount,
                ]);

                return CreditLedger::create([
                    'wallet_id' => $lockedWallet->id,
                    'organizer_id' => $lockedWallet->organizer_id,
                    'event_id' => $event?->id,
                    'type' => CreditTxType::DEBIT,
                    'reason' => $reason,
                    'amount' => $actualConsumedAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $newBalance,
                    'idempotency_key' => $idempotencyKey,
                    'reference' => $reference,
                    'description' => $description,
                    'metadata' => $ledgerMetadata,
                ]);
            }

            return null;
        });
    }

    /**
     * Release a reserved credit amount back to available balance.
     *
     * @throws InvalidReservationException
     */
    public function releaseReservation(CreditReservation $reservation, string $reasonText = 'Explicit release'): void
    {
        DB::transaction(function () use ($reservation): void {
            /** @var CreditReservation $lockedReservation */
            $lockedReservation = CreditReservation::where('id', $reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReservation->status !== ReservationStatus::PENDING) {
                throw new InvalidReservationException('Reservation is not in PENDING state.');
            }

            /** @var OrganizerCreditWallet $lockedWallet */
            $lockedWallet = OrganizerCreditWallet::where('id', $lockedReservation->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $newReserved = max(0, $lockedWallet->reserved_balance - $lockedReservation->amount);

            $lockedWallet->update([
                'reserved_balance' => $newReserved,
                'version' => $lockedWallet->version + 1,
            ]);

            $lockedReservation->update([
                'status' => ReservationStatus::RELEASED,
            ]);
        });
    }

    /**
     * Zombie Reaper: Find all expired pending reservations, release reserved balance, and mark EXPIRED.
     */
    public function reapExpiredReservations(): int
    {
        $expiredReservations = CreditReservation::where('status', ReservationStatus::PENDING)
            ->where('expires_at', '<', now())
            ->get();

        $reapedCount = 0;

        foreach ($expiredReservations as $reservation) {
            DB::transaction(function () use ($reservation, &$reapedCount): void {
                /** @var CreditReservation|null $lockedReservation */
                $lockedReservation = CreditReservation::where('id', $reservation->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedReservation || $lockedReservation->status !== ReservationStatus::PENDING) {
                    return;
                }

                /** @var OrganizerCreditWallet $lockedWallet */
                $lockedWallet = OrganizerCreditWallet::where('id', $lockedReservation->wallet_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $newReserved = max(0, $lockedWallet->reserved_balance - $lockedReservation->amount);

                $lockedWallet->update([
                    'reserved_balance' => $newReserved,
                    'version' => $lockedWallet->version + 1,
                ]);

                $lockedReservation->update([
                    'status' => ReservationStatus::EXPIRED,
                ]);

                $reapedCount++;
            });
        }

        return $reapedCount;
    }

    /**
     * Reconciliation Invariant Check:
     * Check if stored balance matches the computed sum from credit_ledger.
     *
     * @return array{valid: bool, stored_balance: int, computed_balance: int, difference: int}
     */
    public function reconcileWallet(OrganizerCreditWallet $wallet): array
    {
        $result = CreditLedger::where('wallet_id', $wallet->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'CREDIT' THEN amount ELSE -amount END), 0) as computed_balance")
            ->first();

        $computedBalance = (int) ($result->computed_balance ?? 0);
        $storedBalance = (int) $wallet->balance;
        $difference = $storedBalance - $computedBalance;

        return [
            'valid' => $difference === 0,
            'stored_balance' => $storedBalance,
            'computed_balance' => $computedBalance,
            'difference' => $difference,
        ];
    }
}
