<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['purchase_orders', 'material_vouchers'];

    // El cascade borraba OC (y sus facturas, hitos y partidas) sin pasar por Eloquent ni la auditoría.
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->softDeletes();
        });

        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['supplier_id']);
                $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['supplier_id']);
                $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            });
        }

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
