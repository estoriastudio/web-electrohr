<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('purchase_order_invoice_id')->constrained('purchase_order_invoices')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['payment_id', 'purchase_order_invoice_id'], 'invoice_payment_allocation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payment_allocations');
    }
};