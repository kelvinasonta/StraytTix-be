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
            if (! Schema::hasColumn('organizers', 'verification_status')) {
                $table->string('verification_status')->default('unverified')->index();
            }
            if (! Schema::hasColumn('organizers', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
        });

        Schema::dropIfExists('organizer_verifications');

        Schema::create('organizer_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('requested_by');
            $table->string('status')->default('pending')->index();
            $table->string('business_name');
            $table->string('business_type');
            $table->string('registration_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('website_url')->nullable();
            $table->string('official_email')->nullable();
            $table->string('official_phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('Nigeria');
            $table->json('documents')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organizer_id', 'fk_org_verif_org_id')->references('id')->on('organizers')->cascadeOnDelete();
            $table->foreign('requested_by', 'fk_org_verif_req_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by', 'fk_org_verif_rev_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizer_verifications');

        Schema::table('organizers', function (Blueprint $table) {
            if (Schema::hasColumn('organizers', 'verification_status')) {
                $table->dropColumn('verification_status');
            }
            if (Schema::hasColumn('organizers', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
        });
    }
};
