<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_milestones', function (Blueprint $table) {
            $table->foreignId('split_from_milestone_id')->nullable()->constrained('purchase_order_milestones')->nullOnDelete();
        });

        Schema::create('purchase_order_milestone_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained('purchase_order_milestones')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('previous_amount', 15, 2);
            $table->decimal('new_amount', 15, 2);
            $table->string('previous_value_type');
            $table->decimal('previous_value', 15, 2);
            $table->string('reason', 1000);
            $table->foreignId('partial_milestone_id')->nullable()
                ->constrained('purchase_order_milestones', indexName: 'milestone_adjustments_partial_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_milestone_adjustments');
        Schema::table('purchase_order_milestones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('split_from_milestone_id');
        });
    }
};
