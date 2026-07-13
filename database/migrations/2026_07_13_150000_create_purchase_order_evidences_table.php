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
        Schema::create('purchase_order_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_order_milestone_id')->nullable()->constrained('purchase_order_milestones')->nullOnDelete();
            $table->foreignId('purchase_order_invoice_id')->nullable()->constrained('purchase_order_invoices')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->enum('source', ['supplier_portal', 'internal'])->default('internal');
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'created_at'], 'po_evidences_po_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_evidences');
    }
};
