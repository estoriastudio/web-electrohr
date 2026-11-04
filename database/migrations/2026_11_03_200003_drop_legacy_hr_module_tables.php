<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retiro del módulo anterior de RH. Solo se conservan workers, worker_groups,
     * worker_files, worker_dc3s, worker_terminations y position_categories.
     */
    private const TABLES = [
        'payroll_line_extra_payments',
        'piecework_daily_amounts',
        'worker_attendances',
        'incentives',
        'payroll_lines',
        'piecework_weekly_entries',
        'payroll_periods',
        'worker_vacations',
        'worker_group_members',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Sin retorno: el equipo confirmó que estos datos no se usan (HR-005).
    }
};
