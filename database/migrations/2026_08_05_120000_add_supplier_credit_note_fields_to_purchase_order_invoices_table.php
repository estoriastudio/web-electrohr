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
            $table->date('due_date')->nullable()->after('currency');
            $table->string('credit_note_file_name')->nullable()->after('evidence_file_path');
            $table->string('credit_note_file_path')->nullable()->after('credit_note_file_name');
            $table->string('credit_note_xml_file_name')->nullable()->after('credit_note_file_path');
            $table->string('credit_note_xml_file_path')->nullable()->after('credit_note_xml_file_name');
            $table->decimal('credit_note_amount', 15, 2)->nullable()->after('credit_note_xml_file_path');
            $table->decimal('net_scope', 15, 2)->nullable()->after('credit_note_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'due_date',
                'credit_note_file_name',
                'credit_note_file_path',
                'credit_note_xml_file_name',
                'credit_note_xml_file_path',
                'credit_note_amount',
                'net_scope',
            ]);
        });
    }
};