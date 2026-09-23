<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_entry_items')) {
            Schema::create('stock_entry_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_entry_id')->constrained()->cascadeOnDelete();
                $table->foreignId('concept_id')->constrained()->restrictOnDelete();
                $table->unsignedSmallInteger('line_number');
                $table->decimal('quantity', 14, 3);
                $table->timestamps();

                $table->unique(['stock_entry_id', 'concept_id']);
                $table->unique(['stock_entry_id', 'line_number']);
                $table->index(['concept_id', 'stock_entry_id']);
            });
        }

        if (! Schema::hasTable('stock_exit_items')) {
            Schema::create('stock_exit_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_exit_id')->constrained()->cascadeOnDelete();
                $table->foreignId('concept_id')->constrained()->restrictOnDelete();
                $table->unsignedSmallInteger('line_number');
                $table->decimal('quantity', 14, 3);
                $table->timestamps();

                $table->unique(['stock_exit_id', 'concept_id']);
                $table->unique(['stock_exit_id', 'line_number']);
                $table->index(['concept_id', 'stock_exit_id']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('stock_exit_items');
        Schema::dropIfExists('stock_entry_items');
    }
};