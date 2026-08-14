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
        Schema::create('project_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->decimal('contracted_amount', 18, 2);
            $table->decimal('current_amount', 18, 2);
            $table->date('contracted_end_date');
            $table->unsignedInteger('contracted_term_days');
            $table->date('current_end_date');
            $table->string('agreement_number');
            $table->decimal('new_amount', 18, 2);
            $table->date('new_end_date');
            $table->string('appointments_file_path');
            $table->string('appointments_file_name');
            $table->string('appointments_file_mime', 100);
            $table->timestamps();

            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_agreements');
    }
};
