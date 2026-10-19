<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_milestones', function (Blueprint $table) {
            $table->string('credit_start', 30)->nullable();
            $table->unsignedSmallInteger('credit_days')->nullable();
        });

        Schema::table('purchase_order_invoices', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable();
        });

        // Los hitos a crédito existentes conservan su regla actual: factura aceptada como inicio del plazo.
        DB::table('purchase_order_milestones')
            ->where('payment_condition', 'credito')
            ->update(['credit_start' => 'invoice_validation']);

        DB::table('purchase_order_invoices')
            ->where('status', 'aceptada')
            ->update(['accepted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('purchase_order_invoices', function (Blueprint $table) {
            $table->dropColumn('accepted_at');
        });

        Schema::table('purchase_order_milestones', function (Blueprint $table) {
            $table->dropColumn(['credit_start', 'credit_days']);
        });
    }
};
