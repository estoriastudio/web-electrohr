<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_estimates', function (Blueprint $table) {
            $table->string('liquidation_slip_path')->nullable()->after('spei_receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('project_estimates', function (Blueprint $table) {
            $table->dropColumn('liquidation_slip_path');
        });
    }
};
