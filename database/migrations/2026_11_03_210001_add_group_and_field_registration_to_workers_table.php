<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->foreignId('worker_group_id')->nullable()->after('position_category_id')
                ->constrained('worker_groups')->nullOnDelete();
            $table->foreignId('registered_by_user_id')->nullable()->after('worker_group_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('field_registered_at')->nullable()->after('registered_by_user_id');
            $table->date('profile_complete_deadline')->nullable()->after('field_registered_at');
            $table->boolean('file_incomplete')->default(false)->after('profile_complete_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('worker_group_id');
            $table->dropConstrainedForeignId('registered_by_user_id');
            $table->dropColumn(['field_registered_at', 'profile_complete_deadline', 'file_incomplete']);
        });
    }
};
