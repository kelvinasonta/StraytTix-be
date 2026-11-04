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
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('target_type', 100)->nullable()->after('organizer_id')->index();
            $table->string('target_id', 64)->nullable()->after('target_type')->index();
            $table->string('reason')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['target_type']);
            $table->dropIndex(['target_id']);
            $table->dropColumn(['target_type', 'target_id', 'reason']);
        });
    }
};
