<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_exits', function (Blueprint $table) {
            $table->string('voucher_number', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('stock_exits')->whereNull('voucher_number')->update(['voucher_number' => '']);

        Schema::table('stock_exits', function (Blueprint $table) {
            $table->string('voucher_number', 100)->nullable(false)->change();
        });
    }
};
