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
        Schema::create('stock_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained()->restrictOnDelete();
            $table->enum('certificate_type', ['origin', 'safety']);
            $table->string('file_name', 255);
            $table->string('file_path', 1024);
            $table->string('disk', 50)->default('s3');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['concept_id', 'certificate_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_certificates');
    }
};
