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
        Schema::create('project_agreement_project_work', function (Blueprint $table) {
            $table->foreignId('project_agreement_id')
                ->constrained('project_agreements')
                ->cascadeOnDelete();
            $table->foreignId('project_work_id')
                ->constrained('project_works')
                ->cascadeOnDelete();
            $table->primary(['project_agreement_id', 'project_work_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_agreement_project_work');
    }
};
