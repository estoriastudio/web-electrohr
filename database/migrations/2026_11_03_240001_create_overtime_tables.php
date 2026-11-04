<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->foreignId('project_work_id')->constrained('project_works')->restrictOnDelete();
            $table->date('date');
            $table->unsignedInteger('hours');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'date']);
            $table->index(['worker_id', 'date']);
        });

        Schema::create('overtime_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('project_work_id')->constrained('project_works')->restrictOnDelete();
            $table->foreignId('charge_project_work_id')->constrained('project_works')->restrictOnDelete();
            $table->unsignedInteger('hours');
            $table->string('source');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['worker_id', 'date']);
            $table->index(['project_work_id', 'date']);
        });

        Schema::create('overtime_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_work_id')->constrained('project_works')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_work_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_closures');
        Schema::dropIfExists('overtime_entries');
        Schema::dropIfExists('late_overtime_requests');
    }
};
