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
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('tool_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('entry_type', ['purchase', 'tool_return']);
            $table->string('purchase_reference', 255)->nullable();
            $table->decimal('quantity', 14, 3);
            $table->date('received_at');
            $table->string('invoice_file_name', 255)->nullable();
            $table->string('invoice_file_path', 1024)->nullable();
            $table->string('invoice_disk', 50)->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['concept_id', 'received_at']);
            $table->index(['tool_id', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_entries');
    }
};
