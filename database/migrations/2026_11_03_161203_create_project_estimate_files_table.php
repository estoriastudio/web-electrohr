<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_COLUMNS = [
        'credit_note_pdf' => 'credit_note_pdf_path',
        'credit_note_xml' => 'credit_note_xml_path',
        'spei_receipt' => 'spei_receipt_path',
        'liquidation_slip' => 'liquidation_slip_path',
    ];

    private const LEGACY_INVOICE_COLUMNS = ['invoice_pdf_path', 'invoice_xml_path'];

    public function up(): void
    {
        Schema::create('project_estimate_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_estimate_id')->constrained('project_estimates')->cascadeOnDelete();
            $table->string('file_type');
            $table->string('file_path');
            $table->string('original_name');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_estimate_id', 'file_type']);
        });

        Schema::create('project_estimate_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_estimate_id')->constrained('project_estimates')->cascadeOnDelete();
            $table->string('folio', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_original_name')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('xml_original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_estimate_id', 'folio']);
        });

        $estimates = DB::table('project_estimates')->get([
            'id', 'estimate_number', 'invoice_number', 'invoice_date',
            ...self::LEGACY_INVOICE_COLUMNS, ...array_values(self::LEGACY_COLUMNS),
        ]);

        foreach ($estimates as $estimate) {
            // La factura PDF y XML existentes de una estimación se migran como una sola factura.
            if ($estimate->invoice_pdf_path || $estimate->invoice_xml_path) {
                DB::table('project_estimate_invoices')->insert([
                    'project_estimate_id' => $estimate->id,
                    'folio' => $estimate->invoice_number,
                    'invoice_date' => $estimate->invoice_date,
                    'payment_date' => null,
                    'pdf_path' => $estimate->invoice_pdf_path,
                    'pdf_original_name' => $estimate->invoice_pdf_path ? $estimate->estimate_number . '-invoice_pdf.pdf' : null,
                    'xml_path' => $estimate->invoice_xml_path,
                    'xml_original_name' => $estimate->invoice_xml_path ? $estimate->estimate_number . '-invoice_xml.xml' : null,
                    'uploaded_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (self::LEGACY_COLUMNS as $type => $column) {
                $path = $estimate->{$column};

                if (! $path) {
                    continue;
                }

                DB::table('project_estimate_files')->insert([
                    'project_estimate_id' => $estimate->id,
                    'file_type' => $type,
                    'file_path' => $path,
                    'original_name' => $estimate->estimate_number . '-' . $type . '.' . pathinfo($path, PATHINFO_EXTENSION),
                    'uploaded_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('project_estimates', function (Blueprint $table) {
            $table->dropColumn([...self::LEGACY_INVOICE_COLUMNS, ...array_values(self::LEGACY_COLUMNS)]);
        });
    }

    public function down(): void
    {
        Schema::table('project_estimates', function (Blueprint $table) {
            $table->string('invoice_pdf_path')->nullable()->after('invoice_number');
            $table->string('invoice_xml_path')->nullable()->after('invoice_pdf_path');
            $table->string('credit_note_pdf_path')->nullable()->after('invoice_xml_path');
            $table->string('credit_note_xml_path')->nullable()->after('credit_note_pdf_path');
            $table->string('spei_receipt_path')->nullable()->after('spei_reference');
            $table->string('liquidation_slip_path')->nullable()->after('spei_receipt_path');
        });

        // El repositorio admite varios archivos por tipo; al volver se conserva el más antiguo de cada uno.
        foreach (DB::table('project_estimate_invoices')->orderBy('id')->get()->unique('project_estimate_id') as $invoice) {
            DB::table('project_estimates')->where('id', $invoice->project_estimate_id)->update([
                'invoice_pdf_path' => $invoice->pdf_path,
                'invoice_xml_path' => $invoice->xml_path,
            ]);
        }

        foreach (self::LEGACY_COLUMNS as $type => $column) {
            $files = DB::table('project_estimate_files')
                ->where('file_type', $type)
                ->orderBy('id')
                ->get(['project_estimate_id', 'file_path'])
                ->unique('project_estimate_id');

            foreach ($files as $file) {
                DB::table('project_estimates')
                    ->where('id', $file->project_estimate_id)
                    ->update([$column => $file->file_path]);
            }
        }

        Schema::dropIfExists('project_estimate_invoices');
        Schema::dropIfExists('project_estimate_files');
    }
};
