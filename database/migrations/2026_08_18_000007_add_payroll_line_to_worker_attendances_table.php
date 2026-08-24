<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_attendances', function (Blueprint $table) {
            $table->foreignId('payroll_line_id')
                ->nullable()
                ->after('worker_group_id')
                ->constrained('payroll_lines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('worker_attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_line_id');
        });
    }
};