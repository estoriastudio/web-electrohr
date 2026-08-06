<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('isr_rate', 7, 4)->nullable()->change();
            $table->decimal('retention_iva_rate', 7, 4)->nullable()->change();
            $table->decimal('retention_isr_rate', 7, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('isr_rate', 5, 2)->nullable()->change();
            $table->decimal('retention_iva_rate', 5, 2)->nullable()->change();
            $table->decimal('retention_isr_rate', 5, 2)->nullable()->change();
        });
    }
};