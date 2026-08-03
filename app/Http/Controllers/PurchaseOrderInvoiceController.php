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
            'folio'             => 'nullable|string|max:100',
            'amount'            => ['required', 'numeric', 'min:0.01', 'max:' . $purchaseOrder->amount],
            'currency'          => 'required|in:MXN,USD,EUR',
            'milestone_ids'     => 'nullable|array',
            'milestone_ids.*'   => 'exists:purchase_order_milestones,id',
            'pdf_file'          => 'nullable|file|mimes:pdf|max:10240',
            'xml_file'          => 'nullable|file|mimes:xml,text/xml|max:10240',
        ], [
            'amount.max' => 'El importe no puede exceder el total de la orden de compra (' . number_format($purchaseOrder->amount, 2) . ' ' . $purchaseOrder->currency . ').',
        ]);

        $xmlFiscalFolio = null;
        if ($request->hasFile('xml_file')) {
            $xmlFiscalFolio = $this->extractFiscalFolioFromXml($request->file('xml_file')->getRealPath());

            if (!$xmlFiscalFolio) {
                return redirect()->back()->withInput()->withErrors([
                    'xml_file' => 'No fue posible leer el folio fiscal (UUID) del XML. Verifica que sea un CFDI timbrado válido.',
                ]);
            }

            $inputFolio = strtoupper(trim((string) ($validated['folio'] ?? '')));
            if ($inputFolio !== '' && $inputFolio !== $xmlFiscalFolio) {
                return redirect()->back()->withInput()->withErrors([
                    'folio' => 'El folio capturado no coincide con el folio fiscal (UUID) del XML.',
                ]);
            }

            $validated['folio'] = $xmlFiscalFolio;
        }

        $fileName    = null;
        $storagePath = null;
        $xmlFileName = null;
        $xmlStoragePath = null;
        $invoiceNumber = $purchaseOrder->invoices()->count() + 1;
        $folioForFile = strtoupper(trim((string) ($validated['folio'] ?? '')));
        $baseName = $folioForFile !== ''
            ? $this->sanitizeFileToken($folioForFile)
            : 'OC' . $purchaseOrder->id . '-FACT' . $invoiceNumber;

        if ($request->hasFile('pdf_file')) {
            // Generar nombre de archivo: OC{id}-FACT{n+1}
            $fileName      = $baseName . '.pdf';
            $storagePath   = 'invoices/' . $purchaseOrder->id . '/' . $fileName;

            $request->file('pdf_file')->storeAs(
                'invoices/' . $purchaseOrder->id,
                $fileName
            );
        }

        if ($request->hasFile('xml_file')) {
            $xmlFileName = $baseName . '.xml';
            $xmlStoragePath = 'invoices/' . $purchaseOrder->id . '/' . $xmlFileName;

            $request->file('xml_file')->storeAs(
                'invoices/' . $purchaseOrder->id,
                $xmlFileName
            );
        }

        $invoice = PurchaseOrderInvoice::create([
            'purchase_order_id' => $purchaseOrder->id,
            'folio'             => $validated['folio'] ?? null,
            'file_name'         => $fileName,
            'file_path'         => $storagePath,
            'xml_file_name'     => $xmlFileName,
            'xml_file_path'     => $xmlStoragePath,
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
            'data'         => 'registró la factura ' . ($validated['folio'] ?? ($fileName ?? 'sin PDF')) . ' en la OC #' . $purchaseOrder->id,
        ]);

        return redirect()
            ->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Factura registrada correctamente.');
    }

    /**
     * Descargar / visualizar el PDF de una factura.
     */
    public function download(PurchaseOrderInvoice $invoice)
    {
        if (!$invoice->file_path || !Storage::exists($invoice->file_path)) {
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

        if ($invoice->file_path && Storage::exists($invoice->file_path)) {
            Storage::delete($invoice->file_path);
        }
        if ($invoice->xml_file_path && Storage::exists($invoice->xml_file_path)) {
            Storage::delete($invoice->xml_file_path);
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
}
