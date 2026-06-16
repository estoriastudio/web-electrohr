<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Tasa de impuesto aplicable: 0, 8, 16 o null = exento
            $table->decimal('tax_rate', 5, 2)->default(16.00)->after('amount');
        });

        Schema::table('purchase_order_milestones', function (Blueprint $table) {
            // Tipo de condición de pago: contado | credito
            $table->enum('payment_condition', ['contado', 'credito'])
                  ->default('credito')
                  ->after('type');

            // Indica si el hito es un anticipo (puede coexistir con type)
            $table->boolean('is_advance')->default(false)->after('payment_condition');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });

        Schema::table('purchase_order_milestones', function (Blueprint $table) {
            $table->dropColumn(['payment_condition', 'is_advance']);
        });
    }
};
