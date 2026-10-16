<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_item_deliveries', function (Blueprint $table) {
            $table->foreignId('milestone_id')->nullable()->after('evidence_id')
                ->constrained('purchase_order_milestones')->nullOnDelete();
        });

        Schema::table('purchase_order_milestones', function (Blueprint $table) {
            $table->timestamp('delivery_confirmed_at')->nullable();
            $table->foreignId('delivery_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('checklist_verified_at')->nullable();
        });

        // Los hitos de OCs ya entregadas conservan su entrega como confirmada.
        DB::table('purchase_order_milestones')
            ->whereIn('purchase_order_id', DB::table('purchase_orders')->where('delivery_status', 'entregado')->select('id'))
            ->update(['delivery_confirmed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('checklist_verified_at');
        });

        Schema::table('purchase_order_milestones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_confirmed_by');
            $table->dropColumn('delivery_confirmed_at');
        });

        Schema::table('purchase_order_item_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('milestone_id');
        });
    }
};
