<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('incentives', 'worker_id')) {
            return;
        }

        Schema::dropIfExists('payroll_line_extra_payments');
        Schema::dropIfExists('incentives');

        DB::table('payroll_lines')->orderBy('id')->each(function (object $line): void {
            $incentiveAmount = (float) $line->incentive_amount;

            DB::table('payroll_lines')->where('id', $line->id)->update([
                'incentive_amount' => 0,
                'subtotal_amount' => round((float) $line->subtotal_amount - $incentiveAmount, 2),
                'total_amount' => round((float) $line->total_amount - $incentiveAmount, 2),
                'complement_amount' => round((float) $line->complement_amount - $incentiveAmount, 2),
            ]);
        });

        $this->createIncentivesTable();
        $this->createPayrollLineExtraPaymentsTable();
    }

    public function down(): void
    {
        // The prior global-rate schema cannot be reconstructed from worker incentives.
    }

    private function createIncentivesTable(): void
    {
        Schema::create('incentives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('rate_type');
            $table->date('incentive_date');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['worker_id', 'incentive_date', 'category', 'rate_type']);
            $table->index(['worker_id', 'status', 'incentive_date']);
        });
    }

    private function createPayrollLineExtraPaymentsTable(): void
    {
        Schema::create('payroll_line_extra_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incentive_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->unique('incentive_id');
        });
    }
};