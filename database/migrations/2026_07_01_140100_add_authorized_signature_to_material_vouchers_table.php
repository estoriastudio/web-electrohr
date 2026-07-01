<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_vouchers', function (Blueprint $table) {
            $table->longText('authorized_signature')->nullable()->after('authorized_signature_name');
        });
    }

    public function down(): void
    {
        Schema::table('material_vouchers', function (Blueprint $table) {
            $table->dropColumn('authorized_signature');
        });
    }
};
