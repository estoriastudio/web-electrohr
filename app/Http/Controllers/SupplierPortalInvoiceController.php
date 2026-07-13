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

        $purchaseOrder->load(['milestones' => function ($q) {
            $q->orderBy('id');
        }]);

        $eligibleMilestones = $purchaseOrder->milestones
            ->filter(function ($milestone) {
                return $milestone->due_date
                    && ($milestone->due_date->isPast() || $milestone->due_date->isToday());
            })
            ->values();

        $requestedMilestoneId = (int) $request->input('milestone_id');
        $selectedMilestone = $eligibleMilestones->firstWhere('id', $requestedMilestoneId)
            ?? $eligibleMilestones->first();

        return view('supplier_portal.create_invoice', [
            'supplier' => $supplier,
            'purchaseOrder' => $purchaseOrder,
            'eligibleMilestones' => $eligibleMilestones,
            'selectedMilestone' => $selectedMilestone,
            'prefilledCurrency' => $purchaseOrder->currency,
            'prefilledAmount' => $selectedMilestone?->effective_amount,
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $supplier = $request->user()->supplier;

        abort_unless((int) $purchaseOrder->supplier_id === (int) $supplier->id, 403);

        $validated = $request->validate([
            'milestone_id' => 'required|exists:purchase_order_milestones,id',
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
            'xml_file' => 'nullable|file|mimes:xml,text/xml|max:10240',
            'evidence_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $milestone = $purchaseOrder->milestones()
            ->whereKey($validated['milestone_id'])
            ->first();

        if (!$milestone) {
            return redirect()->route('supplier_portal.purchase_orders.index')
                ->with('error', 'El hito seleccionado no pertenece a esta orden de compra.');
        }

        if (!$milestone->due_date || $milestone->due_date->isFuture()) {
            return redirect()->back()->withInput()
                ->with('error', 'Solo se permite subir factura para hitos ya acontecidos.');
        }

        $resolvedAmount = (float) $milestone->effective_amount;
        if ($resolvedAmount <= 0) {
            return redirect()->back()->withInput()
                ->with('error', 'El hito seleccionado no tiene un importe válido para facturar.');
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
            $milestone,
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

            $invoice->milestones()->sync([$milestone->id]);

            PurchaseOrderEvidence::create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_milestone_id' => $milestone->id,
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
            ->with('success', 'Factura registrada correctamente para el hito seleccionado.');
    }
}
