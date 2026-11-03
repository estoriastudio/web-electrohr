<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $baseTypes = [
        'poliza_seguro',
        'verificacion',
        'tarjeta_circulacion',
        'ficha_tecnica',
        'manual',
        'licencia_conducir',
        'placas',
        'inspeccion_fisico_mecanica',
    ];

    public function up(): void
    {
        Schema::table('mobile_asset_documents', function (Blueprint $table) {
            $table->enum('document_type', [...$this->baseTypes, 'factura', 'pedimento'])->change();
        });
    }

    public function down(): void
    {
        DB::table('mobile_asset_documents')
            ->whereIn('document_type', ['factura', 'pedimento'])
            ->delete();

        Schema::table('mobile_asset_documents', function (Blueprint $table) {
            $table->enum('document_type', $this->baseTypes)->change();
        });
    }
};
