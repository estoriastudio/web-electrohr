<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            // Quitar FK antes de drop
            $table->dropForeign(['project_work_id']);
            $table->dropColumn('project_work_id');

            // Alterar el ENUM status para añadir 'sent_to_warehouse'
            // MySQL requiere redefinir el ENUM completo
            $table->enum('status', ['pending', 'sent_to_warehouse', 'linked', 'completed'])
                  ->default('pending')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreignId('project_work_id')
                  ->nullable()
                  ->constrained('project_works')
                  ->nullOnDelete();

            $table->enum('status', ['pending', 'linked', 'completed'])
                  ->default('pending')
                  ->change();
        });
    }
};
