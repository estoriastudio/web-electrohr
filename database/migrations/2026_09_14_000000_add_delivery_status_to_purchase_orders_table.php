<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('delivery_status', 20)->default('por_entregar')->after('is_delivered');
            $table->text('delivery_evidence')->nullable()->after('delivery_status');
        });

        DB::table('purchase_orders')
            ->where('is_delivered', true)
            ->update(['delivery_status' => 'entregado']);
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_status', 'delivery_evidence']);
        });
    }
};
