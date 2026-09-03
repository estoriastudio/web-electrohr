<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_files', function (Blueprint $table) {
            $table->string('professional_title_path')->nullable();
            $table->string('professional_license_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('worker_files', function (Blueprint $table) {
            $table->dropColumn(['professional_title_path', 'professional_license_path']);
        });
    }
};