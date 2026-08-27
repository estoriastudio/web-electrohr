<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_item_project_works', function (Blueprint $table) {
            $table->foreignId('committed_by')
                ->nullable()
                ->after('is_committed')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('committed_at')->nullable()->after('committed_by');
        });
    }

    public function down(): void
    {
        Schema::table('material_request_item_project_works', function (Blueprint $table) {
            $table->dropForeign(['committed_by']);
            $table->dropColumn(['committed_by', 'committed_at']);
        });
    }
};
