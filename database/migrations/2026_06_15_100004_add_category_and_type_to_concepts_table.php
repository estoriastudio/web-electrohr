<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concepts', function (Blueprint $table) {
            $table->enum('type', ['materiales', 'mantenimiento'])->default('materiales')->after('status');
            $table->foreignId('concept_category_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->foreignId('concept_subcategory_id')->nullable()->after('concept_category_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('concepts', function (Blueprint $table) {
            $table->dropForeign(['concept_category_id']);
            $table->dropForeign(['concept_subcategory_id']);
            $table->dropColumn(['type', 'concept_category_id', 'concept_subcategory_id']);
        });
    }
};
