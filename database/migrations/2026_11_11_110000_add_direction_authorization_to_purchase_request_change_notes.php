<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4: un cambio de SOLCOM marcado como ajuste comercial (precio, proveedor, condiciones) espera la
 * autorización de Dirección antes de resolverse. Los demás cambios siguen igual (decisión 2026-11-11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_change_notes', function (Blueprint $table) {
            $table->boolean('is_commercial')->default(false)->after('text');
            $table->string('direction_status', 20)->nullable()->after('is_commercial'); // pending | approved | rejected
            $table->foreignId('direction_decided_by')->nullable()->after('direction_status')->constrained('users')->nullOnDelete();
            $table->timestamp('direction_decided_at')->nullable()->after('direction_decided_by');
            $table->text('direction_note')->nullable()->after('direction_decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_change_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('direction_decided_by');
            $table->dropColumn(['is_commercial', 'direction_status', 'direction_decided_at', 'direction_note']);
        });
    }
};
