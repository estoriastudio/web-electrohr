<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_work_id')->constrained('project_works')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->date('start_date')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'project_work_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_requests');
    }
};
