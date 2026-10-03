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
        Schema::table('organizers', function (Blueprint $table) {
            if (! Schema::hasColumn('organizers', 'cover_photo')) {
                $table->string('cover_photo', 1000)->nullable()->after('logo');
            }
            if (! Schema::hasColumn('organizers', 'website')) {
                $table->string('website')->nullable()->after('phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            if (Schema::hasColumn('organizers', 'cover_photo')) {
                $table->dropColumn('cover_photo');
            }
            if (Schema::hasColumn('organizers', 'website')) {
                $table->dropColumn('website');
            }
        });
    }
};
