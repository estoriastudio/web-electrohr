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
        Schema::table('concepts', function (Blueprint $table) {
            $table->string('warehouse_location', 255)->default('Almacén principal')->after('unit');
            $table->decimal('minimum_stock', 14, 3)->nullable()->after('unit_price');
            $table->decimal('maximum_stock', 14, 3)->nullable()->after('minimum_stock');
            $table->boolean('requires_origin_certificate')->default(false)->after('maximum_stock');
            $table->boolean('requires_safety_certificate')->default(false)->after('requires_origin_certificate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('concepts', function (Blueprint $table) {
            $table->dropColumn([
                'warehouse_location',
                'minimum_stock',
                'maximum_stock',
                'requires_origin_certificate',
                'requires_safety_certificate',
            ]);
        });
    }
};
