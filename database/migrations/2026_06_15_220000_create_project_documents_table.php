<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('document_type', ['licitacion', 'contratacion', 'entrega_recepcion']);
            $table->string('file_path')->nullable();       // ruta en S3
            $table->date('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};
