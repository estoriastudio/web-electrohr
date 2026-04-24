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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained('purchase_order_milestones')->cascadeOnDelete();
            $table->string('folio');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->enum('status', ['por_autorizar', 'autorizado', 'pagado'])->default('por_autorizar');
            $table->string('reference_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
