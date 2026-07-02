<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_annexes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                  ->unique()
                  ->constrained('purchase_orders')
                  ->cascadeOnDelete();

            // Shared info block
            $table->string('client_name')->nullable();
            $table->string('provider_name')->nullable();

            // Annex 1: Condiciones Generales de Compra
            $table->boolean('annex_condiciones')->default(false);
            $table->string('penalidad_porcentaje', 50)->nullable();
            $table->string('penalidad_numero', 50)->nullable();
            $table->string('nombre_aceptacion')->nullable();

            // Annex 2: Contrato
            $table->boolean('annex_contrato')->default(false);
            $table->longText('contrato_html')->nullable();

            // Annex 3: Índice Dossier de Calidad
            $table->boolean('annex_dossier')->default(false);
            $table->longText('dossier_html')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_annexes');
    }
};
