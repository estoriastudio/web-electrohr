<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PurchaseOrderInvoiceController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    /**
     * Almacenar una nueva factura vinculada a una OC.
     */
    public function store(Request $request): RedirectResponse
    {
        // Cargar la OC antes de validar para usar su importe como límite
        $purchaseOrder = PurchaseOrder::findOrFail($request->input('purchase_order_id'));

        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'amount'            => ['required', 'numeric', 'min:0.01', 'max:' . $purchaseOrder->amount],
            'currency'          => 'required|in:MXN,USD,EUR',
            'milestone_ids'     => 'nullable|array',
            'milestone_ids.*'   => 'exists:purchase_order_milestones,id',
            'pdf_file'          => 'required|file|mimes:pdf|max:10240',
        ], [
            'amount.max' => 'El importe no puede exceder el total de la orden de compra (' . number_format($purchaseOrder->amount, 2) . ' ' . $purchaseOrder->currency . ').',
        ]);

        // Generar nombre de archivo: OC{id}-FACT{n+1}
        $invoiceNumber = $purchaseOrder->invoices()->count() + 1;
        $fileName      = 'OC' . $purchaseOrder->id . '-FACT' . $invoiceNumber . '.pdf';
        $storagePath   = 'invoices/' . $purchaseOrder->id . '/' . $fileName;

        $request->file('pdf_file')->storeAs(
            'invoices/' . $purchaseOrder->id,
            $fileName
        );

        $invoice = PurchaseOrderInvoice::create([
            'purchase_order_id' => $purchaseOrder->id,
            'file_name'         => $fileName,
            'file_path'         => $storagePath,
            'amount'            => $validated['amount'],
            'currency'          => $validated['currency'],
        ]);

        if (!empty($validated['milestone_ids'])) {
            $validIds = $purchaseOrder->milestones()
                ->whereIn('id', $validated['milestone_ids'])
                ->pluck('id');
            $invoice->milestones()->sync($validIds);
        }

        $this->notification->send([
            'type'         => 'PurchaseOrderInvoice',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $invoice->id,
            'data'         => 'subió la factura ' . $fileName . ' a la OC #' . $purchaseOrder->id,
        ]);

        return redirect()
            ->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Factura ' . $fileName . ' registrada correctamente.');
    }

    /**
     * Descargar / visualizar el PDF de una factura.
     */
    public function download(PurchaseOrderInvoice $invoice)
    {
        if (!Storage::exists($invoice->file_path)) {
            abort(404, 'Archivo no encontrado.');
        }

        return response()->file(
            Storage::path($invoice->file_path),
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $invoice->file_name . '"',
            ]
        );
    }

    /**
     * Eliminar una factura y su archivo.
     */
    public function destroy(PurchaseOrderInvoice $invoice): RedirectResponse
    {
        $purchaseOrderId = $invoice->purchase_order_id;
        $fileName        = $invoice->file_name;

        if (Storage::exists($invoice->file_path)) {
            Storage::delete($invoice->file_path);
        }

        $invoice->delete();

        $this->notification->send([
            'type'         => 'PurchaseOrderInvoice',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $invoice->id,
            'data'         => 'eliminó la factura ' . $fileName . ' de la OC #' . $purchaseOrderId,
        ]);

        return redirect()
            ->route('purchase_orders.show', $purchaseOrderId)
            ->with('success', 'Factura eliminada correctamente.');
    }
}
