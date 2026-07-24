<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvidence;
use App\Models\PurchaseOrderInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $pendingAmount],
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
            'xml_file' => 'nullable|file|mimes:xml,text/xml|max:10240',
            'evidence_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ], [
            'amount.max' => 'El importe no puede exceder el saldo pendiente de la orden de compra (' . number_format($pendingAmount, 2) . ' ' . $purchaseOrder->currency . ').',
        ]);

        $resolvedAmount = (float) $validated['amount'];
        if ($pendingAmount <= 0) {
            return redirect()->back()->withInput()
                ->with('error', 'La orden de compra ya está facturada por completo.');
        }

        $invoiceNumber = $purchaseOrder->invoices()->count() + 1;
        $baseName = 'OC' . $purchaseOrder->id . '-FACT' . $invoiceNumber;

        $pdfName = $baseName . '.pdf';
        $xmlName = null;
        $evidenceExt = strtolower($request->file('evidence_file')->getClientOriginalExtension());
        $evidenceName = $baseName . '-EVIDENCIA.' . $evidenceExt;

        $directory = 'invoices/' . $purchaseOrder->id;

        $pdfPath = $request->file('pdf_file')->storeAs($directory, $pdfName);
        $xmlPath = null;
        if ($request->hasFile('xml_file')) {
            $xmlName = $baseName . '.xml';
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
            $request
        ) {
            $invoice = PurchaseOrderInvoice::create([
                'purchase_order_id' => $purchaseOrder->id,
                'folio' => null,
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
}
