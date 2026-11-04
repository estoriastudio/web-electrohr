<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_day_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_work_id')->constrained('project_works')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_work_id', 'date']);
        });

        Schema::create('attendance_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_attendance_id')->nullable()->constrained('worker_attendances')->nullOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('project_work_id')->nullable()->constrained('project_works')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field');
            $table->text('before')->nullable();
            $table->text('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['worker_id', 'date']);
        });

        Schema::create('weekly_incentive_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->date('payroll_period_start');
            $table->foreignId('project_work_id')->constrained('project_works')->restrictOnDelete();
            $table->string('category');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['worker_id', 'payroll_period_start']);
        });

        Schema::create('work_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('from_project_work_id')->constrained('project_works')->restrictOnDelete();
            $table->foreignId('to_project_work_id')->constrained('project_works')->restrictOnDelete();
            $table->string('fraction');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['worker_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_supports');
        Schema::dropIfExists('weekly_incentive_letters');
        Schema::dropIfExists('attendance_histories');
        Schema::dropIfExists('attendance_day_closures');
    }
};
