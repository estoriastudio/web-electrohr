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
        Schema::create('mobile_assets', function (Blueprint $table) {
            $table->id();

            $table->string('name');                         // Nombre de la maquinaria
            $table->string('folio')->nullable();            // Folio
            $table->string('brand')->nullable();            // Marca
            $table->string('asset_function')->nullable();   // Función
            $table->string('type')->nullable();             // Tipo: 'movil', 'maquinaria', 'equipo_menor'
            $table->string('plates')->nullable();           // Placas (solo tipo móvil)
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_assets');
    }
};
