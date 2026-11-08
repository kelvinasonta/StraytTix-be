<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Modules\Auth\app\Models\User;
use Modules\Credits\app\Enums\CreditTxReason;
use Modules\Credits\app\Services\CreditLedgerService;
use Modules\Credits\app\Services\CreditTopupService;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['account_type' => 'organizer']);
    $this->adminUser = User::factory()->create(['account_type' => 'organizer']);
    $this->managerUser = User::factory()->create(['account_type' => 'organizer']);
    $this->outsider = User::factory()->create(['account_type' => 'organizer']);

    $this->organizer = Organizer::create([
        'user_id' => $this->owner->id,
        'name' => 'API Test Organizer',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    OrganizerMember::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->adminUser->id,
        'role' => OrganizerMemberRole::ADMIN,
    ]);

    OrganizerMember::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->managerUser->id,
        'role' => OrganizerMemberRole::MANAGER,
    ]);

    $this->ledgerService = app(CreditLedgerService::class);
    $this->topupService = app(CreditTopupService::class);
});

test('unauthenticated request to wallet returns 401', function () {
    $response = $this->getJson("/api/v1/organizers/{$this->organizer->uuid}/credits/wallet");

    $response->assertStatus(401);
});

test('unauthorized user cannot access wallet', function () {
    $response = $this->actingAs($this->outsider)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/credits/wallet");

    $response->assertStatus(403);
});

test('owner, admin, and manager can get wallet details', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);
    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 250,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'api_topup_1',
        reference: 'REF-API-1',
        description: 'Initial wallet balance'
    );

    // Reserve 50
    $this->ledgerService->reserveCredits($wallet, 50, 'BROADCAST', 'B-1');

    foreach ([$this->owner, $this->adminUser, $this->managerUser] as $user) {
        $response = $this->actingAs($user)
            ->getJson("/api/v1/organizers/{$this->organizer->uuid}/credits/wallet");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.balance', 250)
            ->assertJsonPath('data.reserved_balance', 50)
            ->assertJsonPath('data.available_balance', 200)
            ->assertJsonPath('data.currency', 'NGN');
    }
});

test('organizer can fetch paginated ledger history with filtering', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    // Create 3 ledger entries: 2 topups, 1 debit
    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 100,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'ledger_entry_1',
        reference: 'REF-1',
        description: 'First topup'
    );

    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 200,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'ledger_entry_2',
        reference: 'REF-2',
        description: 'Second topup'
    );

    $this->ledgerService->debitCreditsSync(
        wallet: $wallet,
        amount: 50,
        reason: CreditTxReason::EMAIL_BROADCAST,
        idempotencyKey: 'ledger_entry_3',
        reference: 'REF-3',
        description: 'Debit for email'
    );

    // Default fetch
    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/credits/ledger");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);

    // Filter by reason=TOPUP
    $filteredResponse = $this->actingAs($this->owner)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/credits/ledger?reason=TOPUP");

    $filteredResponse->assertStatus(200)
        ->assertJsonCount(2, 'data');

    // Filter by type=DEBIT
    $debitResponse = $this->actingAs($this->owner)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/credits/ledger?type=DEBIT");

    $debitResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount', 50);
});

test('organizer can initialize top-up and obtain payment reference', function () {
    $response = $this->actingAs($this->owner)
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/credits/top-up/initialize", [
            'credits' => 50,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.credits', 50)
        ->assertJsonPath('data.amount_kobo', 50000)
        ->assertJsonPath('data.currency', 'NGN');

    expect($response->json('data.reference'))->toBeString()->toStartWith('CREDIT-');
    expect($response->json('data.checkout_url'))->toBeString();
});

test('organizer can trigger wallet reconcile endpoint', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);
    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 300,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'recon_api_topup',
        reference: 'REF-RECON',
        description: 'Reconcile test'
    );

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/credits/reconcile");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.stored_balance', 300)
        ->assertJsonPath('data.computed_balance', 300)
        ->assertJsonPath('data.difference', 0);

    // Tamper with balance
    $wallet->update(['balance' => 9999]);

    $tamperedResponse = $this->actingAs($this->owner)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/credits/reconcile");

    $tamperedResponse->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'CREDIT_RECONCILIATION_MISMATCH');
});

test('public paystack webhook verifies signature and credits wallet', function () {
    $secret = 'test_paystack_secret_key_abcdef';
    Config::set('paystack.secret_key', $secret);

    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $payload = [
        'event' => 'charge.success',
        'data' => [
            'reference' => 'PAYSTACK_WEBHOOK_REF_100',
            'amount' => 50000, // 500 NGN = 50 credits
            'currency' => 'NGN',
            'metadata' => [
                'wallet_id' => $wallet->id,
                'organizer_id' => $this->organizer->id,
                'credits_purchased' => 50,
            ],
        ],
    ];

    $rawJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $signature = hash_hmac('sha512', $rawJson, $secret);

    $response = $this->withHeaders([
        'x-paystack-signature' => $signature,
        'Content-Type' => 'application/json',
    ])->postJson('/api/v1/credits/webhooks/paystack', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('credits_awarded', 50);

    $wallet->refresh();
    expect($wallet->balance)->toBe(50);
});

test('public paystack webhook rejects invalid signature', function () {
    Config::set('paystack.secret_key', 'test_paystack_secret_key_abcdef');

    $payload = [
        'event' => 'charge.success',
        'data' => [
            'reference' => 'PAYSTACK_REF_FAKE',
            'amount' => 10000,
        ],
    ];

    $response = $this->withHeaders([
        'x-paystack-signature' => 'invalid_signature_hash',
    ])->postJson('/api/v1/credits/webhooks/paystack', $payload);

    $response->assertStatus(400);
});
