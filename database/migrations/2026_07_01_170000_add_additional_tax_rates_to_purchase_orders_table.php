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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('isr_rate', 5, 2)->nullable()->after('tax_rate');
            $table->decimal('retention_iva_rate', 5, 2)->nullable()->after('isr_rate');
            $table->decimal('retention_isr_rate', 5, 2)->nullable()->after('retention_iva_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['isr_rate', 'retention_iva_rate', 'retention_isr_rate']);
        });
    }
};
