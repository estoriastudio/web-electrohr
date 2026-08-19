<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('incentives');
    }
};