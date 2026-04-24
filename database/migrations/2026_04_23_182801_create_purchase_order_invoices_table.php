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
        Schema::create('purchase_order_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('file_name');          // OC1-FACT1.pdf
            $table->string('file_path');          // invoices/{po_id}/OC1-FACT1.pdf
            $table->decimal('amount', 15, 2);
            $table->enum('currency', ['MXN', 'USD', 'EUR'])->default('MXN');
            $table->timestamp('attached_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('invoice_milestone', function (Blueprint $table) {
            $table->foreignId('purchase_order_invoice_id')
                  ->constrained('purchase_order_invoices')
                  ->cascadeOnDelete();
            $table->foreignId('purchase_order_milestone_id')
                  ->constrained('purchase_order_milestones')
                  ->cascadeOnDelete();
            $table->primary(['purchase_order_invoice_id', 'purchase_order_milestone_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_milestone');
        Schema::dropIfExists('purchase_order_invoices');
    }
};
