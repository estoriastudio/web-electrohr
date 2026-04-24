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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['materiales_servicios', 'mantenimiento']);
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('project')->nullable();
            $table->string('site')->nullable();
            $table->enum('currency', ['MXN', 'USD', 'EUR'])->default('MXN');
            $table->decimal('amount', 15, 2)->default(0);
            $table->enum('status', ['emitida', 'pendiente', 'autorizada'])->default('emitida');
            $table->enum('recurrence_type', ['unico', 'recurrente'])->default('unico');
            $table->string('recurrence_frequency')->nullable();
            $table->date('recurrence_start_date')->nullable();
            $table->date('recurrence_end_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
