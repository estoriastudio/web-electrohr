<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 (V-02): Dirección autoriza la nómina. Flujo: abierta → cerrada (RRHH) → autorizada
 * (Dirección) → pagada (RRHH). El estatus es texto, así que solo se agrega quién y cuándo autorizó.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Algunas bases locales no tienen la tabla aunque su migración figure como corrida.
        if (! Schema::hasTable('payroll_periods')) {
            return;
        }

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->foreignId('authorized_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable()->after('authorized_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payroll_periods', 'authorized_by')) {
            return;
        }

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('authorized_by');
            $table->dropColumn('authorized_at');
        });
    }
};
