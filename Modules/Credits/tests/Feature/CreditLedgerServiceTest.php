<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Modules\Auth\app\Models\User;
use Modules\Credits\app\Enums\CreditTxReason;
use Modules\Credits\app\Enums\CreditTxType;
use Modules\Credits\app\Enums\ReservationStatus;
use Modules\Credits\app\Exceptions\InsufficientCreditsException;
use Modules\Credits\app\Exceptions\InvalidReservationException;
use Modules\Credits\app\Models\CreditLedger;
use Modules\Credits\app\Models\CreditReservation;
use Modules\Credits\app\Models\CreditTopupTransaction;
use Modules\Credits\app\Models\OrganizerCreditWallet;
use Modules\Credits\app\Services\CreditLedgerService;
use Modules\Credits\app\Services\CreditTopupService;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['account_type' => 'organizer']);
    $this->organizer = Organizer::create([
        'user_id' => $this->owner->id,
        'name' => 'African Tech Network',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $this->event = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Lagos Tech Fest 2026',
        'description' => 'A premier tech gathering.',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Conference',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $this->ledgerService = app(CreditLedgerService::class);
    $this->topupService = app(CreditTopupService::class);
});

test('getOrCreateWallet creates a new wallet with zero balance', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    expect($wallet)->toBeInstanceOf(OrganizerCreditWallet::class)
        ->and($wallet->organizer_id)->toBe($this->organizer->id)
        ->and($wallet->balance)->toBe(0)
        ->and($wallet->reserved_balance)->toBe(0)
        ->and($wallet->available_balance)->toBe(0)
        ->and($wallet->version)->toBe(1);

    // Call again to verify idempotent retrieval
    $sameWallet = $this->ledgerService->getOrCreateWallet($this->organizer);
    expect($sameWallet->id)->toBe($wallet->id);
});

test('creditWallet increases balance and inserts credit ledger entry with idempotency', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $entry = $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 500,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'tx_topup_1',
        reference: 'REF-TOPUP-1',
        description: 'First topup',
        metadata: ['channel' => 'card'],
        event: $this->event
    );

    expect($entry)->toBeInstanceOf(CreditLedger::class)
        ->and($entry->type)->toBe(CreditTxType::CREDIT)
        ->and($entry->amount)->toBe(500)
        ->and($entry->balance_before)->toBe(0)
        ->and($entry->balance_after)->toBe(500)
        ->and($entry->wallet_id)->toBe($wallet->id)
        ->and($entry->organizer_id)->toBe($this->organizer->id)
        ->and($entry->event_id)->toBe($this->event->id);

    $wallet->refresh();
    expect($wallet->balance)->toBe(500)
        ->and($wallet->version)->toBe(2);

    // Idempotent retry with same key
    $retryEntry = $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 500,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'tx_topup_1',
        reference: 'REF-TOPUP-1',
        description: 'First topup',
        metadata: ['channel' => 'card'],
        event: $this->event
    );

    expect($retryEntry->id)->toBe($entry->id);
    $wallet->refresh();
    expect($wallet->balance)->toBe(500); // Balance unchanged
});

test('debitCreditsSync decreases balance when sufficient funds exist', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 300,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'tx_topup_2',
        reference: 'REF-TOPUP-2',
        description: 'Fund wallet'
    );

    $wallet->refresh();

    $debitEntry = $this->ledgerService->debitCreditsSync(
        wallet: $wallet,
        amount: 100,
        reason: CreditTxReason::EMAIL_BROADCAST,
        idempotencyKey: 'tx_debit_1',
        reference: 'REF-EMAIL-1',
        description: 'Sent 100 emails',
        metadata: ['recipients' => 100]
    );

    expect($debitEntry)->toBeInstanceOf(CreditLedger::class)
        ->and($debitEntry->type)->toBe(CreditTxType::DEBIT)
        ->and($debitEntry->amount)->toBe(100)
        ->and($debitEntry->balance_before)->toBe(300)
        ->and($debitEntry->balance_after)->toBe(200);

    $wallet->refresh();
    expect($wallet->balance)->toBe(200)
        ->and($wallet->version)->toBe(3);

    // Idempotent retry returns existing entry without double-debiting
    $retryDebit = $this->ledgerService->debitCreditsSync(
        wallet: $wallet,
        amount: 100,
        reason: CreditTxReason::EMAIL_BROADCAST,
        idempotencyKey: 'tx_debit_1',
        reference: 'REF-EMAIL-1',
        description: 'Sent 100 emails'
    );

    expect($retryDebit->id)->toBe($debitEntry->id);
    $wallet->refresh();
    expect($wallet->balance)->toBe(200);
});

