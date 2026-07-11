<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('status');
            $table->text('deletion_comment')->nullable()->after('observations');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('archived_at');
            $table->dropColumn('deletion_comment');
            $table->dropSoftDeletes();
        });
    }
};
