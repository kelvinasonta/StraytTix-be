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
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->enum('ticket_type', ['single', 'group'])->default('single');
            $table->integer('group_size')->nullable();
            $table->enum('type', ['free', 'paid'])->default('paid');
            $table->text('description')->nullable();
            $table->enum('ticket_capacity', ['limited', 'unlimited'])->default('limited');
            $table->integer('quantity')->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('currency', 10)->default('NGN');
            $table->integer('sold_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->dateTime('sale_starts_at')->nullable();
            $table->dateTime('sale_ends_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
