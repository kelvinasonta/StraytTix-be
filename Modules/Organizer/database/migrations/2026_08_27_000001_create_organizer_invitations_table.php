<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->string('email');
            $table->enum('role', ['owner', 'admin', 'manager', 'staff'])->default('staff');
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['organizer_id', 'email'], 'organizer_invitations_org_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_invitations');
    }
};
