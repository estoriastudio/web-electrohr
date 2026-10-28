<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/* Modelos */
use App\Models\MobileAsset;
use App\Models\Concept;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAnnex;
use App\Models\PurchaseOrderEvidence;
use App\Models\PurchaseOrderItemDelivery;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderMilestone;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\ProjectWork;
use App\Models\Supplier;
use App\Models\Project;
use App\Models\Payment;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/* PDF */
use Barryvdh\DomPDF\Facade\Pdf;

/* Notificaciones */
use App\Services\NotificationService;
use App\Services\MilestoneCreditTerms;
use App\Services\PurchaseOrderClosureEvaluator;
use App\Services\PurchaseOrderMilestoneChecklist;
use App\Services\PurchaseOrderPaymentSequenceValidator;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private NotificationService $notification,
        private PurchaseOrderPaymentSequenceValidator $paymentSequenceValidator,
        private PurchaseOrderMilestoneChecklist $milestoneChecklist,
        private PurchaseOrderClosureEvaluator $closureEvaluator,
        private MilestoneCreditTerms $creditTerms,
    ) {}

    private function blockIfAuthorizedAndNotAdmin(PurchaseOrder $purchaseOrder): ?RedirectResponse
    {
        $purchaseOrder->ensureOpen();

        if ($purchaseOrder->status === 'autorizada' && !Auth::user()->hasRole('admin')) {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'Solo admin puede editar una OC autorizada.');
        }

        return null;
    }

    public function index(Request $request): View
    {
        $scope = $this->listingScope($request);
        $buyerStatus = $scope === 'all' && $request->input('buyer_status') === 'unassigned' ? 'unassigned' : '';
        $search  = trim($request->input('search', ''));
        $tipo    = $request->input('tipo', '');
        $sortDue = match ($request->input('sort_due', '')) {
            'asc' => 'payment_asc',
            'desc' => 'payment_desc',
            'payment_asc', 'payment_desc', 'delivery_asc', 'delivery_desc' => $request->input('sort_due'),
            default => '',
        };
        $tray    = $request->input('tray', 'all');
        $searchMode = $request->input('search_mode', 'default');
        $trays   = ['all', 'issued', 'authorized', 'delivered', 'closed'];

        if (!in_array($tray, $trays, true) || !Auth::user()->hasRole('admin')) {
            $tray = 'all';
        }

        if (!in_array($searchMode, ['default', 'traceability'], true)) {
            $searchMode = 'default';
        }

        $orderQuery = PurchaseOrder::with(['supplier', 'items', 'buyer'])
            ->withCount(['milestones', 'children'])
            ->whereNull('archived_at')
            ->when($scope === 'mine', fn ($query) => $query->ownedBy(Auth::id()))
            ->when($buyerStatus === 'unassigned', fn ($query) => $query->whereNull('buyer_id'));

        $orders = (clone $orderQuery)
            ->when($sortDue, function ($query) {
                $query->whereNull('purchase_orders.closed_at')->where(function ($query) {
                $query->whereNull('delivery_status')
                    ->orWhere('delivery_status', '!=', 'entregado')
                    ->orWhereRaw(
                        "COALESCE((SELECT SUM(payments.amount)
                            FROM payments
                            INNER JOIN purchase_order_milestones
                                ON purchase_order_milestones.id = payments.milestone_id
                            WHERE purchase_order_milestones.purchase_order_id = purchase_orders.id
                                AND payments.status = ?), 0) < purchase_orders.amount",
                        ['pagado']
                    );
                });
            })
            ->when($search && $searchMode === 'default', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('supplier', function ($s) use ($search) {
                        $s->where('rfc_name', 'like', '%' . $search . '%')
                          ->orWhere('commercial_name', 'like', '%' . $search . '%');
                                        })->orWhereHas('projectRelation', function ($project) use ($search) {
                                                $project->where('name', 'like', '%' . $search . '%');
                                        })->orWhere('project', 'like', '%' . $search . '%')
                                            ->orWhere('folio', 'like', '%' . $search . '%')
                                            ->orWhere('elaborated_by', 'like', '%' . $search . '%')
                                            ->orWhereHas('buyer', fn ($buyer) => $buyer->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->when($search && $searchMode === 'traceability', function ($q) use ($search) {
                $q->whereHas('purchaseRequest', function ($purchaseRequest) use ($search) {
                    $purchaseRequest->where('folio', 'like', '%' . $search . '%')
                        ->orWhereHas('materialRequest', function ($materialRequest) use ($search) {
                            $materialRequest->where('folio', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('materialRequests', function ($materialRequests) use ($search) {
                            $materialRequests->where('folio', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($tipo, fn ($q) => $q->where('type', $tipo))
            ->when($tray === 'issued', fn ($q) => $q->where('status', 'emitida')->where('delivery_status', '!=', 'entregado'))
            ->when($tray === 'authorized', fn ($q) => $q->where('status', 'autorizada')->where('delivery_status', '!=', 'entregado'))
            ->when($tray === 'delivered', fn ($q) => $q->where('delivery_status', 'entregado'))
            ->when($tray === 'closed', fn ($q) => $q->whereNotNull('closed_at'))
            ->withMin([
                'milestones as next_payment_due_date' => fn ($query) => $query
                    ->whereNotNull('due_date')
                    ->whereColumn('covered_amount', '<', 'value'),
            ], 'due_date')
            ->withMin([
                'items as next_delivery_due_date' => fn ($query) => $query
                    ->whereNotNull('delivery_due_date'),
            ], 'delivery_due_date')
            ->when(in_array($sortDue, ['payment_asc', 'payment_desc', 'delivery_asc', 'delivery_desc'], true), function ($query) use ($sortDue) {
                $column = str_starts_with($sortDue, 'payment_')
                    ? 'next_payment_due_date'
                    : 'next_delivery_due_date';
                $direction = str_ends_with($sortDue, '_asc') ? 'asc' : 'desc';

                $query->orderByRaw("CASE WHEN {$column} IS NULL THEN 1 ELSE 0 END")
                    ->orderBy($column, $direction)
                    ->orderByDesc('purchase_orders.created_at');
            }, function ($query) {
                $query->latest();
            })
            ->paginate(25)
            ->withQueryString();

        $suppliers             = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();
        $projects              = Project::where('status', 'active')->orderBy('name')->get();
        $nextFolio             = (PurchaseOrder::withTrashed()->max('folio') ?? 0) + 1;
        $authorizedSignatories = config('purchase_orders.authorized_signatories', []);
        $recentPendingInvoices = PurchaseOrderInvoice::query()
            ->with([
                'purchaseOrder:id,folio,supplier_id,buyer_id,elaborated_by',
                'purchaseOrder.buyer:id,name',
                'purchaseOrder.supplier:id,rfc_name,commercial_name',
            ])
            ->whereHas('purchaseOrder', function ($query) use ($scope, $buyerStatus) {
                $query->active()
                    ->when($scope === 'mine', fn ($query) => $query->ownedBy(Auth::id()))
                    ->when($buyerStatus === 'unassigned', fn ($query) => $query->whereNull('buyer_id'));
            })
            ->where('status', PurchaseOrderInvoice::STATUS_EN_PROCESO)
            ->orderByDesc('attached_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $trayCounts = Auth::user()->hasRole('admin')
            ? [
                'all' => (clone $orderQuery)->count(),
                'issued' => (clone $orderQuery)->where('status', 'emitida')->where('delivery_status', '!=', 'entregado')->count(),
                'authorized' => (clone $orderQuery)->where('status', 'autorizada')->where('delivery_status', '!=', 'entregado')->count(),
                'delivered' => (clone $orderQuery)->where('delivery_status', 'entregado')->count(),
                'closed' => (clone $orderQuery)->whereNotNull('closed_at')->count(),
            ]
            : [];

        return view('purchase_orders.index', compact(
            'orders', 'suppliers', 'search', 'searchMode', 'tipo', 'sortDue', 'tray', 'trayCounts',
            'projects', 'nextFolio', 'authorizedSignatories', 'recentPendingInvoices', 'scope', 'buyerStatus'
        ));
    }

    private function listingScope(Request $request): string
    {
        return Auth::user()->hasAnyRole(['admin', 'Pagos']) && $request->input('scope', 'all') !== 'mine' ? 'all' : 'mine';
    }

    public function overdueDeliveries(): View
    {
        $orders = PurchaseOrder::query()
            ->when(!Auth::user()->hasRole('admin'), fn ($query) => $query->ownedBy(Auth::id()))
            ->select('purchase_orders.*')
            ->selectSub(
                PurchaseOrderItem::query()
                    ->selectRaw('MIN(delivery_due_date)')
                    ->whereColumn('purchase_order_items.purchase_order_id', 'purchase_orders.id')
                    ->whereNotNull('delivery_due_date')
                    ->whereDate('delivery_due_date', '<', today()),
                'oldest_overdue_delivery_due_date'
            )
            ->where('purchase_orders.status', 'autorizada')
            ->whereNull('purchase_orders.archived_at')
            ->where('purchase_orders.delivery_status', '!=', 'entregado')
            ->with([
                'supplier',
                'purchaseRequest.assignedTo',
                'purchaseRequest.materialRequest',
                'items' => fn ($query) => $query->whereNotNull('delivery_due_date')->orderBy('delivery_due_date'),
            ])
            ->whereHas('items', function ($query) {
                $query->whereNotNull('delivery_due_date')
                    ->whereDate('delivery_due_date', '<', today());
            })
            ->orderBy('oldest_overdue_delivery_due_date')
            ->paginate(25);

        return view('purchase_orders.overdue_deliveries', compact('orders'));
    }

    public function create(): View
    {
        $suppliers             = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();
        $projects              = Project::where('status', 'active')->orderBy('name')->get();
        $mobileAssets          = MobileAsset::where('status', 'activo')->orderBy('name')->get();
        $nextFolio             = (PurchaseOrder::withTrashed()->max('folio') ?? 0) + 1;
        $authorizedSignatories = config('purchase_orders.authorized_signatories', []);

        return view('purchase_orders.create', compact(
            'suppliers', 'projects', 'mobileAssets', 'nextFolio', 'authorizedSignatories'
        ));
    }

    /**
     * Abre el formulario de nueva OC pre-vinculando una SOLCOM.
     * Equivalente a "crear SOLCOM desde SOLMAT" pero para OC desde SOLCOM.
     */
    public function createFromSolcom(PurchaseRequest $purchaseRequest): View|RedirectResponse
    {
        $purchaseRequest->load([
            'project',
            'projectWork',
            'projectWorks',
            'items.concept',
            'items.purchaseOrderItems.purchaseOrder:id,folio,status',
            'purchaseOrders:id,purchase_request_id,folio',
        ]);

        if ($purchaseRequest->items->isNotEmpty() && !$purchaseRequest->items->contains(fn ($item) => $item->is_pending)) {
            return redirect()->route('purchasing.solcom_pile', ['section' => 'salida'])
                ->with('error', "Todos los conceptos de la SOLCOM #{$purchaseRequest->folio} ya fueron fincados por completo a una OC.");
        }

        $suppliers             = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();
        $projects              = Project::where('status', 'active')->orderBy('name')->get();
        $nextFolio             = (PurchaseOrder::withTrashed()->max('folio') ?? 0) + 1;
        $authorizedSignatories = config('purchase_orders.authorized_signatories', []);

        return view('purchase_orders.create_from_solcom', compact(
            'purchaseRequest', 'suppliers', 'projects', 'nextFolio', 'authorizedSignatories'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'folio'                => 'nullable|integer',
            'type'                 => 'required|in:materiales_servicios,mantenimiento',
            'is_destajo'           => 'boolean',
            'supplier_id'          => 'required|exists:suppliers,id',
            'site'                 => 'nullable|string|max:255',
            'currency'             => 'required|in:MXN,USD,EUR',
            'tax_rate'             => 'required|in:0,8,16,exempt',
            'isr_rate'             => 'nullable|numeric|min:0|max:100',
            'retention_iva_rate'   => 'nullable|numeric|min:0|max:100',
            'retention_isr_rate'   => 'nullable|numeric|min:0|max:100',
                'cedular_rate'         => 'nullable|numeric|min:0|max:100',
            'purchase_request_id'  => 'nullable|exists:purchase_requests,id',
            'attorney_name'        => 'nullable|string|max:255',
            'supplier_signatory'   => 'nullable|string|max:255',
            'authorized_signatory' => 'nullable|string|max:255',
        ];

        if ($request->type === 'materiales_servicios') {
            $rules['project_id']      = 'nullable|exists:projects,id';
            $rules['project_work_id'] = 'nullable|exists:project_works,id';
            $rules['project_work_ids'] = 'nullable|array|min:1';
            $rules['project_work_ids.*'] = 'exists:project_works,id';

            // Si se crea desde SOLCOM, permitir seleccionar qué conceptos heredar
            if ($request->filled('purchase_request_id')) {
                $rules['selected_item_ids'] = ['required', 'array', 'min:1'];
                $rules['item_quantities'] = ['nullable', 'array'];
                $rules['item_quantities.*'] = ['nullable', 'numeric', 'gt:0', 'decimal:0,4'];
                $rules['selected_item_ids.*'] = [
                    'integer',
                    Rule::exists('purchase_request_items', 'id')->where(function ($q) use ($request) {
                        $q->where('purchase_request_id', (int) $request->input('purchase_request_id'));
                    }),
                ];
            }
        }

        if ($request->type === 'mantenimiento') {
            $rules['mobile_asset_id'] = 'required|exists:mobile_assets,id';
            $rules['project_id'] = 'required|exists:projects,id';
            $rules['project_work_ids'] = 'required|array|min:1';
            $rules['project_work_ids.*'] = 'exists:project_works,id';
        }

        $validated = $request->validate($rules);
        $validated['is_destajo'] = $request->boolean('is_destajo');

        $supplier = Supplier::findOrFail($validated['supplier_id']);
        $missingSupplierFields = $supplier->purchaseOrderMissingFields();

        if ($missingSupplierFields !== []) {
            throw ValidationException::withMessages([
                'supplier_id' => 'El perfil del proveedor está incompleto: ' . implode(', ', $missingSupplierFields) . '.',
            ]);
        }

        $selectedWorkIds = collect($validated['project_work_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($selectedWorkIds->isEmpty() && !empty($validated['project_work_id'])) {
            $selectedWorkIds = collect([(int) $validated['project_work_id']]);
        }

        if (in_array($validated['type'] ?? null, ['materiales_servicios', 'mantenimiento'], true) && $selectedWorkIds->isEmpty()) {
            throw ValidationException::withMessages([
                'project_work_ids' => 'Debes seleccionar al menos una obra para crear la OC.',
            ]);
        }

        if (!empty($validated['project_id']) && $selectedWorkIds->isNotEmpty()) {
            $validWorksCount = ProjectWork::where('project_id', (int) $validated['project_id'])
                ->whereIn('id', $selectedWorkIds)
                ->count();

            if ($validWorksCount !== $selectedWorkIds->count()) {
                throw ValidationException::withMessages([
                    'project_work_ids' => 'Todas las obras deben pertenecer al proyecto seleccionado.',
                ]);
            }
        }

        $sourcePurchaseRequest = null;
        if (!empty($validated['purchase_request_id'])) {
            $sourcePurchaseRequest = PurchaseRequest::with(['items.concept', 'projectWorks'])
                ->find($validated['purchase_request_id']);

            if ($sourcePurchaseRequest && $selectedWorkIds->isNotEmpty()) {
                // La OC hereda el proyecto de la SOLCOM origen.
                $validated['project_id'] = (int) $sourcePurchaseRequest->project_id;

                $allowedWorkIds = $sourcePurchaseRequest->projectWorks
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);

                $outside = $selectedWorkIds->diff($allowedWorkIds);
                if ($outside->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'project_work_ids' => 'Solo puedes seleccionar obras vinculadas a la SOLCOM origen.',
                    ]);
                }
            }
        }

        if (in_array($validated['type'] ?? null, ['materiales_servicios', 'mantenimiento'], true)) {
            $validated['project_work_id'] = $selectedWorkIds->first();
        }

        unset($validated['project_work_ids']);

        if ($request->type === 'mantenimiento') {
            $validated['purchase_request_id'] = null;
            unset($validated['selected_item_ids']);
        } else {
            $validated['mobile_asset_id'] = null;
        }

        // Normalizar tax_rate: 'exempt' → 0 para almacenar, flag aparte no needed (0% y exento son 0 en cálculo)
        // Guardamos el valor como decimal: exempt se almacena como null para distinguirlo visualmente
        $validated['tax_rate'] = $validated['tax_rate'] === 'exempt' ? null : (float) $validated['tax_rate'];
        $validated['isr_rate'] = $this->normalizeOptionalRate($validated['isr_rate'] ?? null);
        $validated['retention_iva_rate'] = $this->normalizeOptionalRate($validated['retention_iva_rate'] ?? null);
        $validated['retention_isr_rate'] = $this->normalizeOptionalRate($validated['retention_isr_rate'] ?? null);
            $validated['cedular_rate'] = $this->normalizeOptionalRate($validated['cedular_rate'] ?? null);

        $validated['buyer_id'] = Auth::id();
        $validated['elaborated_by'] = Auth::user()->name;

        // amount siempre parte en 0; se recalculará cuando se agreguen conceptos
        $validated['amount'] = 0;
        $validated['recurrence_type'] = 'unico';
        $validated['recurrence_frequency'] = null;
        $validated['recurrence_start_date'] = null;
        $validated['recurrence_end_date'] = null;
        $validated['status'] = 'pendiente';

        unset($validated['folio']);

        $order = DB::transaction(function () use ($validated, $selectedWorkIds, $sourcePurchaseRequest) {
            $nextFolio = (PurchaseOrder::withTrashed()->lockForUpdate()->max('folio') ?? 0) + 1;

            $validated['folio'] = $nextFolio;
            $order = PurchaseOrder::create($validated);

            if (in_array($validated['type'] ?? null, ['materiales_servicios', 'mantenimiento'], true)) {
                $order->projectWorks()->sync($selectedWorkIds->all());
            }

            // Si viene vinculada a una SOLCOM: copiar sus ítems
            if (!empty($validated['purchase_request_id'])) {
                $pr = $sourcePurchaseRequest;

                if ($pr) {
                    $selectedItemIds = collect($validated['selected_item_ids'] ?? [])->map(fn ($id) => (int) $id);

                    // Bloquea los conceptos para que dos OC simultáneas no hereden el mismo concepto.
                    PurchaseRequestItem::where('purchase_request_id', $pr->id)
                        ->whereIn('id', $selectedItemIds)
                        ->lockForUpdate()
                        ->get(['id']);

                    $requestedQuantities = $validated['item_quantities'] ?? [];

                    $itemsToCopy = PurchaseRequestItem::with([
                        'concept',
                        'purchaseOrderItems.purchaseOrder:id,folio,status',
                    ])
                        ->whereIn('id', $selectedItemIds)
                        ->orderBy('id')
                        ->get();

                    $quantitiesToInherit = [];
                    $errors = [];

                    foreach ($itemsToCopy as $item) {
                        $remaining = $item->remaining_quantity;

                        if ($remaining <= 0) {
                            $errors[] = '"' . $item->description . '" ya fue fincado por completo (OC #' . $item->inheritedOrders()->pluck('folio')->implode(', #') . ')';
                            continue;
                        }

                        $quantity = isset($requestedQuantities[$item->id]) && $requestedQuantities[$item->id] !== ''
                            ? round((float) $requestedQuantities[$item->id], 4)
                            : $remaining;

                        if ($quantity > $remaining) {
                            $errors[] = '"' . $item->description . '": la cantidad a fincar (' . $quantity . ') excede la no fincada (' . $remaining . ')';
                            continue;
                        }

                        $quantitiesToInherit[$item->id] = $quantity;
                    }

                    if ($errors !== []) {
                        throw ValidationException::withMessages([
                            'selected_item_ids' => implode('; ', $errors) . '.',
                        ]);
                    }

                    foreach ($itemsToCopy as $item) {
                        PurchaseOrderItem::create([
                            'purchase_order_id' => $order->id,
                            'purchase_request_item_id' => $item->id,
                            'concept_id'        => $item->concept_id,
                            'description'       => $item->description,
                            'unit'              => $item->unit,
                            'quantity'          => $quantitiesToInherit[$item->id],
                            'unit_price'        => $item->concept?->unit_price ?? 0,
                            'delivery_date'     => null,
                        ]);
                    }

                    // Heredar observaciones de SOLCOM a OC para conservar el historial del flujo.
                    if (!empty($pr->observations) && empty($order->observations)) {
                        $order->update(['observations' => $pr->observations]);
                    }

                    // Recalcular amount a partir de los ítems copiados
                    $order->recalculateAmount();

                    // Mantener la SOLCOM activa en la Pila de Compras para permitir bifurcaciones.
                    // El cierre de la SOLCOM debe ser explícito y no automático con la primera OC.
                    if ($pr->status !== 'sent_to_purchasing') {
                        $pr->update(['status' => 'sent_to_purchasing']);
                    }
                }
            }

            return $order;
        });

        $supplierName = $order->supplier->rfc_name ?? $order->supplier->commercial_name ?? 'Proveedor desconocido';

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $order->id,
            'data'         => 'creó la orden de compra #' . $order->folio . ' para ' . $supplierName . '.',
        ]);

        $successMsg = 'Orden de compra #' . $order->folio . ' creada correctamente.';

        return redirect()->route('purchase_orders.show', $order)
            ->with('success', $successMsg);
    }


    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->ensureProjectVisible($this->purchaseOrderProjectId($purchaseOrder));

        $isRestrictedEngineer = (bool) Auth::user()?->isRestrictedEngineer();

        if ($isRestrictedEngineer) {
            $purchaseOrder->load([
                'buyer',
                'supplier',
                'projectRelation',
                'items.concept',
                'purchaseRequest.purchaseOrders:id,purchase_request_id,folio,created_at',
            ]);
            $purchaseOrder->setRelation('milestones', collect());
            $purchaseOrder->setRelation('invoices', collect());
            $purchaseOrder->setRelation('evidences', collect());

            return view('purchase_orders.show', [
                'purchaseOrder' => $purchaseOrder,
                'pendingAuthCount' => 0,
                'nextPendingPurchaseOrder' => null,
                'buyers' => collect(),
                'isRestrictedEngineer' => true,
                'movementHistory' => collect(),
            ]);
        }

        $purchaseOrder->load([
            'buyer',
            'supplier.contacts',
            'supplier.locations',
            'mobileAsset',
            'projectRelation',
            'milestones.payments',
            'milestones.invoices',
            'milestones.adjustments.user',
            'invoices.milestones',
            'evidences.milestone',
            'evidences.uploader',
            'items.concept',
            'purchaseRequest.purchaseOrders:id,purchase_request_id,folio,created_at',
            'annex',
        ]);

        $pendingQueue = Auth::user()->hasRole('admin') ? PurchaseOrder::query()
            ->whereNull('archived_at')
            ->where('status', 'emitida')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'folio']) : collect();

        $buyers = Auth::user()->hasRole('admin')
            ? User::role(['admin', 'Orden de compra'])->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        $pendingAuthCount = $pendingQueue->count();
        $currentPendingIndex = $pendingQueue->search(fn ($row) => (int) $row->id === (int) $purchaseOrder->id);
        if ($currentPendingIndex === false) {
            $nextPendingPurchaseOrder = $pendingQueue->first();
        } else {
            $nextPendingPurchaseOrder = $pendingQueue->get($currentPendingIndex + 1);

            if (!$nextPendingPurchaseOrder) {
                $nextPendingPurchaseOrder = $pendingQueue
                    ->first(fn ($row) => (int) $row->id !== (int) $purchaseOrder->id);
            }
        }

        $movementHistory = Auth::user()->hasRole('admin')
            ? Notification::with('user:id,name')
                ->forPurchaseOrder($purchaseOrder)
                ->latest()
                ->latest('id')
                ->limit(200)
                ->get()
            : collect();

        return view('purchase_orders.show', compact(
            'purchaseOrder',
            'pendingAuthCount',
            'nextPendingPurchaseOrder',
            'buyers',
            'isRestrictedEngineer',
            'movementHistory'
        ));
    }

    private function purchaseOrderProjectId(PurchaseOrder $purchaseOrder): ?int
    {
        $projectId = $purchaseOrder->project_id
            ?? $purchaseOrder->workRelation()->value('project_id')
            ?? $purchaseOrder->purchaseRequest()->value('project_id');

        return $projectId ? (int) $projectId : null;
    }

    public function assignBuyer(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate(['buyer_id' => ['required', 'integer', 'exists:users,id']]);
        $buyer = User::findOrFail($validated['buyer_id']);
        $previousBuyer = $purchaseOrder->buyer?->name ?? 'Sin comprador asignado';
        $purchaseOrder->update(['buyer_id' => $buyer->id]);

        $this->notification->send([
            'type' => 'PurchaseOrder',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $purchaseOrder->id,
            'data' => 'reasignó el comprador de la OC #' . $purchaseOrder->folio . ': ' . $previousBuyer . ' → ' . $buyer->name . '.',
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Comprador actualizado correctamente.');
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            return $redirect;
        }

        $suppliers             = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();
        $projects              = Project::where('status', 'active')->orderBy('name')->get();
        $mobileAssets          = MobileAsset::where('status', 'activo')->orderBy('name')->get();
        $authorizedSignatories = config('purchase_orders.authorized_signatories', []);

        return view('purchase_orders.edit', compact('purchaseOrder', 'suppliers', 'projects', 'mobileAssets', 'authorizedSignatories'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            return $redirect;
        }

        $rules = [
            'type'                 => 'required|in:materiales_servicios,mantenimiento',
            'is_destajo'           => 'boolean',
            'supplier_id'          => 'required|exists:suppliers,id',
            'currency'             => 'required|in:MXN,USD,EUR',
              'site'                 => 'nullable|string|max:255',
            'tax_rate'             => 'required|in:0,8,16,exempt',
            'isr_rate'             => 'nullable|numeric|min:0|max:100',
            'retention_iva_rate'   => 'nullable|numeric|min:0|max:100',
            'retention_isr_rate'   => 'nullable|numeric|min:0|max:100',
              'cedular_rate'         => 'nullable|numeric|min:0|max:100',
            'attorney_name'        => 'nullable|string|max:255',
            'supplier_signatory'   => 'nullable|string|max:255',
            'authorized_signatory' => 'nullable|string|max:255',
        ];

        if ($request->type === 'materiales_servicios') {
            $rules['project_id']      = 'nullable|exists:projects,id';
            $rules['project_work_id'] = 'nullable|exists:project_works,id';
            $rules['project_work_ids'] = 'nullable|array|min:1';
            $rules['project_work_ids.*'] = 'exists:project_works,id';
        }

        if ($request->type === 'mantenimiento') {
            $rules['mobile_asset_id'] = 'required|exists:mobile_assets,id';
        }

        $validated = $request->validate($rules);
    $validated['is_destajo'] = $request->boolean('is_destajo');
        $validated['status'] = $purchaseOrder->status;

        if ($purchaseOrder->status === 'autorizada') {
            unset($validated['site']);
        }

        $selectedWorkIds = collect($validated['project_work_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($selectedWorkIds->isEmpty() && !empty($validated['project_work_id'])) {
            $selectedWorkIds = collect([(int) $validated['project_work_id']]);
        }

        if (($validated['type'] ?? null) === 'materiales_servicios' && $selectedWorkIds->isEmpty()) {
            throw ValidationException::withMessages([
                'project_work_ids' => 'Debes seleccionar al menos una obra para guardar la OC.',
            ]);
        }

        if (($validated['type'] ?? null) === 'materiales_servicios' && !empty($validated['project_id']) && $selectedWorkIds->isNotEmpty()) {
            $validWorksCount = ProjectWork::where('project_id', (int) $validated['project_id'])
                ->whereIn('id', $selectedWorkIds)
                ->count();

            if ($validWorksCount !== $selectedWorkIds->count()) {
                throw ValidationException::withMessages([
                    'project_work_ids' => 'Todas las obras deben pertenecer al proyecto seleccionado.',
                ]);
            }
        }

        if (!empty($purchaseOrder->purchase_request_id) && $selectedWorkIds->isNotEmpty()) {
            $originPurchaseRequest = PurchaseRequest::find($purchaseOrder->purchase_request_id);
            $allowedWorkIds = $originPurchaseRequest
                ? $originPurchaseRequest->projectWorks()->pluck('project_works.id')->map(fn ($id) => (int) $id)
                : collect();

            $outside = $selectedWorkIds->diff($allowedWorkIds);
            if ($outside->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'project_work_ids' => 'Solo puedes seleccionar obras vinculadas a la SOLCOM origen.',
                ]);
            }
        }

        if (($validated['type'] ?? null) === 'materiales_servicios') {
            $validated['project_work_id'] = $selectedWorkIds->first();
        }

        unset($validated['project_work_ids']);

        // Normalizar tax_rate
        $validated['tax_rate'] = ($validated['tax_rate'] ?? '16') === 'exempt' ? null : (float) ($validated['tax_rate'] ?? 16);
        $validated['isr_rate'] = $this->normalizeOptionalRate($validated['isr_rate'] ?? null);
        $validated['retention_iva_rate'] = $this->normalizeOptionalRate($validated['retention_iva_rate'] ?? null);
        $validated['retention_isr_rate'] = $this->normalizeOptionalRate($validated['retention_isr_rate'] ?? null);
            $validated['cedular_rate'] = $this->normalizeOptionalRate($validated['cedular_rate'] ?? null);

        // Recalcular amount desde los ítems actuales (no viene del form)
        unset($validated['amount']);

        if ($request->type === 'mantenimiento') {
            $validated['project_id']      = null;
            $validated['project_work_id'] = null;
            $validated['project']         = null;
        } else {
            $validated['mobile_asset_id'] = null;
        }
        $validated['recurrence_type'] = 'unico';
        $validated['recurrence_frequency']  = null;
        $validated['recurrence_start_date'] = null;
        $validated['recurrence_end_date']   = null;

        $purchaseOrder->update($validated);

        if (($validated['type'] ?? null) === 'materiales_servicios') {
            $purchaseOrder->projectWorks()->sync($selectedWorkIds->all());
        } else {
            $purchaseOrder->projectWorks()->detach();
        }

        // Recalcular amount a partir de los ítems con la nueva tax_rate
        $purchaseOrder->recalculateAmount();

        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'actualizó la orden de compra #' . ($purchaseOrder->folio ?? $purchaseOrder->id) . ' de ' . $supplierName . '.',
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Orden de compra actualizada correctamente.');
    }

    public function emit(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== 'pendiente') {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'Solo se pueden emitir órdenes de compra pendientes.');
        }

        $purchaseOrder->update(['status' => 'emitida']);

        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'emitió la orden de compra #' . ($purchaseOrder->folio ?? $purchaseOrder->id) . ' de ' . $supplierName . '.',
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Orden de compra #' . ($purchaseOrder->folio ?? $purchaseOrder->id) . ' emitida correctamente.');
    }

    public function close(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'closure_note' => 'nullable|string|max:1000',
        ]);

        $isAdmin = Auth::user()->hasRole('admin');
        if (!$isAdmin && (int) $purchaseOrder->buyer_id !== (int) Auth::id()) {
            abort(403);
        }

        $folio = $purchaseOrder->folio ?? $purchaseOrder->id;

        DB::transaction(function () use ($purchaseOrder, $validated) {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($locked->is_closed) {
                throw ValidationException::withMessages(['purchase_order' => 'La orden de compra ya está cerrada.']);
            }

            $closure = $this->closureEvaluator->evaluate($locked);
            if (!$closure['ready']) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'No se puede cerrar la OC. Pendiente: ' . implode(', ', $closure['pending']) . '.',
                ]);
            }

            $locked->update([
                'closed_at' => now(),
                'closed_by' => Auth::id(),
                'closure_note' => $validated['closure_note'] ?? null,
            ]);
        });

        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';
        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'cerró la orden de compra #' . $folio . ' de ' . $supplierName . '.',
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Orden de compra #' . $folio . ' cerrada.');
    }

    public function reopen(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'reopen_reason' => 'required|string|min:10|max:1000',
        ]);

        if (!$purchaseOrder->is_closed) {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'La orden de compra no está cerrada.');
        }

        $folio = $purchaseOrder->folio ?? $purchaseOrder->id;
        $purchaseOrder->update(['closed_at' => null, 'closed_by' => null, 'closure_note' => null]);

        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';
        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'reabrió la orden de compra #' . $folio . ' de ' . $supplierName . '. Motivo: ' . $validated['reopen_reason'],
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Orden de compra #' . $folio . ' reabierta.');
    }

    public function approve(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status === 'autorizada') {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'La orden de compra ya está autorizada.');
        }

        if ($purchaseOrder->status !== 'emitida') {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'La orden de compra debe estar emitida antes de autorizarse.');
        }

        $purchaseOrder->update(['status' => 'autorizada', 'authorized_at' => now()]);

        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'autorizó la orden de compra #' . ($purchaseOrder->folio ?? $purchaseOrder->id) . ' de ' . $supplierName . '.',
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Orden de compra #' . ($purchaseOrder->folio ?? $purchaseOrder->id) . ' autorizada correctamente.');
    }

    public function approveWithPayments(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'payment_ids' => 'nullable|array|max:500',
            'payment_ids.*' => 'integer|distinct|exists:payments,id',
        ]);

        $paymentIds = collect($validated['payment_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $authorizedPayments = DB::transaction(function () use ($purchaseOrder, $paymentIds) {
            $lockedPurchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($lockedPurchaseOrder->status === 'autorizada') {
                throw ValidationException::withMessages([
                    'purchase_order' => 'La orden de compra ya está autorizada.',
                ]);
            }

            if ($lockedPurchaseOrder->status !== 'emitida') {
                throw ValidationException::withMessages([
                    'purchase_order' => 'La orden de compra debe estar emitida antes de autorizarse.',
                ]);
            }

            $payments = Payment::query()
                ->whereIn('id', $paymentIds)
                ->whereHas('milestone', fn ($query) => $query->where('purchase_order_id', $lockedPurchaseOrder->id))
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($payments->count() !== $paymentIds->count()
                || $payments->contains(fn (Payment $payment) => !in_array($payment->status, ['por_autorizar', 'pospuesto'], true))) {
                throw ValidationException::withMessages([
                    'payment_ids' => 'Solo se pueden autorizar pagos pendientes de esta orden de compra.',
                ]);
            }

            $this->paymentSequenceValidator->ensureStatusSequence(
                $lockedPurchaseOrder,
                $payments->mapWithKeys(fn (Payment $payment) => [$payment->id => 'autorizado'])->all(),
            );
            $this->milestoneChecklist->ensureComplete($payments);
            $lockedPurchaseOrder->update(['status' => 'autorizada', 'authorized_at' => now()]);
            $payments->each(fn (Payment $payment) => $payment->update(['status' => 'autorizado', 'checklist_verified_at' => now()]));

            return $payments;
        });

        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';
        $purchaseOrderFolio = $purchaseOrder->folio ?? $purchaseOrder->id;

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'autorizó la orden de compra #' . $purchaseOrderFolio . ' de ' . $supplierName . '.',
        ]);

        foreach ($authorizedPayments as $payment) {
            $this->notification->send([
                'type'         => 'Payment',
                'action_by'    => Auth::id(),
                'model_action' => 'update',
                'model_id'     => $payment->id,
                'data'         => 'autorizó el pago #' . $payment->folio . ' de la orden de compra #' . $purchaseOrderFolio . '.',
            ]);
        }

        $message = 'Orden de compra #' . $purchaseOrderFolio . ' autorizada correctamente.';
        if ($authorizedPayments->isNotEmpty()) {
            $message .= ' Se autorizaron ' . $authorizedPayments->count() . ' pago(s).';
        }

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', $message);
    }

    public function deliveryReport(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $orders = collect();
        $selectedOrder = null;

        if ($search !== '') {
            $escaped = addcslashes($search, '\\%_');
            $orders = PurchaseOrder::with('supplier')
                ->whereNull('archived_at')
                ->where('folio', 'like', '%' . $escaped . '%')
                ->orderByDesc('id')
                ->limit(20)
                ->get();

            $selectedId = $request->integer('order') ?: ($orders->count() === 1 ? $orders->first()->id : null);
            $selectedOrder = $selectedId ? $orders->firstWhere('id', $selectedId) : null;

            if ($selectedOrder) {
                $selectedOrder->load([
                    'items.deliveries.deliveredByUser',
                    'milestones',
                ]);
            }
        }

        return view('purchase_orders.delivery_report', compact('search', 'orders', 'selectedOrder'));
    }

    public function storeDelivery(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->ensureOpen();

        $user = Auth::user();
        $canManage = $user->hasAnyRole(['admin', 'Solmat', 'Inventario'])
            || ($user->hasRole('Orden de compra') && (int) $purchaseOrder->buyer_id === (int) $user->id);
        abort_unless($canManage, 403);

        if ($purchaseOrder->status !== 'autorizada') {
            return back()->with('error', 'Solo se puede registrar la entrega de una OC autorizada.');
        }

        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => [
                'integer',
                Rule::exists('purchase_order_items', 'id')
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->whereNull('delivered_at'),
            ],
            'milestone_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_order_milestones', 'id')->where('purchase_order_id', $purchaseOrder->id),
            ],
            'quantities' => 'nullable|array',
            'quantities.*' => 'nullable|numeric|min:0.0001|decimal:0,4',
            'delivery_evidence' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ], [
            'item_ids.required' => 'Selecciona al menos un concepto entregado.',
            'item_ids.min' => 'Selecciona al menos un concepto entregado.',
            'milestone_id.exists' => 'El hito seleccionado no pertenece a la OC.',
            'item_ids.*.exists' => 'Alguno de los conceptos no pertenece a la OC o ya fue entregado.',
            'quantities.*.min' => 'La cantidad entregada debe ser mayor a cero.',
            'quantities.*.numeric' => 'La cantidad entregada debe ser numérica.',
            'quantities.*.decimal' => 'La cantidad entregada admite máximo 4 decimales.',
            'delivery_evidence.required' => 'Adjunta la evidencia de entrega.',
        ]);

        $itemIds = array_values(array_unique($validated['item_ids']));
        $evidenceFileName = null;

        DB::transaction(function () use ($request, $purchaseOrder, $itemIds, $validated, &$evidenceFileName) {
            $items = $purchaseOrder->items()->whereIn('id', $itemIds)->lockForUpdate()->get();

            // Cada concepto entrega su cantidad indicada; si no se indica, el pendiente completo.
            $deliveries = [];
            foreach ($items as $item) {
                $pending = $item->pending_quantity;
                $quantity = isset($validated['quantities'][$item->id]) && $validated['quantities'][$item->id] !== ''
                    ? round((float) $validated['quantities'][$item->id], 4)
                    : $pending;

                if ($quantity > $pending + 0.00001) {
                    throw ValidationException::withMessages([
                        'quantities.' . $item->id => 'La cantidad de "' . $item->description . '" excede lo pendiente por entregar (' . rtrim(rtrim(number_format($pending, 4, '.', ''), '0'), '.') . ').',
                    ]);
                }

                $deliveries[$item->id] = $quantity;
            }

            $file = $request->file('delivery_evidence');
            $nextNumber = $purchaseOrder->evidences()->count() + 1;
            $extension = strtolower($file->getClientOriginalExtension());
            $evidenceFileName = 'OC' . $purchaseOrder->id . '-EVID' . $nextNumber . '.' . $extension;
            $filePath = $file->storeAs('evidences/' . $purchaseOrder->id, $evidenceFileName);

            $evidence = PurchaseOrderEvidence::create([
                'purchase_order_id' => $purchaseOrder->id,
                'uploaded_by' => Auth::id(),
                'file_name' => $evidenceFileName,
                'file_path' => $filePath,
                'mime_type' => $file->getClientMimeType(),
                'source' => 'internal',
                'description' => 'Evidencia de entrega (' . count($itemIds) . ' concepto(s))',
            ]);

            foreach ($deliveries as $itemId => $quantity) {
                PurchaseOrderItemDelivery::create([
                    'purchase_order_item_id' => $itemId,
                    'evidence_id' => $evidence->id,
                    'milestone_id' => $validated['milestone_id'] ?? null,
                    'delivered_by' => Auth::id(),
                    'quantity' => $quantity,
                    'delivered_at' => now(),
                ]);
            }

            $purchaseOrder->syncDeliveryStatus();
        });

        $purchaseOrder->refresh();
        $deliveryLabel = ['por_entregar' => 'Por entregar', 'parcial' => 'Entrega parcial', 'entregado' => 'Entregado'][$purchaseOrder->delivery_status];
        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'registró la entrega de ' . count($itemIds) . ' concepto(s) con la evidencia ' . $evidenceFileName . ' en la orden de compra #' . ($purchaseOrder->folio ?? $purchaseOrder->id) . ' de ' . $supplierName . '. Estatus de entrega: "' . $deliveryLabel . '".',
        ]);

        $route = $request->input('return_to') === 'delivery_report'
            ? redirect()->route('purchase_orders.delivery_report', ['search' => $purchaseOrder->folio ?? '', 'order' => $purchaseOrder->id])
            : redirect()->route('purchase_orders.show', $purchaseOrder);

        return $route->with('success', 'Entrega registrada. Estatus de entrega: "' . $deliveryLabel . '".');
    }

    public function revertDelivery(PurchaseOrder $purchaseOrder, PurchaseOrderItemDelivery $delivery): RedirectResponse
    {
        $purchaseOrder->ensureOpen();

        $item = $delivery->item;
        abort_unless($item && $item->purchase_order_id === $purchaseOrder->id, 404);

        $quantity = rtrim(rtrim(number_format((float) $delivery->quantity, 4, '.', ''), '0'), '.');
        $milestoneId = $delivery->milestone_id;
        $delivery->delete();

        if ($milestoneId && !PurchaseOrderItemDelivery::where('milestone_id', $milestoneId)->exists()) {
            PurchaseOrderMilestone::whereKey($milestoneId)->update([
                'delivery_confirmed_at' => null,
                'delivery_confirmed_by' => null,
            ]);
        }

        $purchaseOrder->syncDeliveryStatus(false);

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'revirtió una entrega de ' . $quantity . ' ' . $item->unit . ' del concepto "' . $item->description . '" en la orden de compra #' . ($purchaseOrder->folio ?? $purchaseOrder->id) . '.',
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Entrega revertida.');
    }

    public function confirmMilestoneDelivery(PurchaseOrder $purchaseOrder, PurchaseOrderMilestone $milestone): RedirectResponse
    {
        $purchaseOrder->ensureOpen();

        abort_unless($milestone->purchase_order_id === $purchaseOrder->id, 404);

        if ($milestone->delivery_confirmed_at === null && !$milestone->deliveries()->exists()) {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'Registra al menos una entrega ligada al hito antes de confirmarla.');
        }

        $confirmed = $milestone->delivery_confirmed_at === null;
        $milestone->update([
            'delivery_confirmed_at' => $confirmed ? now() : null,
            'delivery_confirmed_by' => $confirmed ? Auth::id() : null,
        ]);
        $creditWarning = $confirmed ? $this->creditTerms->apply($milestone->load('purchaseOrder')) : null;

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => ($confirmed ? 'confirmó' : 'revocó la confirmación de')
                . ' la entrega del hito #' . $milestone->id . ' en la orden de compra #' . ($purchaseOrder->folio ?? $purchaseOrder->id) . '.',
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', $confirmed ? 'Entrega del hito confirmada.' : 'Confirmación de entrega revocada.')
            ->with('warning', $creditWarning);
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            return $redirect;
        }

        $validated = $request->validate([
            'deletion_comment' => 'required|string|max:1000',
        ]);

        $folio        = $purchaseOrder->folio ?? $purchaseOrder->id;
        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        DB::transaction(function () use ($purchaseOrder, $validated, $folio, $supplierName) {
            $purchaseOrder->update([
                'deletion_comment' => $validated['deletion_comment'],
            ]);

            $purchaseOrder->delete();

            $this->notification->send([
                'type'         => 'PurchaseOrder',
                'action_by'    => Auth::id(),
                'model_action' => 'destroy',
                'model_id'     => $purchaseOrder->id,
                'data'         => 'eliminó la orden de compra #' . $folio . ' de ' . $supplierName . '. Motivo: ' . $validated['deletion_comment'],
            ]);
        });

        return redirect()->route('purchase_orders.index')
            ->with('success', 'Orden de compra eliminada.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ARCHIVO Y PAPELERA
    // ──────────────────────────────────────────────────────────────────────────

    public function archive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->update(['archived_at' => now()]);

        $folio        = $purchaseOrder->folio ?? $purchaseOrder->id;
        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'archive',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'archivó la orden de compra #' . $folio . ' de ' . $supplierName . '.',
        ]);

        return redirect()->route('purchase_orders.index')
            ->with('success', 'Orden de compra #' . $folio . ' archivada.');
    }

    public function unarchive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_if($purchaseOrder->supplier->trashed(), 409, 'Cannot unarchive an order for a deleted supplier.');

        $folio = $purchaseOrder->folio ?? $purchaseOrder->id;
        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        DB::transaction(function () use ($purchaseOrder, $folio, $supplierName) {
            $purchaseOrder->update(['archived_at' => null]);

            $this->notification->send([
                'type'         => 'PurchaseOrder',
                'action_by'    => Auth::id(),
                'model_action' => 'unarchive',
                'model_id'     => $purchaseOrder->id,
                'data'         => 'desarchivó la orden de compra #' . $folio . ' de ' . $supplierName . '.',
            ]);
        });

        return redirect()->route('purchase_orders.archived')
            ->with('success', 'Orden de compra #' . $folio . ' restaurada al listado activo.');
    }

    public function archived(Request $request): View
    {
        $scope = $this->listingScope($request);
        $search = trim($request->input('search', ''));

        $orders = PurchaseOrder::with(['supplier', 'items', 'buyer'])
            ->when($scope === 'mine', fn ($query) => $query->ownedBy(Auth::id()))
            ->whereNotNull('archived_at')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('supplier', function ($s) use ($search) {
                        $s->where('rfc_name', 'like', '%' . $search . '%')
                          ->orWhere('commercial_name', 'like', '%' . $search . '%');
                    })->orWhere('folio', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('archived_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('purchase_orders.archive', compact('orders', 'search', 'scope'));
    }

    public function softDeleted(Request $request): View
    {
        $search = trim($request->input('search', ''));

        $orders = PurchaseOrder::onlyTrashed()
            ->with(['supplier', 'buyer'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('supplier', function ($s) use ($search) {
                        $s->where('rfc_name', 'like', '%' . $search . '%')
                          ->orWhere('commercial_name', 'like', '%' . $search . '%');
                    })->orWhere('folio', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('deleted_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('purchase_orders.soft_deleted', compact('orders', 'search'));
    }

    public function restore(int $id): RedirectResponse
    {
        $purchaseOrder = PurchaseOrder::onlyTrashed()->findOrFail($id);
        abort_if($purchaseOrder->supplier->trashed(), 409, 'Cannot restore an order for a deleted supplier.');

        $folio = $purchaseOrder->folio ?? $purchaseOrder->id;
        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        DB::transaction(function () use ($purchaseOrder, $folio, $supplierName) {
            $purchaseOrder->restore();

            $this->notification->send([
                'type'         => 'PurchaseOrder',
                'action_by'    => Auth::id(),
                'model_action' => 'restore',
                'model_id'     => $purchaseOrder->id,
                'data'         => 'restauró de la papelera la orden de compra #' . $folio . ' de ' . $supplierName . '.',
            ]);
        });

        return redirect()->route('purchase_orders.soft_deleted')
            ->with('success', 'Orden de compra #' . $folio . ' restaurada.');
    }

    public function forceDestroy(int $id): RedirectResponse
    {
        $purchaseOrder = PurchaseOrder::onlyTrashed()->findOrFail($id);

        $folio        = $purchaseOrder->folio ?? $purchaseOrder->id;
        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        $purchaseOrder->loadCount(['items', 'milestones', 'invoices', 'evidences']);

        $summary = sprintf(
            '%d partida(s), %d hito(s), %d factura(s) y %d evidencia(s) asociadas',
            $purchaseOrder->items_count,
            $purchaseOrder->milestones_count,
            $purchaseOrder->invoices_count,
            $purchaseOrder->evidences_count,
        );
        $reason = $purchaseOrder->deletion_comment ? ' Motivo de la eliminación previa: ' . $purchaseOrder->deletion_comment : '';

        DB::transaction(function () use ($purchaseOrder, $folio, $supplierName, $summary, $reason) {
            $purchaseOrder->forceDelete();

            $this->notification->send([
                'type'         => 'PurchaseOrder',
                'action_by'    => Auth::id(),
                'model_action' => 'force_destroy',
                'model_id'     => $purchaseOrder->id,
                'data'         => 'eliminó permanentemente la orden de compra #' . $folio . ' de ' . $supplierName . ' (' . $summary . ').' . $reason,
            ]);
        });

        return redirect()->route('purchase_orders.soft_deleted')
            ->with('success', 'Orden de compra #' . $folio . ' eliminada permanentemente.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ÍTEMS (Conceptos)
    // ──────────────────────────────────────────────────────────────────────────

    public function storeItem(Request $request, PurchaseOrder $purchaseOrder): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Solo admin puede editar una OC autorizada.'], 403);
            }

            return $redirect;
        }

        $allowedConceptType = $purchaseOrder->type === 'mantenimiento' ? 'mantenimiento' : 'materiales';

        $data = $request->validate([
            'concept_id'    => [
                'nullable',
                Rule::exists('concepts', 'id')->where(function ($q) use ($allowedConceptType) {
                    $q->where('type', $allowedConceptType)
                      ->where('status', 'active')
                      ->whereNull('archived_at');
                }),
            ],
            'description'   => 'required|string|max:500',
            'unit'          => 'required|string|max:50',
            'quantity'      => 'required|numeric|min:0.0001|decimal:0,4',
            'unit_price'    => 'required|numeric|min:0',
            'delivery_due_date' => 'nullable|date_format:Y-m-d',
        ]);

        // Si viene del catálogo, normalizar con la definición oficial del concepto.
        if (! empty($data['concept_id'])) {
            $concept = Concept::select('description', 'unit')
                ->available()
                ->where('type', $allowedConceptType)
                ->find($data['concept_id']);

            if ($concept) {
                $data['description'] = $concept->description;
                $data['unit'] = $concept->unit;
            }
        }

        $data['purchase_order_id'] = $purchaseOrder->id;
        $item = PurchaseOrderItem::create($data);

        $percentageMilestonePaymentsSynchronized = $purchaseOrder->recalculateAmount();
        $purchaseOrder->syncDeliveryStatus();
        $purchaseOrder->refresh();

        if ($request->wantsJson()) {
            return response()->json([
                'id'             => $item->id,
                'description'    => $item->description,
                'unit'           => $item->unit,
                'quantity'       => (float) $item->quantity,
                'unit_price'     => $item->unit_price,
                'total'          => $item->total,
                'delivery_due_date' => $item->delivery_due_date?->format('Y-m-d'),
                'subtotal'       => $purchaseOrder->subtotal,
                'iva'            => $purchaseOrder->iva,
                'isr_amount'     => $purchaseOrder->isr_amount,
                'retention_iva_amount' => $purchaseOrder->retention_iva_amount,
                'retention_isr_amount' => $purchaseOrder->retention_isr_amount,
                    'cedular_amount' => $purchaseOrder->cedular_amount,
                'total_with_iva' => $purchaseOrder->total_with_iva,
                'amount'         => (float) $purchaseOrder->amount,
                'tax_rate'       => $purchaseOrder->tax_rate,
                'isr_rate'       => $purchaseOrder->isr_rate,
                'retention_iva_rate' => $purchaseOrder->retention_iva_rate,
                'retention_isr_rate' => $purchaseOrder->retention_isr_rate,
                    'cedular_rate'   => $purchaseOrder->cedular_rate,
                'percentage_milestone_payments_synchronized' => $percentageMilestonePaymentsSynchronized,
            ]);
        }

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Concepto agregado.');
    }

    public function updateItem(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Solo admin puede editar una OC autorizada.'], 403);
            }

            return $redirect;
        }

        $data = $request->validate([
            'quantity'          => 'sometimes|numeric|min:0.0001|decimal:0,4',
            'unit_price'        => 'sometimes|numeric|min:0',
            'delivery_due_date' => 'sometimes|nullable|date_format:Y-m-d',
        ]);

        // Un concepto fincado de SOLCOM no puede exceder la cantidad faltante por comprar.
        if (array_key_exists('quantity', $data) && $item->purchase_request_item_id) {
            $requestItem = PurchaseRequestItem::with('purchaseOrderItems.purchaseOrder:id,folio,status')
                ->find($item->purchase_request_item_id);

            if ($requestItem) {
                $maxQuantity = round($requestItem->remaining_quantity + (float) $item->quantity, 4);

                if (round((float) $data['quantity'], 4) > $maxQuantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'La cantidad excede lo disponible de la SOLCOM para este concepto (máximo ' . rtrim(rtrim(number_format($maxQuantity, 4, '.', ''), '0'), '.') . ').',
                    ]);
                }
            }
        }

        $item->update($data);

        $percentageMilestonePaymentsSynchronized = $purchaseOrder->recalculateAmount();
        if (array_key_exists('quantity', $data)) {
            $purchaseOrder->syncDeliveryStatus();
        }
        $purchaseOrder->refresh();

        if ($request->wantsJson()) {
            return response()->json([
                'id'             => $item->id,
                'quantity'       => (float) $item->quantity,
                'unit_price'     => $item->unit_price,
                'total'          => $item->total,
                'delivery_due_date' => $item->delivery_due_date?->format('Y-m-d'),
                'subtotal'       => $purchaseOrder->subtotal,
                'iva'            => $purchaseOrder->iva,
                'isr_amount'     => $purchaseOrder->isr_amount,
                'retention_iva_amount' => $purchaseOrder->retention_iva_amount,
                'retention_isr_amount' => $purchaseOrder->retention_isr_amount,
                    'cedular_amount' => $purchaseOrder->cedular_amount,
                'total_with_iva' => $purchaseOrder->total_with_iva,
                'amount'         => (float) $purchaseOrder->amount,
                'tax_rate'       => $purchaseOrder->tax_rate,
                'isr_rate'       => $purchaseOrder->isr_rate,
                'retention_iva_rate' => $purchaseOrder->retention_iva_rate,
                'retention_isr_rate' => $purchaseOrder->retention_isr_rate,
                    'cedular_rate'   => $purchaseOrder->cedular_rate,
                'percentage_milestone_payments_synchronized' => $percentageMilestonePaymentsSynchronized,
            ]);
        }

        return back()->with('success', 'Concepto actualizado.');
    }

    public function destroyItem(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Solo admin puede editar una OC autorizada.'], 403);
            }

            return $redirect;
        }

        $item->delete();
        $percentageMilestonePaymentsSynchronized = $purchaseOrder->recalculateAmount();
        $purchaseOrder->syncDeliveryStatus();

        if ($request->wantsJson()) {
            $purchaseOrder->refresh();
            return response()->json([
                'success'        => true,
                'subtotal'       => $purchaseOrder->subtotal,
                'iva'            => $purchaseOrder->iva,
                'isr_amount'     => $purchaseOrder->isr_amount,
                'retention_iva_amount' => $purchaseOrder->retention_iva_amount,
                'retention_isr_amount' => $purchaseOrder->retention_isr_amount,
                    'cedular_amount' => $purchaseOrder->cedular_amount,
                'total_with_iva' => $purchaseOrder->total_with_iva,
                'amount'         => (float) $purchaseOrder->amount,
                'tax_rate'       => $purchaseOrder->tax_rate,
                'isr_rate'       => $purchaseOrder->isr_rate,
                'retention_iva_rate' => $purchaseOrder->retention_iva_rate,
                'retention_isr_rate' => $purchaseOrder->retention_isr_rate,
                    'cedular_rate'   => $purchaseOrder->cedular_rate,
                'percentage_milestone_payments_synchronized' => $percentageMilestonePaymentsSynchronized,
            ]);
        }

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Concepto eliminado.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // OBSERVACIONES
    // ──────────────────────────────────────────────────────────────────────────

    public function storeObservation(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            return $redirect;
        }

        $data = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $observations   = $purchaseOrder->observations ?? [];
        $observations[] = [
            'user_id'    => Auth::id(),
            'user_name'  => Auth::user()->name,
            'text'       => $data['text'],
            'created_at' => now()->toDateTimeString(),
        ];

        $purchaseOrder->update(['observations' => $observations]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Observación agregada.');
    }

    public function updateObservation(Request $request, PurchaseOrder $purchaseOrder, int $noteIndex): RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            return $redirect;
        }

        $data = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $observations = $purchaseOrder->observations ?? [];

        if (!array_key_exists($noteIndex, $observations)) {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'La observación no existe o ya fue eliminada.');
        }

        $observations[$noteIndex]['text'] = $data['text'];
        $observations[$noteIndex]['updated_at'] = now()->toDateTimeString();

        $purchaseOrder->update(['observations' => $observations]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Observación actualizada.');
    }

    public function destroyObservation(PurchaseOrder $purchaseOrder, int $noteIndex): RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            return $redirect;
        }

        $observations = $purchaseOrder->observations ?? [];

        if (!array_key_exists($noteIndex, $observations)) {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'La observación no existe o ya fue eliminada.');
        }

        unset($observations[$noteIndex]);
        $purchaseOrder->update(['observations' => array_values($observations)]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Observación eliminada.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PDF
    // ──────────────────────────────────────────────────────────────────────────

    public function downloadPdf(PurchaseOrder $purchaseOrder): \Illuminate\Http\Response
    {
        // El PDF incluye condiciones de pago (hitos); el Engineer no accede a él.
        abort_if(Auth::user()?->isRestrictedEngineer(), 403);

        $purchaseOrder->load([
            'supplier.contacts',
            'supplier.locations',
            'mobileAsset',
            'items.concept',
            'milestones',
            'purchaseRequest.materialRequest.requestedBy',
            'purchaseRequest.materialRequests',
        ]);

        $pdf = Pdf::loadView('purchase_orders.pdf', compact('purchaseOrder'))
            ->setPaper('letter', 'portrait');

        $filename = 'OC-' . ($purchaseOrder->folio ?? $purchaseOrder->id) . '.pdf';

        return $pdf->download($filename);
    }

    private function normalizeOptionalRate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function persistAnnex(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderAnnex
    {
        $allowed = '<p><strong><em><u><s><br><ul><ol><li><h1><h2><h3><h4><h5><h6><span><a><blockquote><table><thead><tbody><tr><th><td>';

        return PurchaseOrderAnnex::updateOrCreate(
            ['purchase_order_id' => $purchaseOrder->id],
            [
                'client_name'          => $request->input('client_name', ''),
                'provider_name'        => $request->input('provider_name', ''),
                'annex_condiciones'    => $request->boolean('annex_condiciones'),
                'penalidad_porcentaje' => $request->input('penalidad_porcentaje', ''),
                'penalidad_numero'     => $request->input('penalidad_numero', ''),
                'nombre_aceptacion'    => $request->input('nombre_aceptacion', ''),
                'annex_contrato'       => $request->boolean('annex_contrato'),
                'contrato_html'        => $request->boolean('annex_contrato')
                    ? strip_tags($request->input('contrato_html', ''), $allowed) : null,
                'annex_dossier'        => $request->boolean('annex_dossier'),
                'dossier_html'         => $request->boolean('annex_dossier')
                    ? strip_tags($request->input('dossier_html', ''), $allowed) : null,
            ]
        );
    }

    public function saveAnnex(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->persistAnnex($request, $purchaseOrder);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Configuración de anexos guardada correctamente.');
    }

    public function downloadPdfWithAnnexes(Request $request, PurchaseOrder $purchaseOrder): \Illuminate\Http\Response
    {
        $annex = $this->persistAnnex($request, $purchaseOrder);

        $purchaseOrder->load([
            'supplier',
            'mobileAsset',
            'items.concept',
            'milestones',
            'purchaseRequest.materialRequest.requestedBy',
            'purchaseRequest.materialRequests',
        ]);

        $filename = 'OC-' . ($purchaseOrder->folio ?? $purchaseOrder->id);

        // No annexes: just download the plain OC PDF
        if (!$annex->annex_condiciones && !$annex->annex_contrato && !$annex->annex_dossier) {
            return Pdf::loadView('purchase_orders.pdf', compact('purchaseOrder'))
                ->setPaper('letter', 'portrait')
                ->download($filename . '.pdf');
        }

        // Contrato not selected: single combined render, no merge needed
        if (!$annex->annex_contrato || !$annex->contrato_html) {
            return Pdf::loadView('purchase_orders.pdf_with_annexes', compact('purchaseOrder', 'annex'))
                ->setPaper('letter', 'portrait')
                ->download($filename . '-con-anexos.pdf');
        }

        // Contrato IS selected: generate each section as its own PDF and merge.
        // This is necessary because DomPDF's position:fixed only repeats on all
        // pages when the element is a direct <body> child in an isolated document.
        if (!class_exists(\setasign\Fpdi\Fpdi::class) && !class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            return Pdf::loadView('purchase_orders.pdf_with_annexes', compact('purchaseOrder', 'annex'))
                ->setPaper('letter', 'portrait')
                ->download($filename . '-con-anexos.pdf');
        }

        $tempFiles = [];
        try {
            $parts = [];

            // 1. OC
            $parts[] = $this->saveTempPdf(
                Pdf::loadView('purchase_orders.pdf', compact('purchaseOrder'))->setPaper('letter', 'portrait')->output(),
                $tempFiles
            );

            // 2. Condiciones Generales (if selected)
            if ($annex->annex_condiciones) {
                $parts[] = $this->saveTempPdf(
                    Pdf::loadView('purchase_orders.pdf_annex_condiciones', compact('purchaseOrder', 'annex'))->setPaper('letter', 'portrait')->output(),
                    $tempFiles
                );
            }

            // 3. Contrato with running page header
            $parts[] = $this->saveTempPdf(
                Pdf::loadView('purchase_orders.pdf_annex_contrato', compact('purchaseOrder', 'annex'))->setPaper('letter', 'portrait')->output(),
                $tempFiles
            );

            // 4. Dossier (if selected)
            if ($annex->annex_dossier && $annex->dossier_html) {
                $parts[] = $this->saveTempPdf(
                    Pdf::loadView('purchase_orders.pdf_annex_dossier', compact('purchaseOrder', 'annex'))->setPaper('letter', 'portrait')->output(),
                    $tempFiles
                );
            }

            $merged = $this->mergePdfs($parts);
        } catch (\Throwable $e) {
            return Pdf::loadView('purchase_orders.pdf_with_annexes', compact('purchaseOrder', 'annex'))
                ->setPaper('letter', 'portrait')
                ->download($filename . '-con-anexos.pdf');
        } finally {
            foreach ($tempFiles as $f) { @unlink($f); }
        }

        return response($merged, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '-con-anexos.pdf"',
        ]);
    }

    /** Save a PDF binary string to a temp file and track it for cleanup. */
    private function saveTempPdf(string $pdfString, array &$tempFiles): string
    {
        $path = tempnam(sys_get_temp_dir(), 'oc_pdf_');
        file_put_contents($path, $pdfString);
        $tempFiles[] = $path;
        return $path;
    }

    /** Merge an ordered list of PDF file paths into a single PDF binary string. */
    private function mergePdfs(array $paths): string
    {
        if (class_exists(\setasign\Fpdi\Fpdi::class)) {
            $merger = new \setasign\Fpdi\Fpdi();
        } elseif (class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            $merger = new \setasign\Fpdi\Tcpdf\Fpdi();
        } else {
            throw new \RuntimeException('FPDI is not available.');
        }

        foreach ($paths as $path) {
            $pageCount = $merger->setSourceFile($path);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $merger->importPage($i);
                $size  = $merger->getTemplateSize($tplId);
                $merger->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
                $merger->useTemplate($tplId);
            }
        }
        return $merger->Output('S');
    }
}
