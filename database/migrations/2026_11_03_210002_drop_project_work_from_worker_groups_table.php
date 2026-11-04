<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_groups', function (Blueprint $table) {
            $table->dropIndex(['status', 'project_work_id']);
            $table->dropConstrainedForeignId('project_work_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('worker_groups', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->foreignId('project_work_id')->nullable()->after('name')
                ->constrained('project_works')->restrictOnDelete();
            $table->index(['status', 'project_work_id']);
        });
    }
};