test('debitCreditsSync throws InsufficientCreditsException when available balance is too low', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 50,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'tx_topup_3',
        reference: 'REF-TOPUP-3',
        description: 'Small fund'
    );

    $wallet->refresh();

    expect(fn () => $this->ledgerService->debitCreditsSync(
        wallet: $wallet,
        amount: 100,
        reason: CreditTxReason::EMAIL_BROADCAST,
        idempotencyKey: 'tx_debit_fail',
        reference: 'REF-EMAIL-FAIL',
        description: 'Too expensive'
    ))->toThrow(InsufficientCreditsException::class);

    $wallet->refresh();
    expect($wallet->balance)->toBe(50);
});

test('reserveCredits holds balance in two-phase reservation pattern', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 1000,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'tx_topup_res',
        reference: 'REF-RES-TOPUP',
        description: 'Fund for reservation'
    );

    $wallet->refresh();

    $reservation = $this->ledgerService->reserveCredits(
        wallet: $wallet,
        amount: 400,
        referenceType: 'EMAIL_CAMPAIGN',
        referenceId: 'CAMPAIGN-101',
        ttlMinutes: 30
    );

    expect($reservation)->toBeInstanceOf(CreditReservation::class)
        ->and($reservation->amount)->toBe(400)
        ->and($reservation->status)->toBe(ReservationStatus::PENDING)
        ->and($reservation->reference_type)->toBe('EMAIL_CAMPAIGN')
        ->and($reservation->reference_id)->toBe('CAMPAIGN-101');

    $wallet->refresh();
    expect($wallet->balance)->toBe(1000)
        ->and($wallet->reserved_balance)->toBe(400)
        ->and($wallet->available_balance)->toBe(600);

    // Attempting to reserve more than available (600) fails
    expect(fn () => $this->ledgerService->reserveCredits(
        wallet: $wallet,
        amount: 700,
        referenceType: 'EMAIL_CAMPAIGN',
        referenceId: 'CAMPAIGN-102'
    ))->toThrow(InsufficientCreditsException::class);
});

test('settleReservation commits reservation, debits actual consumed, and releases unconsumed remainder', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 1000,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'tx_topup_settle',
        reference: 'REF-SETTLE-TOPUP',
        description: 'Topup for settlement'
    );

    $wallet->refresh();

    // Reserved 400, but only 250 actually delivered
    $reservation = $this->ledgerService->reserveCredits(
        wallet: $wallet,
        amount: 400,
        referenceType: 'EMAIL_CAMPAIGN',
        referenceId: 'CAMPAIGN-201'
    );

    $wallet->refresh();
    expect($wallet->balance)->toBe(1000)
        ->and($wallet->reserved_balance)->toBe(400);

    $ledgerEntry = $this->ledgerService->settleReservation(
        reservation: $reservation,
        actualConsumedAmount: 250,
        reason: CreditTxReason::EMAIL_BROADCAST,
        idempotencyKey: 'tx_settle_201',
        reference: 'REF-CAMPAIGN-201',
        description: 'Settled campaign 201 (250/400 sent)'
    );

    expect($ledgerEntry)->toBeInstanceOf(CreditLedger::class)
        ->and($ledgerEntry->amount)->toBe(250)
        ->and($ledgerEntry->balance_before)->toBe(1000)
        ->and($ledgerEntry->balance_after)->toBe(750);

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::COMMITTED);

    $wallet->refresh();
    expect($wallet->balance)->toBe(750)
        ->and($wallet->reserved_balance)->toBe(0)
        ->and($wallet->available_balance)->toBe(750);
});

test('releaseReservation releases reserved balance back to available balance', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 500,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'tx_topup_rel',
        reference: 'REF-REL-TOPUP',
        description: 'Topup for release'
    );

    $reservation = $this->ledgerService->reserveCredits(
        wallet: $wallet,
        amount: 200,
        referenceType: 'SMS_CAMPAIGN',
        referenceId: 'SMS-1'
    );

    $wallet->refresh();
    expect($wallet->reserved_balance)->toBe(200);

    $this->ledgerService->releaseReservation($reservation);

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::RELEASED);

    $wallet->refresh();
    expect($wallet->balance)->toBe(500)
        ->and($wallet->reserved_balance)->toBe(0)
        ->and($wallet->available_balance)->toBe(500);

    // Re-releasing throws InvalidReservationException
    expect(fn () => $this->ledgerService->releaseReservation($reservation))
        ->toThrow(InvalidReservationException::class);
});

