<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_assets', function (Blueprint $table) {
            // Nuevos campos de ficha técnica
            $table->string('model')->nullable()->after('brand');
            $table->string('year', 10)->nullable()->after('model');
            $table->string('serial')->nullable()->after('year');
            $table->string('color')->nullable()->after('serial');
            $table->string('operator')->nullable()->after('color');

            // Fotos (rutas S3)
            $table->string('photo1')->nullable()->after('operator');
            $table->string('photo2')->nullable()->after('photo1');
            $table->string('photo3')->nullable()->after('photo2');

            // Cambiar tipo de bien (drop y recrear con nuevos valores)
            $table->dropColumn('type');
            $table->enum('type', ['parque_vehicular', 'maquinaria_pesada', 'semiremolque'])
                  ->nullable()
                  ->after('asset_function');

            // Cambiar estatus (drop y recrear con nuevos valores)
            $table->dropColumn('status');
            $table->enum('status', ['activo', 'vendido', 'obsoleto', 'reparacion'])
                  ->default('activo')
                  ->after('plates');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_assets', function (Blueprint $table) {
            $table->dropColumn(['model', 'year', 'serial', 'color', 'operator', 'photo1', 'photo2', 'photo3']);
            $table->dropColumn('type');
            $table->enum('type', ['movil', 'maquinaria', 'equipo_menor'])->nullable()->after('asset_function');
            $table->dropColumn('status');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('plates');
        });
    }
};
