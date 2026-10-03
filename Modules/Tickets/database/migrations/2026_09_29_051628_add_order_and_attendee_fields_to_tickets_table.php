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
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('ticket_type_id')->constrained('orders')->nullOnDelete();
            $table->string('attendee_name')->nullable()->after('currency');
            $table->string('attendee_email')->nullable()->after('attendee_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn(['order_id', 'attendee_name', 'attendee_email']);
        });
    }
};
