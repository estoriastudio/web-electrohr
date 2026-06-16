<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreignId('concept_category_id')
                ->nullable()
                ->after('supply_category')
                ->constrained('concept_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropForeign(['concept_category_id']);
            $table->dropColumn('concept_category_id');
        });
    }
};
