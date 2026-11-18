<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4: seguimiento del evento hasta su cierre. Un hito pagado sigue abierto si falta entrega o
 * factura aceptada; Compras anota el seguimiento y una fecha compromiso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_milestones', function (Blueprint $table) {
            $table->text('followup_note')->nullable();
            $table->date('followup_due_date')->nullable();
            $table->foreignId('followup_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('followup_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_milestones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('followup_updated_by');
            $table->dropColumn(['followup_note', 'followup_due_date', 'followup_updated_at']);
        });
    }
};
