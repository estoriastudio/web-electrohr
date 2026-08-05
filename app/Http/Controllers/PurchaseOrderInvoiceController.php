<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PurchaseOrderInvoiceController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $section = trim((string) $request->input('section', PurchaseOrderInvoice::STATUS_EN_PROCESO));

        $validSections = array_merge(PurchaseOrderInvoice::STATUSES, ['todas']);
        if (!in_array($section, $validSections, true)) {
            $section = PurchaseOrderInvoice::STATUS_EN_PROCESO;
        }

        $pendingCount = PurchaseOrderInvoice::query()
            ->where('status', PurchaseOrderInvoice::STATUS_EN_PROCESO)
            ->count();
        $acceptedCount = PurchaseOrderInvoice::query()
            ->where('status', PurchaseOrderInvoice::STATUS_ACEPTADA)
            ->count();
        $rejectedCount = PurchaseOrderInvoice::query()
            ->where('status', PurchaseOrderInvoice::STATUS_RECHAZADA)
            ->count();

        $invoicesQuery = PurchaseOrderInvoice::query()
            ->with([
                'purchaseOrder:id,folio,supplier_id,elaborated_by',
                'purchaseOrder.supplier:id,rfc_name,commercial_name',
                'purchaseOrder.milestones:id,purchase_order_id,concept,payment_condition,value_type,value',
                'milestones:id,concept,payment_condition',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('folio', 'like', '%' . $search . '%')
                        ->orWhereHas('purchaseOrder', function ($po) use ($search) {
                            $po->where('folio', 'like', '%' . $search . '%')
                                ->orWhere('elaborated_by', 'like', '%' . $search . '%')
                                ->orWhereHas('supplier', function ($sup) use ($search) {
                                    $sup->where('rfc_name', 'like', '%' . $search . '%')
                                        ->orWhere('commercial_name', 'like', '%' . $search . '%');
                                });
                        });
                });
            });

        if ($section !== 'todas') {
            $invoicesQuery->where('status', $section);
        }

        if ($section === PurchaseOrderInvoice::STATUS_EN_PROCESO) {
            // Prioritize pending invoices by earliest due date; null due dates go last.
            $invoicesQuery
                ->orderByRaw('due_date IS NULL ASC')
                ->orderBy('due_date')
                ->orderByDesc('attached_at')
                ->orderByDesc('id');
        } else {
            $invoicesQuery
                ->orderByDesc('attached_at')
                ->orderByDesc('id');
        }

        $invoices = $invoicesQuery
            ->paginate(20)
            ->withQueryString();

        return view('invoices.index', compact(
            'invoices',
            'search',
            'section',
            'pendingCount',
            'acceptedCount',
            'rejectedCount'
        ));
    }

    public function show(PurchaseOrderInvoice $invoice): View
    {
        $invoice->load([
            'purchaseOrder:id,folio,supplier_id,elaborated_by,currency,amount',
            'purchaseOrder.supplier:id,rfc_name,commercial_name',
            'purchaseOrder.milestones:id,purchase_order_id,concept,payment_condition,value_type,value',
            'milestones:id,concept,payment_condition',
        ]);

        return view('invoices.show', compact('invoice'));
    }

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
        $xmlIssueDate = null;
        if ($request->hasFile('xml_file')) {
            $invoiceMetadata = $this->extractInvoiceMetadataFromXml($request->file('xml_file')->getRealPath());
            $xmlFiscalFolio = $invoiceMetadata['uuid'] ?? null;
            $xmlIssueDate = $invoiceMetadata['issue_date'] ?? null;

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
            'status'            => PurchaseOrderInvoice::STATUS_EN_PROCESO,
            'issue_date'        => $xmlIssueDate,
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

    public function downloadFile(PurchaseOrderInvoice $invoice, string $type)
    {
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

    public function updateStatus(Request $request, PurchaseOrderInvoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', PurchaseOrderInvoice::STATUSES),
            'purchase_order_milestone_id' => 'nullable|exists:purchase_order_milestones,id',
            'milestone_ids' => 'nullable|array',
            'milestone_ids.*' => 'exists:purchase_order_milestones,id',
        ]);

        if ($validated['status'] === PurchaseOrderInvoice::STATUS_ACEPTADA) {
            $selectedMilestoneIds = collect($validated['milestone_ids'] ?? [])
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            // Backward compatibility for forms still posting a single milestone field.
            if ($selectedMilestoneIds->isEmpty() && !empty($validated['purchase_order_milestone_id'])) {
                $selectedMilestoneIds = collect([(int) $validated['purchase_order_milestone_id']]);
            }

            if ($selectedMilestoneIds->isEmpty()) {
                return redirect()->back()->withInput()->withErrors([
                    'milestone_ids' => 'Debes seleccionar al menos un hito relacionado para aprobar la factura.',
                ]);
            }

            $validIds = $invoice->purchaseOrder
                ->milestones()
                ->whereIn('id', $selectedMilestoneIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($validIds->count() !== $selectedMilestoneIds->count()) {
                return redirect()->back()->withInput()->withErrors([
                    'milestone_ids' => 'Uno o más hitos seleccionados no pertenecen a la orden de compra de esta factura.',
                ]);
            }

            $invoice->milestones()->sync($validIds->all());
        }

        $invoice->update([
            'status' => $validated['status'],
        ]);

        $statusLabel = match ($validated['status']) {
            PurchaseOrderInvoice::STATUS_ACEPTADA => 'Aceptada',
            PurchaseOrderInvoice::STATUS_RECHAZADA => 'Rechazada',
            default => 'En Proceso',
        };

        $this->notification->send([
            'type'         => 'PurchaseOrderInvoice',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $invoice->id,
            'data'         => 'actualizó el estatus de la factura ' . ($invoice->folio ?: ('#' . $invoice->id)) . ' a ' . $statusLabel . '.',
        ]);

        return redirect()
            ->back()
            ->with('success', 'Estatus de factura actualizado a ' . $statusLabel . '.');
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
}
