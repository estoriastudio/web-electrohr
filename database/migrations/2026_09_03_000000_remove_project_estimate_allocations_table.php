<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_estimate_allocations');
    }

    public function down(): void
    {
        throw new \RuntimeException('La eliminación del desglose de estimaciones por obra no se puede revertir automáticamente.');
    }
};