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
        Schema::create('event_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('schedule_type')->index(); // single, recurring
            $table->string('recurring_type')->nullable()->index(); // after_occurrences, after_end_date
            $table->string('schedule_timezone')->index()->default('UTC'); // UTC, Africa/Lagos, etc.

            $table->string('event_frequency')->nullable()->index(); // daily, weekly, monthly, yearly

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('start_time');
            $table->time('end_time')->nullable();

            $table->date('recurring_end_date')->nullable();
            $table->time('recurring_end_time')->nullable();

            $table->unsignedInteger('recurring_occurrences')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_schedules');
    }
};
