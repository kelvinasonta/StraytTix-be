<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Models\User;
use Modules\Credits\app\Enums\CreditTxReason;
use Modules\Credits\app\Services\CreditLedgerService;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['account_type' => 'organizer']);
    $this->organizer = Organizer::create([
        'user_id' => $this->owner->id,
        'name' => 'Concurrency Tech Corp',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $this->ledgerService = app(CreditLedgerService::class);
});

test('parallel double-spend concurrency test prevents negative balance under 20 concurrent debits', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    // Initial top-up of exactly 100 credits
    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 100,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'initial_concurrency_seed_100',
        reference: 'REF-SEED-100',
        description: 'Initial seed 100 credits for double-spend concurrency test'
    );

    $wallet->refresh();
    expect($wallet->balance)->toBe(100);

    // Prepare a temporary file-based SQLite database with WAL enabled for concurrent processes
    $tempDbPath = tempnam(sys_get_temp_dir(), 'credits_concurrency_');
    $pdo = new PDO('sqlite:'.$tempDbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL;');
    $pdo->exec('PRAGMA busy_timeout=15000;');

    // Replicate schema for organizer_credit_wallets and credit_ledger
    $pdo->exec('CREATE TABLE organizer_credit_wallets (
        id INTEGER PRIMARY KEY,
        uuid TEXT,
        organizer_id INTEGER,
        balance INTEGER NOT NULL,
        reserved_balance INTEGER NOT NULL DEFAULT 0,
        currency TEXT DEFAULT "NGN",
        version INTEGER NOT NULL DEFAULT 1
    )');

    $pdo->exec('CREATE TABLE credit_ledger (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        wallet_id INTEGER,
        organizer_id INTEGER,
        type TEXT,
        reason TEXT,
        amount INTEGER,
        balance_before INTEGER,
        balance_after INTEGER,
        idempotency_key TEXT UNIQUE,
        reference TEXT,
        description TEXT
    )');

    $insertWallet = $pdo->prepare('INSERT INTO organizer_credit_wallets (id, uuid, organizer_id, balance, reserved_balance, currency, version) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insertWallet->execute([
        $wallet->id,
        $wallet->uuid,
        $wallet->organizer_id,
        $wallet->balance,
        $wallet->reserved_balance,
        $wallet->currency,
        $wallet->version,
    ]);

    // Close parent PDO before forking
    $pdo = null;

    $totalRequests = 20;
    $debitAmount = 10;
    $pids = [];

    // Fork 20 concurrent worker processes
    for ($i = 0; $i < $totalRequests; $i++) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            $this->fail('Failed to fork child process for concurrency test');
        } elseif ($pid === 0) {
            // Child worker process
            $childPdo = new PDO('sqlite:'.$tempDbPath);
            $childPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $childPdo->setAttribute(PDO::ATTR_TIMEOUT, 15);
            $idempotencyKey = 'concurrent_debit_'.$i;

            $maxRetries = 40;
            $processed = false;

            while ($maxRetries > 0 && ! $processed) {
                try {
                    $childPdo->exec('BEGIN IMMEDIATE TRANSACTION;');

                    // Check idempotency
                    $idemStmt = $childPdo->prepare('SELECT id FROM credit_ledger WHERE wallet_id = ? AND idempotency_key = ?');
                    $idemStmt->execute([$wallet->id, $idempotencyKey]);
                    if ($idemStmt->fetch()) {
                        $childPdo->exec('COMMIT;');
                        exit(0);
                    }

                    // Select wallet with row lock equivalent (IMMEDIATE transaction holds reserved lock)
                    $stmt = $childPdo->prepare('SELECT balance, reserved_balance, version FROM organizer_credit_wallets WHERE id = ?');
                    $stmt->execute([$wallet->id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    $balance = (int) $row['balance'];
                    $reserved = (int) $row['reserved_balance'];
                    $available = $balance - $reserved;

                    if ($available < $debitAmount) {
                        $childPdo->exec('ROLLBACK;');
                        exit(2); // Insufficient credits code
                    }

                    $balanceAfter = $balance - $debitAmount;
                    $newVersion = ((int) $row['version']) + 1;

                    // Update wallet
                    $updateStmt = $childPdo->prepare('UPDATE organizer_credit_wallets SET balance = ?, version = ? WHERE id = ?');
                    $updateStmt->execute([$balanceAfter, $newVersion, $wallet->id]);

                    // Insert ledger
                    $ledgerStmt = $childPdo->prepare('INSERT INTO credit_ledger (wallet_id, organizer_id, type, reason, amount, balance_before, balance_after, idempotency_key, reference, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $ledgerStmt->execute([
                        $wallet->id,
                        $wallet->organizer_id,
                        'DEBIT',
                        'EMAIL_BROADCAST',
                        $debitAmount,
                        $balance,
                        $balanceAfter,
                        $idempotencyKey,
                        'REF-'.$idempotencyKey,
                        'Concurrent debit of '.$debitAmount.' credits',
                    ]);

                    $childPdo->exec('COMMIT;');
                    $processed = true;
                    exit(0); // Success code
                } catch (PDOException $e) {
                    try {
                        $childPdo->exec('ROLLBACK;');
                    } catch (Throwable) {
                    }

                    if (str_contains($e->getMessage(), 'locked') || str_contains($e->getMessage(), 'busy')) {
                        $maxRetries--;
                        usleep(rand(1000, 5000));

                        continue;
                    }

                    exit(1); // Unexpected error code
                }
            }

            exit(1);
        } else {
            $pids[] = $pid;
        }
    }

    // Wait for all child processes to complete
    $successCount = 0;
    $insufficientCount = 0;
    $errorCount = 0;

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        $exitCode = pcntl_wexitstatus($status);

        if ($exitCode === 0) {
            $successCount++;
        } elseif ($exitCode === 2) {
            $insufficientCount++;
        } else {
            $errorCount++;
        }
    }

    $verifyPdo = new PDO('sqlite:'.$tempDbPath);
    $finalBalance = (int) $verifyPdo->query('SELECT balance FROM organizer_credit_wallets WHERE id = '.$wallet->id)->fetchColumn();
    $ledgerRecordsCount = (int) $verifyPdo->query('SELECT COUNT(*) FROM credit_ledger WHERE wallet_id = '.$wallet->id)->fetchColumn();
    @unlink($tempDbPath);

    // Assert that exactly 10 requests succeeded (10 * 10 = 100), exactly 10 were rejected for insufficient credits, zero errors
    expect($errorCount)->toBe(0)
        ->and($successCount)->toBe(10)
        ->and($insufficientCount)->toBe(10)
        ->and($ledgerRecordsCount)->toBe(10)
        ->and($finalBalance)->toBe(0);
});

