<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_evidence_id')->nullable()->constrained('purchase_order_evidences')->nullOnDelete();
        });

        // Las OC ya entregadas marcan todos sus conceptos; las de entrega parcial se regularizan después.
        DB::table('purchase_order_items')
            ->whereIn('purchase_order_id', DB::table('purchase_orders')->where('delivery_status', 'entregado')->select('id'))
            ->update(['delivered_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_evidence_id');
            $table->dropConstrainedForeignId('delivered_by');
            $table->dropColumn('delivered_at');
        });
    }
};
