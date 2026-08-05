<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_order_invoices', function (Blueprint $table) {
            $table->enum('status', ['en_proceso', 'aceptada', 'rechazada'])
                ->default('en_proceso')
                ->after('folio');
            $table->date('issue_date')->nullable()->after('status');
        });

        // Preserve historical accounting behavior for existing invoices.
        DB::table('purchase_order_invoices')
            ->whereNull('status')
            ->update(['status' => 'aceptada']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_invoices', function (Blueprint $table) {
            $table->dropColumn(['status', 'issue_date']);
        });
    }
};
