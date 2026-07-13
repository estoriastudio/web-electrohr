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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('portal_user_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('portal_access_enabled')
                ->default(false)
                ->after('portal_user_id');

            $table->timestamp('portal_access_activated_at')
                ->nullable()
                ->after('portal_access_enabled');

            $table->timestamp('portal_access_deactivated_at')
                ->nullable()
                ->after('portal_access_activated_at');

            $table->foreignId('portal_access_managed_by')
                ->nullable()
                ->after('portal_access_deactivated_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->unique('portal_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['portal_user_id']);
            $table->dropConstrainedForeignId('portal_access_managed_by');
            $table->dropColumn('portal_access_deactivated_at');
            $table->dropColumn('portal_access_activated_at');
            $table->dropColumn('portal_access_enabled');
            $table->dropConstrainedForeignId('portal_user_id');
        });
    }
};
