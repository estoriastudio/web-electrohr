<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('por_autorizar', 'autorizado', 'pagado', 'rechazado', 'pospuesto', 'sustituido') NOT NULL DEFAULT 'por_autorizar'");
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->decimal('applied_amount', 15, 2)->nullable();
            $table->string('origin', 20)->nullable();
            $table->foreignId('source_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('replaced_by_payment_id')->nullable()->constrained('payments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('payments')->where('status', 'sustituido')->exists()) {
            throw new RuntimeException('No se puede revertir: existen pagos con estatus sustituido.');
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaced_by_payment_id');
            $table->dropConstrainedForeignId('source_payment_id');
            $table->dropColumn(['original_amount', 'paid_amount', 'applied_amount', 'origin']);
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('por_autorizar', 'autorizado', 'pagado', 'rechazado', 'pospuesto') NOT NULL DEFAULT 'por_autorizar'");
        }
    }
};
