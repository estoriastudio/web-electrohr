<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('material_request_item_project_works');

        Schema::create('material_request_item_project_works', function (Blueprint $table) {
            $table->unsignedBigInteger('material_request_item_id');
            $table->unsignedBigInteger('project_work_id');
            $table->decimal('quantity', 12, 2);
            $table->foreign('material_request_item_id', 'mripw_item_fk')
                ->references('id')
                ->on('material_request_items')
                ->cascadeOnDelete();
            $table->foreign('project_work_id', 'mripw_work_fk')
                ->references('id')
                ->on('project_works')
                ->cascadeOnDelete();
            $table->primary(['material_request_item_id', 'project_work_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_request_item_project_works');
    }
};