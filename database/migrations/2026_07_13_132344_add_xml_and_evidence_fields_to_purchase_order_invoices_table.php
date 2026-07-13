<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_order_invoices', function (Blueprint $table) {
            $table->string('xml_file_name')->nullable()->after('file_path');
            $table->string('xml_file_path')->nullable()->after('xml_file_name');
            $table->string('evidence_file_name')->nullable()->after('xml_file_path');
            $table->string('evidence_file_path')->nullable()->after('evidence_file_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_invoices', function (Blueprint $table) {
            $table->dropColumn('evidence_file_path');
            $table->dropColumn('evidence_file_name');
            $table->dropColumn('xml_file_path');
            $table->dropColumn('xml_file_name');
        });
    }
};
