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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('status')->index();
            $table->string('admin_role', 50)->nullable()->after('is_admin')->index();
            $table->json('admin_permissions')->nullable()->after('admin_role');
            $table->timestamp('suspended_at')->nullable()->after('admin_permissions');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_admin']);
            $table->dropIndex(['admin_role']);
            $table->dropColumn([
                'is_admin',
                'admin_role',
                'admin_permissions',
                'suspended_at',
                'suspension_reason',
            ]);
        });
    }
};
