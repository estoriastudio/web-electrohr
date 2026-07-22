<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->text('deletion_comment')->nullable()->after('observations');
            $table->timestamp('archived_at')->nullable()->after('deletion_comment');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropColumn(['deletion_comment', 'archived_at']);
            $table->dropSoftDeletes();
        });
    }
};
