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
        Schema::create('project_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->text('name');

            $table->string('supervisor')->nullable();
            $table->string('resident')->nullable();

            $table->string('contract_number')->nullable();

            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();

            $table->string('contract_value')->nullable();
            $table->string('currency')->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('project_works');
        Schema::enableForeignKeyConstraints();
    }
};
