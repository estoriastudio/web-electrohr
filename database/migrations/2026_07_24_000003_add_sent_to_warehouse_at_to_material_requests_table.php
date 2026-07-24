<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('material_requests', 'sent_to_warehouse_at')) {
            Schema::table('material_requests', function (Blueprint $table) {
                $table->timestamp('sent_to_warehouse_at')->nullable()->after('status');
                $table->index(['status', 'sent_to_warehouse_at'], 'mr_status_sent_to_warehouse_at_idx');
            });
        }

        // Backfill opcional para registros historicos usando notificaciones existentes.
        DB::statement(
            "UPDATE material_requests mr
             SET mr.sent_to_warehouse_at = (
                 SELECT MIN(n.created_at)
                 FROM notifications n
                 WHERE n.type = 'material_request'
                   AND n.model_id = mr.id
                   AND n.data LIKE '%enviada a Almac%'
             )
             WHERE mr.sent_to_warehouse_at IS NULL"
        );
    }

    public function down(): void
    {
        if (!Schema::hasColumn('material_requests', 'sent_to_warehouse_at')) {
            return;
        }

        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropIndex('mr_status_sent_to_warehouse_at_idx');
            $table->dropColumn('sent_to_warehouse_at');
        });
    }
};