test('reapExpiredReservations zombie reaper finds expired pending reservations and restores balances', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 1000,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'tx_topup_zombie',
        reference: 'REF-ZOMBIE-TOPUP',
        description: 'Topup for zombie test'
    );

    // Create an expired reservation directly
    $expiredReservation = CreditReservation::create([
        'wallet_id' => $wallet->id,
        'amount' => 300,
        'status' => ReservationStatus::PENDING,
        'reference_type' => 'EMAIL_CAMPAIGN',
        'reference_id' => 'ZOMBIE-1',
        'expires_at' => now()->subMinutes(10),
    ]);

    // Create an active (non-expired) reservation
    $activeReservation = CreditReservation::create([
        'wallet_id' => $wallet->id,
        'amount' => 200,
        'status' => ReservationStatus::PENDING,
        'reference_type' => 'EMAIL_CAMPAIGN',
        'reference_id' => 'ACTIVE-1',
        'expires_at' => now()->addMinutes(30),
    ]);

    $wallet->update(['reserved_balance' => 500]);

    $reaped = $this->ledgerService->reapExpiredReservations();

    expect($reaped)->toBe(1);

    $expiredReservation->refresh();
    expect($expiredReservation->status)->toBe(ReservationStatus::EXPIRED);

    $activeReservation->refresh();
    expect($activeReservation->status)->toBe(ReservationStatus::PENDING);

    $wallet->refresh();
    expect($wallet->reserved_balance)->toBe(200); // 500 - 300 = 200
});

test('reconcileWallet verifies that wallet balance matches credit ledger sum', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 1000,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'rec_credit_1',
        reference: 'REF-REC-1',
        description: 'Rec test credit'
    );

    $this->ledgerService->debitCreditsSync(
        wallet: $wallet,
        amount: 350,
        reason: CreditTxReason::EMAIL_BROADCAST,
        idempotencyKey: 'rec_debit_1',
        reference: 'REF-REC-2',
        description: 'Rec test debit'
    );

    $wallet->refresh();
    $report = $this->ledgerService->reconcileWallet($wallet);

    expect($report['valid'])->toBeTrue()
        ->and($report['stored_balance'])->toBe(650)
        ->and($report['computed_balance'])->toBe(650)
        ->and($report['difference'])->toBe(0);

    // Simulate tampering or drift
    $wallet->update(['balance' => 999]);
    $tamperedReport = $this->ledgerService->reconcileWallet($wallet);

    expect($tamperedReport['valid'])->toBeFalse()
        ->and($tamperedReport['stored_balance'])->toBe(999)
        ->and($tamperedReport['computed_balance'])->toBe(650)
        ->and($tamperedReport['difference'])->toBe(349);
});

test('Paystack webhook processes charge.success, verifies HMAC signature, awards credits, and is idempotent', function () {
    Config::set('paystack.secret_key', 'test_secret_key_12345');

    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $payload = [
        'event' => 'charge.success',
        'data' => [
            'reference' => 'PAYSTACK_REF_001',
            'amount' => 500000, // 5000 NGN = 500 credits at standard 1 credit = 1000 kobo
            'metadata' => [
                'wallet_id' => $wallet->id,
                'organizer_id' => $this->organizer->id,
            ],
        ],
    ];

    $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $signature = hash_hmac('sha512', (string) $rawPayload, 'test_secret_key_12345');

    // 1. Process valid webhook
    $result = $this->topupService->processPaystackWebhook($payload, $signature);

    expect($result['status'])->toBe('success')
        ->and($result['credits_awarded'])->toBe(500)
        ->and($result['new_balance'])->toBe(500);

    $wallet->refresh();
    expect($wallet->balance)->toBe(500);

    $topupTx = CreditTopupTransaction::where('provider_ref', 'PAYSTACK_REF_001')->first();
    expect($topupTx)->not->toBeNull()
        ->and($topupTx->status)->toBe('SUCCESS')
        ->and($topupTx->credits_awarded)->toBe(500);

    // 2. Duplicate webhook with same reference is idempotent
    $duplicateResult = $this->topupService->processPaystackWebhook($payload, $signature);
    expect($duplicateResult['status'])->toBe('already_processed');

    $wallet->refresh();
    expect($wallet->balance)->toBe(500); // Balance remains 500, no double credit

    // 3. Invalid signature is rejected
    $invalidSigResult = $this->topupService->processPaystackWebhook($payload, 'invalid_signature_hash');
    expect($invalidSigResult['status'])->toBe('invalid_signature');
});

test('artisan credits:reap-reservations executes successfully', function () {
    $this->artisan('credits:reap-reservations')
        ->expectsOutputToContain('Starting zombie credit reservation reaping process...')
        ->assertSuccessful();
});

test('artisan credits:reconcile audits wallets and warns on mismatch', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);
    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 200,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'audit_cmd_1',
        reference: 'REF-AUDIT-1',
        description: 'Audit test'
    );

    // Should succeed when balanced
    $this->artisan('credits:reconcile')
        ->expectsOutputToContain('Starting credit wallet reconciliation audit...')
        ->assertSuccessful();

    // Cause a mismatch
    $wallet->update(['balance' => 999]);

    $this->artisan('credits:reconcile')
        ->assertFailed();
});
