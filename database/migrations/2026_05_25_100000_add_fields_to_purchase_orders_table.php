<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('folio')->nullable()->unique()->after('parent_id');
            $table->foreignId('purchase_request_id')
                  ->nullable()
                  ->after('folio')
                  ->constrained('purchase_requests')
                  ->nullOnDelete();
            $table->json('observations')->nullable()->after('status');
            $table->string('elaborated_by')->nullable()->after('observations');
            $table->string('supplier_signatory')->nullable()->after('elaborated_by');
            $table->string('authorized_signatory')->nullable()->after('supplier_signatory');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['purchase_request_id']);
            $table->dropColumn([
                'folio',
                'purchase_request_id',
                'observations',
                'elaborated_by',
                'supplier_signatory',
                'authorized_signatory',
            ]);
        });
    }
};
