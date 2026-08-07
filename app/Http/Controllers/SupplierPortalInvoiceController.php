<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvidence;
use App\Models\PurchaseOrderInvoice;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SupplierPortalInvoiceController extends Controller
{
    public function create(Request $request, PurchaseOrder $purchaseOrder)
    {
        $supplier = $request->user()->supplier;

        abort_unless((int) $purchaseOrder->supplier_id === (int) $supplier->id, 403);
        abort_unless($purchaseOrder->status === 'autorizada', 403);

        $invoicedAmount = $this->resolveInvoicedAmount($purchaseOrder);
        $pendingAmount = max(0, (float) $purchaseOrder->amount - $invoicedAmount);

        return view('supplier_portal.create_invoice', [
            'supplier' => $supplier,
            'purchaseOrder' => $purchaseOrder,
            'invoicedAmount' => $invoicedAmount,
            'pendingAmount' => $pendingAmount,
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $supplier = $request->user()->supplier;

        abort_unless((int) $purchaseOrder->supplier_id === (int) $supplier->id, 403);
        abort_unless($purchaseOrder->status === 'autorizada', 403);

        $invoicedAmount = $this->resolveInvoicedAmount($purchaseOrder);
        $pendingAmount = max(0, (float) $purchaseOrder->amount - $invoicedAmount);

        $validated = $request->validate([
            'due_date' => ['required', 'date'],
            'folio' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'has_credit_note' => ['nullable', 'boolean'],
            'credit_note_amount' => ['nullable', 'numeric', 'min:0'],
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
            'xml_file' => 'required|file|mimes:xml,text/xml|max:10240',
            'evidence_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'credit_note_pdf_file' => 'nullable|file|mimes:pdf|max:10240',
            'credit_note_xml_file' => 'nullable|file|mimes:xml,text/xml|max:10240',
        ], [
            'folio.required' => 'El folio fiscal es obligatorio.',
            'xml_file.required' => 'El XML de la factura es obligatorio para validar el folio fiscal.',
            'due_date.required' => 'La fecha de vencimiento es obligatoria.',
        ]);

        $hasCreditNote = $request->boolean('has_credit_note');
        $creditNoteAmount = null;

        if ($hasCreditNote) {
            $creditNoteAmountRaw = $validated['credit_note_amount'] ?? null;
            if ($creditNoteAmountRaw === null || $creditNoteAmountRaw === '') {
                return redirect()->back()->withInput()->withErrors([
                    'credit_note_amount' => 'El importe de la nota de credito es obligatorio cuando la nota de credito esta activada.',
                ]);
            }

            if (!$request->hasFile('credit_note_pdf_file')) {
                return redirect()->back()->withInput()->withErrors([
                    'credit_note_pdf_file' => 'El PDF de la nota de credito es obligatorio cuando la nota de credito esta activada.',
                ]);
            }

            if (!$request->hasFile('credit_note_xml_file')) {
                return redirect()->back()->withInput()->withErrors([
                    'credit_note_xml_file' => 'El XML de la nota de credito es obligatorio cuando la nota de credito esta activada.',
                ]);
            }

            $creditNoteAmount = round((float) $creditNoteAmountRaw, 2);
        }

        $invoiceMetadata = $this->extractInvoiceMetadataFromXml($request->file('xml_file')->getRealPath());
        $xmlFiscalFolio = $invoiceMetadata['uuid'] ?? null;
        $xmlIssueDate = $invoiceMetadata['issue_date'] ?? null;

        if (!$xmlFiscalFolio) {
            return redirect()->back()->withInput()->withErrors([
                'xml_file' => 'No fue posible leer el folio fiscal (UUID) del XML. Verifica que sea un CFDI timbrado válido.',
            ]);
        }

        $inputFolio = strtoupper(trim((string) $validated['folio']));
        if ($inputFolio !== $xmlFiscalFolio) {
            return redirect()->back()->withInput()->withErrors([
                'folio' => 'El folio capturado no coincide con el folio fiscal (UUID) del XML.',
            ]);
        }

        $resolvedAmount = round((float) $validated['amount'], 2);
        $resolvedCreditNoteAmount = $hasCreditNote ? $creditNoteAmount : null;
        $netScope = round($resolvedAmount - (float) ($resolvedCreditNoteAmount ?? 0), 2);

        if ($netScope <= 0) {
            return redirect()->back()->withInput()->withErrors([
                'credit_note_amount' => 'El alcance liquido debe ser mayor a 0.00. Verifica que el importe de la nota de credito no sea igual o mayor al importe de la factura.',
            ]);
        }

        if ($netScope > $pendingAmount) {
            return redirect()->back()->withInput()->withErrors([
                'amount' => 'El alcance liquido no puede exceder el saldo pendiente de la orden de compra (' . number_format($pendingAmount, 2) . ' ' . $purchaseOrder->currency . ').',
            ]);
        }

        if ($pendingAmount <= 0) {
            return redirect()->back()->withInput()
                ->with('error', 'La orden de compra ya está facturada por completo.');
        }

        $uuidBaseName = $this->sanitizeFileToken($xmlFiscalFolio);
        $invoiceNumber = $purchaseOrder->invoices()->count() + 1;

        $pdfName = $uuidBaseName . '.pdf';
        $xmlName = null;
        $evidenceExt = strtolower($request->file('evidence_file')->getClientOriginalExtension());
        $evidenceName = 'OC' . $purchaseOrder->id . '-FACT' . $invoiceNumber . '-EVIDENCIA.' . $evidenceExt;
        $creditNotePdfName = null;
        $creditNotePdfPath = null;
        $creditNoteXmlName = null;
        $creditNoteXmlPath = null;

        $directory = 'invoices/' . $purchaseOrder->id;

        $pdfPath = $request->file('pdf_file')->storeAs($directory, $pdfName);
        $xmlPath = null;
        if ($request->hasFile('xml_file')) {
            $xmlName = $uuidBaseName . '.xml';
            $xmlPath = $request->file('xml_file')->storeAs($directory, $xmlName);
        }
        $evidencePath = $request->file('evidence_file')->storeAs($directory, $evidenceName);

        if ($hasCreditNote) {
            $creditNotePdfName = $uuidBaseName . '-NC.pdf';
            $creditNotePdfPath = $request->file('credit_note_pdf_file')->storeAs($directory, $creditNotePdfName);

            $creditNoteXmlName = $uuidBaseName . '-NC.xml';
            $creditNoteXmlPath = $request->file('credit_note_xml_file')->storeAs($directory, $creditNoteXmlName);
        }

        DB::transaction(function () use (
            $purchaseOrder,
            $pdfName,
            $pdfPath,
            $xmlName,
            $xmlPath,
            $evidenceName,
            $evidencePath,
            $resolvedAmount,
            $resolvedCreditNoteAmount,
            $netScope,
            $request,
            $xmlFiscalFolio,
            $xmlIssueDate,
            $validated,
            $creditNotePdfName,
            $creditNotePdfPath,
            $creditNoteXmlName,
            $creditNoteXmlPath
        ) {
            $invoice = PurchaseOrderInvoice::create([
                'purchase_order_id' => $purchaseOrder->id,
                'folio' => $xmlFiscalFolio,
                'status' => PurchaseOrderInvoice::STATUS_EN_PROCESO,
                'issue_date' => $xmlIssueDate,
                'file_name' => $pdfName,
                'file_path' => $pdfPath,
                'xml_file_name' => $xmlName,
                'xml_file_path' => $xmlPath,
                'evidence_file_name' => $evidenceName,
                'evidence_file_path' => $evidencePath,
                'amount' => $resolvedAmount,
                'currency' => $purchaseOrder->currency,
                'due_date' => $validated['due_date'],
                'credit_note_file_name' => $creditNotePdfName,
                'credit_note_file_path' => $creditNotePdfPath,
                'credit_note_xml_file_name' => $creditNoteXmlName,
                'credit_note_xml_file_path' => $creditNoteXmlPath,
                'credit_note_amount' => $resolvedCreditNoteAmount,
                'net_scope' => $netScope,
            ]);

            PurchaseOrderEvidence::create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_milestone_id' => null,
                'purchase_order_invoice_id' => $invoice->id,
                'uploaded_by' => $request->user()->id,
                'file_name' => $evidenceName,
                'file_path' => $evidencePath,
                'mime_type' => $request->file('evidence_file')->getClientMimeType(),
                'source' => 'supplier_portal',
                'description' => 'Evidencia subida por proveedor junto con factura.',
            ]);
        });

        return redirect()->route('supplier_portal.purchase_orders.index')
            ->with('success', 'Factura registrada correctamente para la orden de compra.');
    }

    private function extractInvoiceMetadataFromXml(string $xmlPath): array
    {
        $raw = @file_get_contents($xmlPath);
        if ($raw === false || trim($raw) === '') {
            return ['uuid' => null, 'issue_date' => null];
        }

        $dom = new \DOMDocument();
        $loaded = @$dom->loadXML($raw, LIBXML_NONET | LIBXML_NOBLANKS);
        if (!$loaded) {
            return ['uuid' => null, 'issue_date' => null];
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[local-name()='TimbreFiscalDigital']");
        $uuid = null;
        if ($nodes && $nodes->length > 0) {
            $candidate = trim((string) (
                $nodes->item(0)?->attributes?->getNamedItem('UUID')?->nodeValue
                ?? $nodes->item(0)?->attributes?->getNamedItem('Uuid')?->nodeValue
                ?? $nodes->item(0)?->attributes?->getNamedItem('uuid')?->nodeValue
                ?? ''
            ));

            $candidate = strtoupper($candidate);
            if ($candidate !== '' && preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/', $candidate)) {
                $uuid = $candidate;
            }
        }

        $issueDate = null;
        $comprobanteNodes = $xpath->query("//*[local-name()='Comprobante']");
        if ($comprobanteNodes && $comprobanteNodes->length > 0) {
            $fechaRaw = trim((string) (
                $comprobanteNodes->item(0)?->attributes?->getNamedItem('Fecha')?->nodeValue
                ?? $comprobanteNodes->item(0)?->attributes?->getNamedItem('fecha')?->nodeValue
                ?? ''
            ));

            if ($fechaRaw === '' && $nodes && $nodes->length > 0) {
                $fechaRaw = trim((string) (
                    $nodes->item(0)?->attributes?->getNamedItem('FechaTimbrado')?->nodeValue
                    ?? $nodes->item(0)?->attributes?->getNamedItem('fechaTimbrado')?->nodeValue
                    ?? ''
                ));
            }

            if ($fechaRaw !== '') {
                try {
                    $issueDate = Carbon::parse($fechaRaw)->toDateString();
                } catch (\Throwable $e) {
                    $issueDate = null;
                }
            }
        }

        return [
            'uuid' => $uuid,
            'issue_date' => $issueDate,
        ];
    }

    private function sanitizeFileToken(string $token): string
    {
        $normalized = strtoupper(trim($token));
        $sanitized = preg_replace('/[^A-Z0-9\-]+/', '_', $normalized) ?? '';
        return trim($sanitized, '_-') !== '' ? trim($sanitized, '_-') : 'SIN-FOLIO';
    }

    public function downloadFile(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderInvoice $invoice, string $type)
    {
        $supplier = $request->user()->supplier;

        abort_unless((int) $purchaseOrder->supplier_id === (int) $supplier->id, 403);
        abort_unless((int) $invoice->purchase_order_id === (int) $purchaseOrder->id, 404);

        $fieldMap = [
            'pdf' => ['path' => 'file_path', 'name' => 'file_name', 'mime' => 'application/pdf'],
            'xml' => ['path' => 'xml_file_path', 'name' => 'xml_file_name', 'mime' => 'application/xml'],
            'evidence' => ['path' => 'evidence_file_path', 'name' => 'evidence_file_name', 'mime' => null],
            'credit_note_pdf' => ['path' => 'credit_note_file_path', 'name' => 'credit_note_file_name', 'mime' => 'application/pdf'],
            'credit_note_xml' => ['path' => 'credit_note_xml_file_path', 'name' => 'credit_note_xml_file_name', 'mime' => 'application/xml'],
        ];

        abort_unless(isset($fieldMap[$type]), 404);

        $pathField = $fieldMap[$type]['path'];
        $nameField = $fieldMap[$type]['name'];
        $forcedMime = $fieldMap[$type]['mime'];

        $filePath = (string) ($invoice->{$pathField} ?? '');
        $fileName = (string) ($invoice->{$nameField} ?? 'archivo');

        if ($filePath === '' || !Storage::exists($filePath)) {
            abort(404, 'Archivo no encontrado.');
        }

        return response()->file(
            Storage::path($filePath),
            [
                'Content-Type' => $forcedMime ?: (Storage::mimeType($filePath) ?: 'application/octet-stream'),
                'Content-Disposition' => 'inline; filename="' . str_replace('"', '', $fileName) . '"',
            ]
        );
    }

    private function resolveInvoicedAmount(PurchaseOrder $purchaseOrder): float
    {
        return (float) $purchaseOrder->invoices()
            ->where('status', PurchaseOrderInvoice::STATUS_ACEPTADA)
            ->selectRaw('COALESCE(SUM(COALESCE(net_scope, amount)), 0) as total')
            ->value('total');
    }
}
