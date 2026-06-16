<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concept_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['materiales', 'mantenimiento'])->default('materiales');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concept_categories');
    }
};
