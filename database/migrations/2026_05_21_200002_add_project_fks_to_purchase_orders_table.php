<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('mobile_asset_id')
                  ->constrained('projects')->nullOnDelete();
            $table->foreignId('project_work_id')->nullable()->after('project_id')
                  ->constrained('project_works')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['project_work_id']);
            $table->dropColumn(['project_id', 'project_work_id']);
        });
    }
};
