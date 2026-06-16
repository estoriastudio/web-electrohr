<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            // Usuario de compras asignado para revisar la SOLCOM
            $table->foreignId('assigned_to')
                  ->nullable()
                  ->after('requested_by')
                  ->constrained('users')
                  ->nullOnDelete();

            // Ampliar el ENUM de status con los nuevos estados del flujo
            $table->enum('status', [
                'pending',
                'linked',
                'sent_to_purchasing',
                'changes_requested',
                'completed',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn('assigned_to');

            $table->enum('status', ['pending', 'linked', 'completed'])
                  ->default('pending')->change();
        });
    }
};
