<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('spei_receipt_path')->nullable()->after('reference_number');
            $table->string('spei_receipt_name')->nullable()->after('spei_receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['spei_receipt_path', 'spei_receipt_name']);
        });
    }
};
