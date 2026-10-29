<?php

namespace App\Http\Controllers;

use App\Exports\PurchaseOrderInvoiceExport;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use App\Services\MilestoneCreditTerms;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class PurchaseOrderInvoiceController extends Controller
{
    private const PURCHASE_ORDER_AMOUNT_TOLERANCE = 0.50;

    public function __construct(
        private NotificationService $notification,
        private MilestoneCreditTerms $creditTerms,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $section = trim((string) $request->input('section', PurchaseOrderInvoice::STATUS_EN_PROCESO));
        $paymentCondition = trim((string) $request->input('payment_condition', 'todas'));

        $validSections = array_merge(PurchaseOrderInvoice::STATUSES, ['todas']);
        if (!in_array($section, $validSections, true)) {
            $section = PurchaseOrderInvoice::STATUS_EN_PROCESO;
        }

        if (!in_array($paymentCondition, ['contado', 'credito', 'todas'], true)) {
            $paymentCondition = 'todas';
        }

        $buyerId = Auth::user()->invoiceBuyerFilter();
        $countsQuery = PurchaseOrderInvoice::query()
            ->when($buyerId !== null, fn ($query) => $query->ownedBy($buyerId));
        if ($paymentCondition !== 'todas') {
            $countsQuery->whereHas('purchaseOrder.milestones', function ($milestones) use ($paymentCondition) {
                $milestones->where('payment_condition', $paymentCondition);
            });
        }

        $pendingCount = (clone $countsQuery)
            ->where('status', PurchaseOrderInvoice::STATUS_EN_PROCESO)
            ->count();
        $acceptedCount = (clone $countsQuery)
            ->where('status', PurchaseOrderInvoice::STATUS_ACEPTADA)
            ->count();
        $rejectedCount = (clone $countsQuery)
            ->where('status', PurchaseOrderInvoice::STATUS_RECHAZADA)
            ->count();

        $invoicesQuery = PurchaseOrderInvoice::query()
            ->when($buyerId !== null, fn ($query) => $query->ownedBy($buyerId))
            ->with([
                'purchaseOrder:id,folio,supplier_id,buyer_id,elaborated_by',
                'purchaseOrder.buyer:id,name',
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
                                ->orWhereHas('buyer', fn ($buyer) => $buyer->where('name', 'like', '%' . $search . '%'))
                                ->orWhereHas('supplier', function ($sup) use ($search) {
                                    $sup->where('rfc_name', 'like', '%' . $search . '%')
                                        ->orWhere('commercial_name', 'like', '%' . $search . '%');
                                });
                        });
                });
            });

        if ($paymentCondition !== 'todas') {
            $invoicesQuery->whereHas('purchaseOrder.milestones', function ($milestones) use ($paymentCondition) {
                $milestones->where('payment_condition', $paymentCondition);
            });
        }

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

        $milestoneOptions = $this->milestoneOptionsFor($invoices->getCollection());

        return view('invoices.index', compact(
            'invoices',
            'milestoneOptions',
            'search',
            'section',
            'paymentCondition',
            'pendingCount',
            'acceptedCount',
            'rejectedCount'
        ));
    }

    public function show(PurchaseOrderInvoice $invoice): View
    {
        $invoice->load([
            'purchaseOrder:id,folio,supplier_id,buyer_id,elaborated_by,currency,amount',
            'purchaseOrder.buyer:id,name',
            'purchaseOrder.supplier:id,rfc_name,commercial_name',
            'purchaseOrder.milestones:id,purchase_order_id,concept,payment_condition,value_type,value',
            'milestones:id,concept,payment_condition',
        ]);

        $milestoneOptions = $this->milestoneOptionsFor(collect([$invoice]))[$invoice->id] ?? [];

        return view('invoices.show', compact('invoice', 'milestoneOptions'));
    }

    /**
     * Hitos de la OC de cada factura con su monto (effective_amount) y si ya tienen otra factura vigente.
     *
     * @return array<int, array<int, array{id:int, label:string, amount:float, blocked:bool}>>
     */
    private function milestoneOptionsFor(\Illuminate\Support\Collection $invoices): array
    {
        $orders = PurchaseOrder::query()
            ->whereIn('id', $invoices->pluck('purchase_order_id')->unique())
            ->with(['items', 'milestones.invoices:id,status'])
            ->get()
            ->keyBy('id');

        return $invoices->mapWithKeys(function (PurchaseOrderInvoice $invoice) use ($orders) {
            $order = $orders->get($invoice->purchase_order_id);
            $milestones = $order
                ? $order->milestones->sortBy('id')->values()->each(fn ($milestone) => $milestone->setRelation('purchaseOrder', $order))
                : collect();

            return [$invoice->id => $milestones->map(function ($milestone, $index) use ($invoice) {
                $concept = $milestone->concept ?: ($milestone->payment_condition === 'contado' ? 'Contado' : 'Crédito');
                $amount = $milestone->effective_amount;

                return [
                    'id' => $milestone->id,
                    'label' => 'Hito #' . ($index + 1) . ' - ' . $concept . ' . ' . $invoice->currency . ' ' . number_format($amount, 2),
                    'amount' => round($amount, 2),
                    'blocked' => $milestone->invoices
                        ->where('id', '!=', $invoice->id)
                        ->where('status', '!=', PurchaseOrderInvoice::STATUS_RECHAZADA)
                        ->isNotEmpty(),
                ];
            })->all()];
        })->all();
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'payment_conditions' => ['required', 'array', 'min:1'],
            'payment_conditions.*' => [Rule::in(['contado', 'credito'])],
        ]);

        $paymentConditions = collect($validated['payment_conditions'])->unique();
        $paymentCondition = $paymentConditions->count() === 2
            ? 'ambas'
            : $paymentConditions->first();

        return Excel::download(
            new PurchaseOrderInvoiceExport(
                $validated['start_date'],
                $validated['end_date'],
                $paymentCondition,
                Auth::user()->invoiceBuyerFilter()
            ),
            'facturas-' . $validated['start_date'] . '-a-' . $validated['end_date'] . '.xlsx'
        );
    }

    /**
     * Almacenar una nueva factura vinculada a una OC.
     */
    public function store(Request $request): RedirectResponse
    {
        return DB::transaction(function () use ($request) {
            PurchaseOrder::query()->lockForUpdate()->findOrFail($request->input('purchase_order_id'))->ensureOpen();

            return $this->storeLockedInvoice($request);
        });
    }

    private function storeLockedInvoice(Request $request): RedirectResponse
    {
        // Cargar la OC antes de validar para usar su importe como límite
        $purchaseOrder = PurchaseOrder::findOrFail($request->input('purchase_order_id'));
        $purchaseOrder->ensureOpen();

        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'folio'             => 'nullable|string|max:100',
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'currency'          => 'required|in:MXN,USD,EUR',
            'milestone_ids'     => 'nullable|array',
            'milestone_ids.*'   => 'exists:purchase_order_milestones,id',
            'pdf_file'          => 'nullable|file|mimes:pdf|max:10240',
            'xml_file'          => ['nullable', 'file', $this->xmlExtensionRule(), 'max:10240'],
        ]);

        $validated['amount'] = round((float) $validated['amount'], 2);
        $this->ensureMilestonesMatchInvoice($purchaseOrder, $validated['milestone_ids'] ?? [], $validated['amount']);
        $pendingAmount = $this->resolvePendingAmount($purchaseOrder, $this->resolveInvoicedAmount($purchaseOrder));

        if ($validated['amount'] > $pendingAmount + self::PURCHASE_ORDER_AMOUNT_TOLERANCE) {
            return redirect()->back()->withInput()->withErrors([
                'amount' => 'El importe no puede exceder el saldo pendiente de la orden de compra (' . number_format($pendingAmount, 2) . ' ' . $purchaseOrder->currency . ').',
            ]);
        }

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
        $invoice->purchaseOrder->ensureOpen();
        $this->releaseRegularizations($invoice);
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
        return DB::transaction(function () use ($request, $invoice) {
            PurchaseOrder::query()->lockForUpdate()->findOrFail($invoice->purchase_order_id)->ensureOpen();

            return $this->updateLockedInvoiceStatus($request, PurchaseOrderInvoice::query()->lockForUpdate()->findOrFail($invoice->id));
        });
    }

    private function updateLockedInvoiceStatus(Request $request, PurchaseOrderInvoice $invoice): RedirectResponse
    {
        $invoice->purchaseOrder->ensureOpen();

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

            $this->ensureMilestonesMatchInvoice($invoice->purchaseOrder, $selectedMilestoneIds->all(), (float) $invoice->amount, $invoice);
            $validIds = $selectedMilestoneIds;

            DB::transaction(function () use ($invoice, $validIds) {
                PurchaseOrder::query()->lockForUpdate()->findOrFail($invoice->purchase_order_id);
                $invoice->milestones()->sync($validIds->all());
                $invoice->update([
                    'status' => PurchaseOrderInvoice::STATUS_ACEPTADA,
                    'accepted_at' => $invoice->accepted_at ?? now(),
                ]);

                $this->regularizePaidPayments($invoice, $validIds->all());
            });

            $creditWarnings = $invoice->purchaseOrder->milestones()
                ->whereIn('id', $validIds)
                ->get()
                ->map(fn ($milestone) => $this->creditTerms->apply($milestone->setRelation('purchaseOrder', $invoice->purchaseOrder)))
                ->filter()
                ->implode(' ');
        }

        if ($validated['status'] !== PurchaseOrderInvoice::STATUS_ACEPTADA) {
            $invoice->update(['status' => $validated['status'], 'accepted_at' => null]);
            $this->releaseRegularizations($invoice);
            if ($validated['status'] === PurchaseOrderInvoice::STATUS_RECHAZADA) {
                $invoice->milestones()->detach();
            }
        }

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
            ->with('success', 'Estatus de factura actualizado a ' . $statusLabel . '.')
            ->with('warning', $creditWarnings ?? null);
    }

    /**
     * Una factura puede cubrir varios hitos, pero cada hito admite una sola factura vigente
     * y la suma de los hitos debe igualar el importe de la factura.
     */
    private function ensureMilestonesMatchInvoice(PurchaseOrder $order, array $ids, float $invoiceAmount, ?PurchaseOrderInvoice $invoice = null): void
    {
        if ($ids === []) {
            return;
        }

        $fail = fn (string $message) => throw \Illuminate\Validation\ValidationException::withMessages([
            'milestone_ids' => $message,
        ]);

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $order->loadMissing('items');
        $order->setRelation('milestones', $order->milestones()->get()->each(fn ($milestone) => $milestone->setRelation('purchaseOrder', $order)));
        $milestones = $order->milestones->whereIn('id', $ids);

        if ($milestones->count() !== count($ids)) {
            $fail('Uno o más hitos seleccionados no pertenecen a la orden de compra de esta factura.');
        }

        $occupied = PurchaseOrderMilestone::query()
            ->whereIn('id', $ids)
            ->whereHas('invoices', function ($query) use ($invoice) {
                $query->where('purchase_order_invoices.status', '!=', PurchaseOrderInvoice::STATUS_RECHAZADA)
                    ->when($invoice, fn ($q) => $q->where('purchase_order_invoices.id', '!=', $invoice->id));
            })
            ->exists();
        if ($occupied) {
            $fail('Uno de los hitos ya tiene una factura vinculada. No se puede asignar otra.');
        }

        $milestonesTotal = round((float) $milestones->sum(fn ($milestone) => $milestone->effective_amount), 2);
        $difference = round($invoiceAmount - $milestonesTotal, 2);
        if (abs($difference) > self::PURCHASE_ORDER_AMOUNT_TOLERANCE) {
            $currency = $order->currency;
            $fail($difference > 0
                ? 'El importe de la factura (' . number_format($invoiceAmount, 2) . ' ' . $currency . ') excede en ' . number_format($difference, 2)
                    . ' ' . $currency . ' la suma de los hitos seleccionados (' . number_format($milestonesTotal, 2) . ' ' . $currency . ').'
                : 'La factura (' . number_format($invoiceAmount, 2) . ' ' . $currency . ') no cubre los hitos seleccionados ('
                    . number_format($milestonesTotal, 2) . ' ' . $currency . '): faltan ' . number_format(abs($difference), 2) . ' ' . $currency . '.');
        }
    }

    /**
     * Regularización documental: al aceptarse la primera factura de un hito, los pagos ya pagados
     * quedan vinculados a ella (sin crear pagos ni importes nuevos).
     */
    private function regularizePaidPayments(PurchaseOrderInvoice $invoice, array $milestoneIds): void
    {
        foreach ($milestoneIds as $milestoneId) {
            $hadAcceptedInvoice = DB::table('invoice_milestone')
                ->join('purchase_order_invoices', 'purchase_order_invoices.id', '=', 'invoice_milestone.purchase_order_invoice_id')
                ->where('invoice_milestone.purchase_order_milestone_id', $milestoneId)
                ->where('purchase_order_invoices.status', PurchaseOrderInvoice::STATUS_ACEPTADA)
                ->where('purchase_order_invoices.id', '!=', $invoice->id)
                ->exists();

            if ($hadAcceptedInvoice) {
                continue;
            }

            Payment::where('milestone_id', $milestoneId)
                ->where('status', 'pagado')
                ->whereNull('regularized_at')
                ->update(['regularization_invoice_id' => $invoice->id, 'regularized_at' => now()]);
        }
    }

    private function releaseRegularizations(PurchaseOrderInvoice $invoice): void
    {
        Payment::where('regularization_invoice_id', $invoice->id)
            ->update(['regularization_invoice_id' => null, 'regularized_at' => null]);
    }

    private function resolveInvoicedAmount(PurchaseOrder $purchaseOrder): float
    {
        return round((float) $purchaseOrder->invoices()
            ->whereIn('status', [
                PurchaseOrderInvoice::STATUS_EN_PROCESO,
                PurchaseOrderInvoice::STATUS_ACEPTADA,
            ])
            ->selectRaw('COALESCE(SUM(COALESCE(net_scope, amount)), 0) as total')
            ->value('total'), 2);
    }

    private function resolvePendingAmount(PurchaseOrder $purchaseOrder, float $invoicedAmount): float
    {
        return max(0, round((float) $purchaseOrder->total_with_iva - $invoicedAmount, 2));
    }

    // `mimes` falla con CFDI sin declaración <?xml (se detectan como text/plain), por eso se valida la extensión.
    private function xmlExtensionRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ($value instanceof \Illuminate\Http\UploadedFile
                && strtolower($value->getClientOriginalExtension()) !== 'xml') {
                $fail('El archivo debe ser de tipo XML.');
            }
        };
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
