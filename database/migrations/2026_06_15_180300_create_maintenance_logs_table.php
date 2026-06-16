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
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_asset_id')->constrained()->cascadeOnDelete();
            $table->string('folio')->nullable();                  // número de seguimiento
            $table->string('inspection_file')->nullable();        // ruta S3 del archivo de inspección
            $table->date('maintenance_date');                     // fecha en que se realizó
            $table->date('next_maintenance_date')->nullable();    // fecha del próximo mantenimiento
            $table->text('notes')->nullable();                    // observaciones
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};
