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
            $table->decimal('estimate_amount', 15, 2)->default(0)->after('type');
            $table->decimal('returned_retention_amount', 15, 2)->default(0)->after('estimate_amount');
            $table->decimal('disfp_deduction', 15, 2)->default(0)->after('returned_retention_amount');
            $table->decimal('apaee_deduction', 15, 2)->default(0)->after('disfp_deduction');
            $table->decimal('inc_retention_amount', 15, 2)->default(0)->after('apaee_deduction');
            $table->decimal('vat_retention_amount', 15, 2)->default(0)->after('inc_retention_amount');
            $table->decimal('advance_amortization_amount', 15, 2)->default(0)->after('vat_retention_amount');
            $table->decimal('advance_amortization_vat_amount', 15, 2)->default(0)->after('advance_amortization_amount');
            $table->decimal('funeral_expense_amount', 15, 2)->default(0)->after('advance_amortization_vat_amount');
            $table->decimal('delay_penalty_amount', 15, 2)->default(0)->after('funeral_expense_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_work_estimates', function (Blueprint $table) {
            $table->dropColumn([
                'estimate_amount',
                'returned_retention_amount',
                'disfp_deduction',
                'apaee_deduction',
                'inc_retention_amount',
                'vat_retention_amount',
                'advance_amortization_amount',
                'advance_amortization_vat_amount',
                'funeral_expense_amount',
                'delay_penalty_amount',
            ]);
        });
    }
};
