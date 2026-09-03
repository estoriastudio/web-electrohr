<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_works', function (Blueprint $table) {
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resident_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_works', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_user_id');
            $table->dropConstrainedForeignId('resident_user_id');
        });
    }
};