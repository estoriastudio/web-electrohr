<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->decimal('purchase_quantity', 12, 4)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->decimal('purchase_quantity', 12, 2)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->change();
        });
    }
};