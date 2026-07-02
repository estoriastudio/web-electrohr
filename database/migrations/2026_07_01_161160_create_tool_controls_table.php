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
        Schema::create('tool_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->foreignId('project_work_id')->constrained('project_works')->cascadeOnDelete();
            $table->enum('loan_type', ['fixed', 'provisional']);
            $table->date('checkout_date');
            $table->date('review_date')->nullable();
            $table->json('observations')->nullable();
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->timestamps();

            $table->index(['project_work_id', 'status']);
            $table->index(['tool_id', 'checkout_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_controls');
    }
};
