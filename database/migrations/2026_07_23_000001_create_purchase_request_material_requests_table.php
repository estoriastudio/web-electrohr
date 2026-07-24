<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_request_material_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')
                ->constrained('purchase_requests')
                ->cascadeOnDelete();
            $table->foreignId('material_request_id')
                ->constrained('material_requests')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['purchase_request_id', 'material_request_id'],
                'pr_mr_unique_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_material_requests');
    }
};
