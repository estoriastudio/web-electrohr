<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvidence;
use App\Models\PurchaseOrderInvoice;
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

        $invoicedAmount = (float) $purchaseOrder->invoices()->sum('amount');
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

        $invoicedAmount = (float) $purchaseOrder->invoices()->sum('amount');
        $pendingAmount = max(0, (float) $purchaseOrder->amount - $invoicedAmount);

        $validated = $request->validate([
            'folio' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $pendingAmount],
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
            'xml_file' => 'required|file|mimes:xml,text/xml|max:10240',
            'evidence_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ], [
            'amount.max' => 'El importe no puede exceder el saldo pendiente de la orden de compra (' . number_format($pendingAmount, 2) . ' ' . $purchaseOrder->currency . ').',
            'folio.required' => 'El folio fiscal es obligatorio.',
            'xml_file.required' => 'El XML de la factura es obligatorio para validar el folio fiscal.',
        ]);

        $xmlFiscalFolio = $this->extractFiscalFolioFromXml($request->file('xml_file')->getRealPath());
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

        $resolvedAmount = (float) $validated['amount'];
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

        $directory = 'invoices/' . $purchaseOrder->id;

        $pdfPath = $request->file('pdf_file')->storeAs($directory, $pdfName);
        $xmlPath = null;
        if ($request->hasFile('xml_file')) {
            $xmlName = $uuidBaseName . '.xml';
            $xmlPath = $request->file('xml_file')->storeAs($directory, $xmlName);
        }
        $evidencePath = $request->file('evidence_file')->storeAs($directory, $evidenceName);

        DB::transaction(function () use (
            $purchaseOrder,
            $pdfName,
            $pdfPath,
            $xmlName,
            $xmlPath,
            $evidenceName,
            $evidencePath,
            $resolvedAmount,
            $request,
            $xmlFiscalFolio
        ) {
            $invoice = PurchaseOrderInvoice::create([
                'purchase_order_id' => $purchaseOrder->id,
                'folio' => $xmlFiscalFolio,
                'file_name' => $pdfName,
                'file_path' => $pdfPath,
                'xml_file_name' => $xmlName,
                'xml_file_path' => $xmlPath,
                'evidence_file_name' => $evidenceName,
                'evidence_file_path' => $evidencePath,
                'amount' => $resolvedAmount,
                'currency' => $purchaseOrder->currency,
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

    private function extractFiscalFolioFromXml(string $xmlPath): ?string
    {
        $raw = @file_get_contents($xmlPath);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $dom = new \DOMDocument();
        $loaded = @$dom->loadXML($raw, LIBXML_NONET | LIBXML_NOBLANKS);
        if (!$loaded) {
            return null;
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[local-name()='TimbreFiscalDigital']");
        if (!$nodes || $nodes->length === 0) {
            return null;
        }

        $uuid = trim((string) (
            $nodes->item(0)?->attributes?->getNamedItem('UUID')?->nodeValue
            ?? $nodes->item(0)?->attributes?->getNamedItem('Uuid')?->nodeValue
            ?? $nodes->item(0)?->attributes?->getNamedItem('uuid')?->nodeValue
            ?? ''
        ));

        if ($uuid === '') {
            return null;
        }

        $uuid = strtoupper($uuid);
        if (!preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/', $uuid)) {
            return null;
        }

        return $uuid;
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
}
