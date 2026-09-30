<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index('buyer_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['buyer_id']);
            $table->dropIndex(['buyer_id']);
            $table->dropColumn('buyer_id');
        });
    }
};