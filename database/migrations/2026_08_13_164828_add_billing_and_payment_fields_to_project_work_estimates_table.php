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
        Schema::table('project_work_estimates', function (Blueprint $table) {
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->date('payment_date')->nullable()->after('spei_amount');
            $table->enum('status', ['pendiente', 'pagada'])->default('pendiente')->after('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_work_estimates', function (Blueprint $table) {
            $table->dropColumn(['invoice_date', 'payment_date', 'status']);
        });
    }
};
