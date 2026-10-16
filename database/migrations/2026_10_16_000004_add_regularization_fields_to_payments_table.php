<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('regularization_invoice_id')->nullable()->constrained('purchase_order_invoices')->nullOnDelete();
            $table->timestamp('regularized_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('regularization_invoice_id');
            $table->dropColumn('regularized_at');
        });
    }
};
