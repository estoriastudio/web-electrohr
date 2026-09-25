<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            $table->boolean('is_adjustment')->default(false);
        });

        Schema::table('stock_exits', function (Blueprint $table) {
            $table->boolean('is_adjustment')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            $table->dropColumn('is_adjustment');
        });

        Schema::table('stock_exits', function (Blueprint $table) {
            $table->dropColumn('is_adjustment');
        });
    }
};