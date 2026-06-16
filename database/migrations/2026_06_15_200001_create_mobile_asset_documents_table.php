<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_asset_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_asset_id')->constrained()->cascadeOnDelete();
            $table->enum('document_type', [
                'poliza_seguro',
                'verificacion',
                'tarjeta_circulacion',
                'ficha_tecnica',
                'manual',
                'licencia_conducir',
                'placas',
                'inspeccion_fisico_mecanica',
            ]);
            $table->string('file_path')->nullable();        // ruta en S3
            $table->date('expiry_date')->nullable();        // fecha de vencimiento (null = sin vencimiento)
            $table->date('uploaded_at')->nullable();        // fecha en que se subió el documento
            $table->timestamps();

            $table->unique(['mobile_asset_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_asset_documents');
    }
};
