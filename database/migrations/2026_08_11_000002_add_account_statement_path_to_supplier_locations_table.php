<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_locations', function (Blueprint $table) {
            $table->string('account_statement_path')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_locations', function (Blueprint $table) {
            $table->dropColumn('account_statement_path');
        });
    }
};