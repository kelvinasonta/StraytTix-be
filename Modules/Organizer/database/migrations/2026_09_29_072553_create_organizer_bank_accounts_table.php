<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizer_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('bank_code', 20);
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number', 20);

            $table->boolean('is_default')->default(false);

            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index('organizer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizer_bank_accounts');
    }
};
