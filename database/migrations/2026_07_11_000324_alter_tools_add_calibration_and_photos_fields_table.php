<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->boolean('requires_calibration')->default(false)->after('status');
            $table->string('photo1')->nullable()->after('requires_calibration');
            $table->string('photo2')->nullable()->after('photo1');
            $table->string('photo3')->nullable()->after('photo2');
        });
        DB::statement("ALTER TABLE tools MODIFY status ENUM('active','inactive','in_service') DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tools MODIFY status ENUM('active','inactive') DEFAULT 'active'");

        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn(['requires_calibration', 'photo1', 'photo2', 'photo3']);
        });
    }
};
