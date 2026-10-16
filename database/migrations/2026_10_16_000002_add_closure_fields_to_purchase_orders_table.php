<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('closure_note')->nullable();
        });

        // Las OCs que ya se consideraban completas (entregadas y pagadas) quedan cerradas.
        DB::table('purchase_orders')
            ->where('delivery_status', 'entregado')
            ->where('amount', '>', 0)
            ->whereRaw(
                "COALESCE((SELECT SUM(payments.amount)
                    FROM payments
                    INNER JOIN purchase_order_milestones
                        ON purchase_order_milestones.id = payments.milestone_id
                    WHERE purchase_order_milestones.purchase_order_id = purchase_orders.id
                        AND payments.status = 'pagado'), 0) >= purchase_orders.amount"
            )
            ->update([
                'closed_at' => now(),
                'closure_note' => 'Cierre automático al implementar el estatus de cierre (OC entregada y pagada).',
            ]);
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['closed_at', 'closure_note']);
        });
    }
};
