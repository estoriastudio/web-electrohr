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
        Schema::create('purchase_order_milestone_fulfillments', function (Blueprint $table) {
            $table->id();
            // Nombres explícitos: los autogenerados superan el límite de 64 caracteres de MySQL.
            $table->foreignId('purchase_order_milestone_id')
                ->constrained('purchase_order_milestones', indexName: 'po_ms_fulfillments_milestone_fk')
                ->cascadeOnDelete();
            $table->string('fulfillment_type');
            $table->foreignId('evidence_id')->nullable()
                ->constrained('purchase_order_evidences', indexName: 'po_ms_fulfillments_evidence_fk')
                ->nullOnDelete();
            $table->text('comment')->nullable();
            $table->foreignId('recorded_by')->nullable()
                ->constrained('users', indexName: 'po_ms_fulfillments_recorded_by_fk')
                ->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique('purchase_order_milestone_id', 'po_ms_fulfillments_milestone_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_milestone_fulfillments');
    }
};
