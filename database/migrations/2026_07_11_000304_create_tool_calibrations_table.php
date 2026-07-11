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
        Schema::create('tool_calibrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->string('folio', 60)->nullable();
            $table->string('file_path')->nullable();
            $table->date('calibration_date');
            $table->date('expiry_date')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['tool_id', 'calibration_date']);
            $table->index(['tool_id', 'expiry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_calibrations');
    }
};
