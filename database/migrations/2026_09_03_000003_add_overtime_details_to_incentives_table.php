<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incentives', function (Blueprint $table) {
            $table->string('rate_type')->nullable()->change();
            $table->decimal('overtime_hours', 5, 2)->nullable();
            $table->decimal('overtime_hourly_rate', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incentives', function (Blueprint $table) {
            $table->dropColumn(['overtime_hours', 'overtime_hourly_rate']);
            $table->string('rate_type')->nullable(false)->change();
        });
    }
};