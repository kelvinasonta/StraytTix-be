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
        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->string('slug')->unique()->index();
                $table->text('description')->nullable();
                $table->string('event_country')->nullable();
                $table->string('event_type')->default('physical')->index();
                $table->string('event_format')->nullable();
                $table->json('event_category')->nullable();

                $table->foreignId('event_location_id')->nullable();
                $table->foreignId('event_schedule_id')->nullable();
                $table->foreignId('event_lineup_id')->nullable();

                $table->string('status')->default('draft')->index();
                $table->string('visibility')->default('public')->index();
                $table->string('banner_url')->nullable();
                $table->boolean('is_featured')->default(false)->index();
                $table->json('meta')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
