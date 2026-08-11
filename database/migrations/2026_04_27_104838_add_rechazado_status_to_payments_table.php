<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('por_autorizar', 'autorizado', 'pagado', 'rechazado') NOT NULL DEFAULT 'por_autorizar'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('por_autorizar', 'autorizado', 'pagado') NOT NULL DEFAULT 'por_autorizar'");
    }
};
