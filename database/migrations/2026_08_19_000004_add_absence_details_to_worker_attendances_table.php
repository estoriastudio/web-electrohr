<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_attendances', function (Blueprint $table) {
            $table->string('absence_reason')->nullable()->after('attended');
            $table->string('absence_document_path')->nullable()->after('absence_reason');
            $table->index(['date', 'absence_reason']);
        });
    }

    public function down(): void
    {
        Schema::table('worker_attendances', function (Blueprint $table) {
            $table->dropIndex(['date', 'absence_reason']);
            $table->dropColumn(['absence_reason', 'absence_document_path']);
        });
    }
};