<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_project_works', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            $table->foreignId('project_work_id')
                ->constrained('project_works')
                ->cascadeOnDelete();
            $table->primary(['purchase_order_id', 'project_work_id']);
        });

        DB::table('purchase_orders')
            ->whereNotNull('project_work_id')
            ->orderBy('id')
            ->each(function ($purchaseOrder) {
                DB::table('purchase_order_project_works')->insertOrIgnore([
                    'purchase_order_id' => $purchaseOrder->id,
                    'project_work_id' => $purchaseOrder->project_work_id,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_project_works');
    }
};
