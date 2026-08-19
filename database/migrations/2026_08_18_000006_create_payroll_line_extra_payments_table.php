<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('payroll_line_extra_payments');
    }
};