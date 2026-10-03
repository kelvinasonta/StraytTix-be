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
        if (! Schema::hasTable('event_metrics')) {
            Schema::create('event_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->unsignedInteger('views_72h')->default(0);
                $table->unsignedInteger('saves_72h')->default(0);
                $table->unsignedInteger('shares_72h')->default(0);
                $table->unsignedInteger('tickets_sold_72h')->default(0);
                $table->unsignedInteger('total_impressions')->default(0);
                $table->decimal('quality_score', 4, 3)->default(0.500);
                $table->decimal('popularity_score', 4, 3)->default(0.000);
                $table->timestamp('last_scored_at')->nullable();
                $table->timestamps();

                $table->unique('event_id');
                $table->index(['popularity_score', 'quality_score']);
            });
        }

        if (! Schema::hasTable('user_interests')) {
            Schema::create('user_interests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('interest_type', 32); // 'category', 'city', 'price_tier'
                $table->string('interest_value', 64);
                $table->decimal('affinity_score', 4, 3)->default(0.100);
                $table->timestamps();

                $table->unique(['user_id', 'interest_type', 'interest_value']);
                $table->index(['user_id', 'affinity_score']);
            });
        }

        Schema::table('events_location', function (Blueprint $table) {
            $table->index(['city', 'state'], 'events_location_city_state_index');
            $table->index(['latitude', 'longitude'], 'events_location_lat_lng_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_location', function (Blueprint $table) {
            $table->dropIndex('events_location_city_state_index');
            $table->dropIndex('events_location_lat_lng_index');
        });

        Schema::dropIfExists('user_interests');
        Schema::dropIfExists('event_metrics');
    }
};
