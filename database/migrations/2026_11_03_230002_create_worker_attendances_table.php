<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('project_work_id')->constrained('project_works')->restrictOnDelete();
            $table->foreignId('worker_group_id')->nullable()->constrained('worker_groups')->nullOnDelete();
            $table->string('incident');
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->unique(['worker_id', 'date']);
            $table->index(['project_work_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_attendances');
    }
};
