<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
        });

        Schema::table('material_request_item_project_works', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
            $table->decimal('committed_quantity', 15, 4)->change();
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->decimal('requested_quantity', 12, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->decimal('requested_quantity', 12, 2)->change();
        });

        Schema::table('material_request_item_project_works', function (Blueprint $table) {
            $table->decimal('committed_quantity', 15, 2)->change();
            $table->decimal('quantity', 12, 2)->change();
        });

        Schema::table('material_request_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->change();
        });
    }
};