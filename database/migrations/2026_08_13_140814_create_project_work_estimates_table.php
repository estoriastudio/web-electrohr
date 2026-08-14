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
        Schema::create('project_work_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_work_id')
                ->constrained('project_works')
                ->cascadeOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('estimate_number', 100);
            $table->date('estimate_date');
            $table->enum('type', ['estimacion', 'nota_credito', 'anticipo']);
            $table->string('invoice_number', 100)->nullable();
            $table->decimal('invoice_amount', 15, 2)->nullable();
            $table->string('spei_reference', 100)->nullable();
            $table->decimal('spei_amount', 15, 2)->nullable();
            $table->decimal('physical_progress', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_work_id', 'estimate_date']);
            $table->unique(['project_work_id', 'estimate_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_work_estimates');
    }
};
