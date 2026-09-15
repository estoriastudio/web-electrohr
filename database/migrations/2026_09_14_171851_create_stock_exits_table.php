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
        Schema::create('stock_exits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('tool_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('exit_type', ['definitive', 'tool_loan']);
            $table->string('voucher_number', 100);
            $table->foreignId('recipient_worker_id')->nullable()->constrained('workers')->restrictOnDelete();
            $table->string('recipient_name', 150)->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_work_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->date('exited_at');
            $table->date('expected_return_at')->nullable();
            $table->enum('status', ['completed', 'open', 'returned'])->default('completed');
            $table->foreignId('return_stock_entry_id')->nullable()->constrained('stock_entries')->nullOnDelete();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['concept_id', 'exited_at']);
            $table->index(['tool_id', 'status', 'expected_return_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_exits');
    }
};
