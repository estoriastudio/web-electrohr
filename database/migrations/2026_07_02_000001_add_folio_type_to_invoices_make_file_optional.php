<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_invoices', function (Blueprint $table) {
            $table->string('folio')->nullable()->after('purchase_order_id');
            $table->string('file_name')->nullable()->change();
            $table->string('file_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_invoices', function (Blueprint $table) {
            $table->dropColumn(['folio']);
            $table->string('file_name')->nullable(false)->change();
            $table->string('file_path')->nullable(false)->change();
        });
    }
};
