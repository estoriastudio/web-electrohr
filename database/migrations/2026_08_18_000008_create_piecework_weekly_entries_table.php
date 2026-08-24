<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piecework_weekly_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_work_id')->nullable()->constrained('project_works')->nullOnDelete();
            $table->foreignId('worker_group_id')->nullable()->constrained('worker_groups')->nullOnDelete();
            $table->foreignId('foreman_worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->foreignId('position_category_id')->nullable()->constrained('position_categories')->nullOnDelete();
            $table->unsignedTinyInteger('week_number');
            $table->unsignedSmallInteger('year');
            $table->decimal('meals_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['worker_id', 'week_number', 'year']);
            $table->index(['project_work_id', 'worker_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piecework_weekly_entries');
    }
};