<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_terminations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_work_id')->nullable()->constrained('project_works')->nullOnDelete();
            $table->string('job_title')->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->string('termination_type');
            $table->string('reason')->nullable();
            $table->date('termination_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['termination_type', 'termination_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_terminations');
    }
};