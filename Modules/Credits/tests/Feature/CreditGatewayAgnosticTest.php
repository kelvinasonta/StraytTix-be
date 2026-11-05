<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Modules\Auth\app\Models\User;
use Modules\Credits\app\Gateways\FlutterwaveCreditGateway;
use Modules\Credits\app\Gateways\PaystackCreditGateway;
use Modules\Credits\app\Models\CreditTopupTransaction;
use Modules\Credits\app\Services\CreditGatewayManager;
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
    $this->organizer = Organizer::create([
        'user_id' => $this->owner->id,
        'name' => 'Gateway Test Organizer',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    OrganizerMember::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'role' => OrganizerMemberRole::OWNER,
    ]);

    $this->ledgerService = app(CreditLedgerService::class);
    $this->gatewayManager = app(CreditGatewayManager::class);
    $this->topupService = app(CreditTopupService::class);
});

test('CreditGatewayManager correctly registers and resolves drivers', function () {
    expect($this->gatewayManager->getAvailableDrivers())->toContain('paystack', 'flutterwave');

    $paystack = $this->gatewayManager->driver('paystack');
    expect($paystack)->toBeInstanceOf(PaystackCreditGateway::class);

    $flutterwave = $this->gatewayManager->driver('flutterwave');
    expect($flutterwave)->toBeInstanceOf(FlutterwaveCreditGateway::class);
});

test('can switch default gateway via config without code change', function () {
    Config::set('credits.default_gateway', 'flutterwave');

    $defaultDriver = $this->gatewayManager->driver();
    expect($defaultDriver)->toBeInstanceOf(FlutterwaveCreditGateway::class);

    Config::set('credits.default_gateway', 'paystack');
    $defaultDriver = $this->gatewayManager->driver();
    expect($defaultDriver)->toBeInstanceOf(PaystackCreditGateway::class);
});

test('Flutterwave checkout initialization produces valid checkout DTO', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $checkout = $this->topupService->initializeTopup(
        wallet: $wallet,
        credits: 100,
        callbackUrl: 'https://example.com/callback',
        customerEmail: 'organizer@example.com',
        gatewayName: 'flutterwave'
    );

    expect($checkout->gateway)->toBe('flutterwave')
        ->and($checkout->credits)->toBe(100)
        ->and($checkout->amountKobo)->toBe(100000)
        ->and($checkout->reference)->toStartWith('CRD_FLW_')
        ->and($checkout->authorizationUrl)->toContain('flutterwave.com');

    $topupTx = CreditTopupTransaction::where('provider_ref', $checkout->reference)->first();
    expect($topupTx)->not->toBeNull()
        ->and($topupTx->provider)->toBe('flutterwave')
        ->and($topupTx->status)->toBe('PENDING');
});

test('Flutterwave webhook verifies secret hash, credits wallet, and is idempotent', function () {
    $secretHash = 'test_flw_secret_hash_98765';
    Config::set('credits.gateways.flutterwave.webhook_secret', $secretHash);

    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'id' => 991823,
            'tx_ref' => 'FLW_TOPUP_TX_555',
            'flw_ref' => 'FLW-MOCK-REF-777',
            'amount' => 2000, // 2,000 NGN = 200,000 Kobo = 200 credits
            'currency' => 'NGN',
            'status' => 'successful',
            'meta' => [
                'wallet_id' => $wallet->id,
                'organizer_id' => $this->organizer->id,
                'credits_requested' => 200,
            ],
        ],
    ];

    $response = $this->withHeaders([
        'verif-hash' => $secretHash,
        'Content-Type' => 'application/json',
    ])->postJson('/api/v1/credits/webhooks/flutterwave', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('credits_awarded', 200);

    $wallet->refresh();
    expect($wallet->balance)->toBe(200);

    // Duplicate webhook replay test
    $replayResponse = $this->withHeaders([
        'verif-hash' => $secretHash,
    ])->postJson('/api/v1/credits/webhooks/flutterwave', $payload);

    $replayResponse->assertStatus(200)
        ->assertJsonPath('status', 'already_processed');

    $wallet->refresh();
    expect($wallet->balance)->toBe(200); // Balance remains intact without double-crediting
});

test('Flutterwave webhook rejects invalid verif-hash', function () {
    Config::set('credits.gateways.flutterwave.webhook_secret', 'correct_hash');

    $payload = [
        'event' => 'charge.completed',
        'data' => ['tx_ref' => 'FAKE_TX', 'status' => 'successful'],
    ];

    $response = $this->withHeaders([
        'verif-hash' => 'wrong_hash',
    ])->postJson('/api/v1/credits/webhooks/flutterwave', $payload);

    $response->assertStatus(400);
});

test('organizer can query available gateways and specify gateway in top-up initialization', function () {
    $gatewaysResponse = $this->actingAs($this->owner)
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/credits/gateways");

    $gatewaysResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.default_gateway', 'paystack')
        ->assertJsonPath('data.gateways.0', 'paystack')
        ->assertJsonPath('data.gateways.1', 'flutterwave');

    // Initialize with explicit flutterwave
    $initResponse = $this->actingAs($this->owner)
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/credits/top-up/initialize", [
            'credits' => 150,
            'gateway' => 'flutterwave',
            'callback_url' => 'https://strayttix.com/billing',
        ]);

    $initResponse->assertStatus(200)
        ->assertJsonPath('data.gateway', 'flutterwave')
        ->assertJsonPath('data.credits', 150)
        ->assertJsonPath('data.amount_kobo', 150000);
});