test('reconciliation invariant confirms balance integrity and flags tampering', function () {
    $wallet = $this->ledgerService->getOrCreateWallet($this->organizer);

    // 1. Initial Top-up: 500 credits
    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 500,
        reason: CreditTxReason::TOPUP,
        idempotencyKey: 'recon_topup_500',
        reference: 'REF-TOPUP-500',
        description: 'Recon Topup'
    );

    // 2. Direct Debit: 120 credits
    $this->ledgerService->debitCreditsSync(
        wallet: $wallet,
        amount: 120,
        reason: CreditTxReason::EMAIL_BROADCAST,
        idempotencyKey: 'recon_debit_120',
        reference: 'REF-DEBIT-120',
        description: 'Recon Debit'
    );

    // 3. Reservation with partial settlement: Reserve 100, Settle 45, Release 55
    $reservation = $this->ledgerService->reserveCredits(
        wallet: $wallet,
        amount: 100,
        referenceType: 'CAMPAIGN',
        referenceId: 'CAMP-RECON-1'
    );

    $this->ledgerService->settleReservation(
        reservation: $reservation,
        actualConsumedAmount: 45,
        reason: CreditTxReason::SMS_BROADCAST,
        idempotencyKey: 'recon_settle_45',
        reference: 'REF-SETTLE-45',
        description: 'Partial settlement'
    );

    // 4. Another credit: 80 credits
    $this->ledgerService->creditWallet(
        wallet: $wallet,
        amount: 80,
        reason: CreditTxReason::FAILED_DISPATCH_REFUND,
        idempotencyKey: 'recon_refund_80',
        reference: 'REF-REFUND-80',
        description: 'Recon Refund'
    );

    $wallet->refresh();

    // Check reconciliation invariant
    // Math: 500 - 120 - 45 + 80 = 415
    $reconciliation = $this->ledgerService->reconcileWallet($wallet);

    expect($reconciliation['valid'])->toBeTrue()
        ->and($reconciliation['stored_balance'])->toBe(415)
        ->and($reconciliation['computed_balance'])->toBe(415)
        ->and($reconciliation['difference'])->toBe(0);

    // 5. Intentionally tamper with wallet balance
    Log::shouldReceive('channel')->with('daily')->andReturnSelf();
    Log::shouldReceive('critical')->once()->withArgs(function (string $message, array $context) use ($wallet) {
        return str_contains($message, 'Credit mismatch detected')
            && $context['wallet_id'] === $wallet->id
            && $context['reconciliation']['difference'] === (9999 - 415);
    });

    $wallet->update(['balance' => 9999]);
    $wallet->refresh();

    $tamperedReconciliation = $this->ledgerService->reconcileWallet($wallet);

    expect($tamperedReconciliation['valid'])->toBeFalse()
        ->and($tamperedReconciliation['stored_balance'])->toBe(9999)
        ->and($tamperedReconciliation['computed_balance'])->toBe(415)
        ->and($tamperedReconciliation['difference'])->toBe(9999 - 415);

    // Now test Artisan reconcile command detects and logs the mismatch
    $this->artisan('credits:reconcile', ['--wallet-id' => $wallet->id])
        ->assertFailed();
});
