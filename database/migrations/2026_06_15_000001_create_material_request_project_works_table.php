<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_request_project_works', function (Blueprint $table) {
            $table->foreignId('material_request_id')
                  ->constrained('material_requests')
                  ->cascadeOnDelete();
            $table->foreignId('project_work_id')
                  ->constrained('project_works')
                  ->cascadeOnDelete();
            $table->primary(['material_request_id', 'project_work_id']);
        });

        // Migrar la obra existente de cada SOLMAT al pivot
        DB::table('material_requests')
            ->whereNotNull('project_work_id')
            ->orderBy('id')
            ->each(function ($mr) {
                DB::table('material_request_project_works')->insertOrIgnore([
                    'material_request_id' => $mr->id,
                    'project_work_id'     => $mr->project_work_id,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_request_project_works');
    }
};
