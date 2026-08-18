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
        Schema::create('worker_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_group_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('week_number');
            $table->unsignedSmallInteger('year');
            $table->string('role')->nullable();
            $table->boolean('attended')->default(true);
            $table->decimal('overtime_hours', 4, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['worker_id', 'date']);
            $table->index(['worker_group_id', 'year', 'week_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worker_attendances');
    }
};
