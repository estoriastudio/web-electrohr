<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_work_id')->nullable()->constrained('project_works')->nullOnDelete();
            $table->foreignId('worker_group_id')->nullable()->constrained('worker_groups')->nullOnDelete();
            $table->foreignId('position_category_id')->nullable()->constrained('position_categories')->nullOnDelete();
            $table->decimal('base_salary', 10, 2);
            $table->unsignedTinyInteger('meal_days')->default(0);
            $table->decimal('meal_amount_per_day', 10, 2)->default(0);
            $table->decimal('meal_total_amount', 10, 2)->default(0);
            $table->boolean('sunday_worked')->default(false);
            $table->decimal('sunday_amount', 10, 2)->default(0);
            $table->decimal('lost_material_amount', 10, 2)->default(0);
            $table->decimal('loan_amount', 10, 2)->default(0);
            $table->decimal('infonavit_amount', 10, 2)->default(0);
            $table->decimal('savings_fund_amount', 10, 2)->default(0);
            $table->unsignedTinyInteger('absence_days')->default(0);
            $table->decimal('absence_amount', 10, 2)->default(0);
            $table->decimal('extras_amount', 10, 2)->default(0);
            $table->decimal('incentive_amount', 10, 2)->default(0);
            $table->decimal('subtotal_amount', 10, 2)->default(0);
            $table->decimal('fiscal_amount', 10, 2)->default(0);
            $table->decimal('complement_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['payroll_period_id', 'worker_id']);
            $table->index(['project_work_id', 'worker_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
    }
};