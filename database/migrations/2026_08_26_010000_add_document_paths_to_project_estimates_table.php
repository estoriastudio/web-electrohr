<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_estimates', function (Blueprint $table) {
            $table->string('invoice_pdf_path')->nullable()->after('invoice_number');
            $table->string('invoice_xml_path')->nullable()->after('invoice_pdf_path');
            $table->string('credit_note_pdf_path')->nullable()->after('invoice_xml_path');
            $table->string('credit_note_xml_path')->nullable()->after('credit_note_pdf_path');
            $table->string('spei_receipt_path')->nullable()->after('spei_reference');
        });
    }

    public function down(): void
    {
        Schema::table('project_estimates', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_pdf_path',
                'invoice_xml_path',
                'credit_note_pdf_path',
                'credit_note_xml_path',
                'spei_receipt_path',
            ]);
        });
    }
};