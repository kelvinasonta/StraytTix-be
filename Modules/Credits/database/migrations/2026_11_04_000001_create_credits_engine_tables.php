<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizer_credit_wallets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organizer_id')->constrained('organizers')->restrictOnDelete();
            $table->integer('balance')->default(0);
            $table->integer('reserved_balance')->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->bigInteger('version')->default(1);
            $table->timestamps();

            $table->index('organizer_id');
            $table->index('uuid');
        });

        // Add check constraints to enforce non-negative balances and balance >= reserved_balance
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE organizer_credit_wallets ADD CONSTRAINT chk_organizer_credit_wallets_balance CHECK (balance >= 0)');
            DB::statement('ALTER TABLE organizer_credit_wallets ADD CONSTRAINT chk_organizer_credit_wallets_reserved CHECK (reserved_balance >= 0)');
            DB::statement('ALTER TABLE organizer_credit_wallets ADD CONSTRAINT chk_organizer_credit_wallets_available CHECK (balance >= reserved_balance)');
        }

        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained('organizer_credit_wallets')->restrictOnDelete();
            $table->foreignId('organizer_id')->constrained('organizers')->restrictOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('type', 20); // CREDIT, DEBIT, HOLD, RELEASE
            $table->string('reason', 50); // TOPUP, EMAIL_BROADCAST, etc.
            $table->integer('amount');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('idempotency_key', 128);
            $table->string('reference', 128);
            $table->string('description', 255);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['wallet_id', 'idempotency_key']);
            $table->index(['wallet_id', 'created_at']);
            $table->index('reference');
        });

        Schema::create('credit_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained('organizer_credit_wallets')->restrictOnDelete();
            $table->integer('amount');
            $table->string('status', 20)->default('PENDING'); // PENDING, COMMITTED, RELEASED, EXPIRED
            $table->string('reference_type', 50); // e.g. EMAIL_CAMPAIGN
            $table->string('reference_id', 64);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('credit_topup_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained('organizer_credit_wallets')->restrictOnDelete();
            $table->string('provider', 30)->default('paystack');
            $table->string('provider_ref', 128)->unique();
            $table->bigInteger('amount_kobo');
            $table->integer('credits_awarded');
            $table->string('status', 20)->default('PENDING'); // PENDING, SUCCESS, FAILED
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_topup_transactions');
        Schema::dropIfExists('credit_reservations');
        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('organizer_credit_wallets');
    }
};
