<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('project_work_id')->constrained('project_works')->restrictOnDelete();
            $table->foreignId('worker_group_id')->nullable()->constrained('worker_groups')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('personnel_request_id')->nullable()->constrained('personnel_requests')->nullOnDelete();
            $table->foreignId('replaced_assignment_id')->nullable()->constrained('work_assignments')->nullOnDelete();
            $table->boolean('provisional')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('closed_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['worker_id', 'start_date']);
            $table->index(['project_work_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_assignments');
    }
};
