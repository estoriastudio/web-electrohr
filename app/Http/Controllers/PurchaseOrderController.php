<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/* Modelos */
use App\Models\MobileAsset;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAnnex;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Project;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/* PDF */
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;

/* Notificaciones */
use App\Services\NotificationService;

class PurchaseOrderController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    private function blockIfAuthorizedAndNotAdmin(PurchaseOrder $purchaseOrder): ?RedirectResponse
    {
        if ($purchaseOrder->status === 'autorizada' && !Auth::user()->hasRole('admin')) {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'Solo admin puede editar una OC autorizada.');
        }

        return null;
    }

    public function index(Request $request): View
    {
        $search  = trim($request->input('search', ''));
        $tipo    = $request->input('tipo', '');
        $sortDue = $request->input('sort_due', '');

        $orders = PurchaseOrder::with(['supplier', 'items'])
            ->withCount(['milestones', 'children'])
            ->whereNull('archived_at')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('supplier', function ($s) use ($search) {
                        $s->where('rfc_name', 'like', '%' . $search . '%')
                          ->orWhere('commercial_name', 'like', '%' . $search . '%');
                    })->orWhere('folio', 'like', '%' . $search . '%');
                });
            })
            ->when($tipo, fn ($q) => $q->where('type', $tipo))
            ->when($sortDue === 'asc', function ($q) {
                $q->withMin('milestones', 'due_date')
                  ->orderByRaw('ISNULL(milestones_min_due_date) ASC')
                  ->orderBy('milestones_min_due_date', 'asc');
            }, function ($q) use ($sortDue) {
                if ($sortDue === 'desc') {
                    $q->withMin('milestones', 'due_date')
                      ->orderByRaw('ISNULL(milestones_min_due_date) ASC')
                      ->orderBy('milestones_min_due_date', 'desc');
                } else {
                    $q->latest();
                }
            })
            ->paginate(25)
            ->withQueryString();

        $suppliers             = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();
        $projects              = Project::where('status', 'active')->orderBy('name')->get();
        $nextFolio             = (PurchaseOrder::withTrashed()->max('folio') ?? 0) + 1;
        $authorizedSignatories = config('purchase_orders.authorized_signatories', []);

        return view('purchase_orders.index', compact(
            'orders', 'suppliers', 'search', 'tipo', 'sortDue',
            'projects', 'nextFolio', 'authorizedSignatories'
        ));
    }

    public function create(): View
    {
        $suppliers             = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();
        $projects              = Project::where('status', 'active')->orderBy('name')->get();
        $mobileAssets          = MobileAsset::where('status', 'active')->orderBy('name')->get();
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
    public function createFromSolcom(PurchaseRequest $purchaseRequest): View
    {
        $purchaseRequest->load(['project', 'projectWork', 'items.concept', 'purchaseOrders:id,purchase_request_id,folio']);

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
            'supplier_id'          => 'required|exists:suppliers,id',
            'currency'             => 'required|in:MXN,USD,EUR',
            'tax_rate'             => 'required|in:0,8,16,exempt',
            'isr_rate'             => 'nullable|numeric|min:0|max:100',
            'retention_iva_rate'   => 'nullable|numeric|min:0|max:100',
            'retention_isr_rate'   => 'nullable|numeric|min:0|max:100',
            'status'               => 'required|in:emitida,pendiente,autorizada',
            'recurrence_type'      => 'required|in:unico,recurrente',
            'purchase_request_id'  => 'nullable|exists:purchase_requests,id',
            'elaborated_by'        => 'nullable|string|max:255',
            'attorney_name'        => 'nullable|string|max:255',
            'supplier_signatory'   => 'nullable|string|max:255',
            'authorized_signatory' => 'nullable|string|max:255',
        ];

        if ($request->type === 'materiales_servicios') {
            $rules['project_id']      = 'nullable|exists:projects,id';
            $rules['project_work_id'] = 'nullable|exists:project_works,id';

            // Si se crea desde SOLCOM, permitir seleccionar qué conceptos heredar
            if ($request->filled('purchase_request_id')) {
                $rules['selected_item_ids'] = ['required', 'array', 'min:1'];
                $rules['selected_item_ids.*'] = [
                    'integer',
                    Rule::exists('purchase_request_items', 'id')->where(function ($q) use ($request) {
                        $q->where('purchase_request_id', (int) $request->input('purchase_request_id'));
                    }),
                ];
            }
        }

        if ($request->type === 'mantenimiento') {
            $rules['mobile_asset_id'] = 'nullable|exists:mobile_assets,id';
        }

        if ($request->recurrence_type === 'recurrente') {
            $rules['recurrence_frequency']  = 'required|in:semanal,quincenal,mensual';
            $rules['recurrence_start_date'] = 'required|date';
            $rules['recurrence_end_date']   = 'nullable|date|after_or_equal:recurrence_start_date';
        }

        // Solo admin puede asignar el estatus «autorizada» directamente
        if (!Auth::user()->hasRole('admin')) {
            $rules['status'] = 'required|in:emitida,pendiente';
        }

        $validated = $request->validate($rules);

        if ($request->type === 'mantenimiento') {
            $validated['project_id']          = null;
            $validated['project_work_id']     = null;
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

        if (empty($validated['elaborated_by'])) {
            $validated['elaborated_by'] = Auth::user()->name;
        }

        // amount siempre parte en 0; se recalculará cuando se agreguen conceptos
        $validated['amount'] = 0;

        unset($validated['folio']);

        $childrenCreated = 0;
        $order = DB::transaction(function () use ($validated, &$childrenCreated) {
            $nextFolio = (PurchaseOrder::withTrashed()->lockForUpdate()->max('folio') ?? 0) + 1;

            $validated['folio'] = $nextFolio;
            $order = PurchaseOrder::create($validated);
            $nextFolio++;

            // Si viene vinculada a una SOLCOM: copiar sus ítems
            if (!empty($validated['purchase_request_id'])) {
                $pr = PurchaseRequest::with('items.concept')->find($validated['purchase_request_id']);

                if ($pr) {
                    $selectedItemIds = collect($validated['selected_item_ids'] ?? [])->map(fn ($id) => (int) $id);
                    $itemsToCopy = $selectedItemIds->isNotEmpty()
                        ? $pr->items->whereIn('id', $selectedItemIds)->values()
                        : $pr->items;

                    foreach ($itemsToCopy as $item) {
                        PurchaseOrderItem::create([
                            'purchase_order_id' => $order->id,
                            'purchase_request_item_id' => $item->id,
                            'concept_id'        => $item->concept_id,
                            'description'       => $item->description,
                            'unit'              => $item->unit,
                            'quantity'          => $item->purchase_quantity,
                            'unit_price'        => $item->concept?->unit_price ?? 0,
                            'delivery_date'     => null,
                        ]);
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

            // Generar órdenes hijas si es recurrente
            if ($order->recurrence_type === 'recurrente'
                && $order->recurrence_start_date
                && $order->recurrence_end_date) {
                $childrenCreated = $this->generateRecurringChildren($order, $nextFolio);
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
        if ($childrenCreated > 0) {
            $successMsg .= ' Se generaron ' . $childrenCreated . ' órdenes individuales de la serie.';
        }

        return redirect()->route('purchase_orders.show', $order)
            ->with('success', $successMsg);
    }

    /**
     * Genera órdenes de compra individuales (hijas) para cada ocurrencia de una serie recurrente.
     * Retorna la cantidad de órdenes generadas.
     */
    private function generateRecurringChildren(PurchaseOrder $parent, int &$nextFolio): int
    {
        $parent->loadMissing('items');

        $current = $parent->recurrence_start_date->copy();
        $end     = $parent->recurrence_end_date->copy();

        $childData = [
            'parent_id'            => $parent->id,
            'type'                 => $parent->type,
            'supplier_id'          => $parent->supplier_id,
            'project_id'           => $parent->project_id,
            'project_work_id'      => $parent->project_work_id,
            'project'              => $parent->project,
            'site'                 => $parent->site,
            'currency'             => $parent->currency,
            'amount'               => $parent->amount,
            'tax_rate'             => $parent->tax_rate,
            'isr_rate'             => $parent->isr_rate,
            'retention_iva_rate'   => $parent->retention_iva_rate,
            'retention_isr_rate'   => $parent->retention_isr_rate,
            'status'               => $parent->status,
            'recurrence_type'      => 'unico',
            'recurrence_frequency' => null,
            'recurrence_end_date'  => null,
            'elaborated_by'        => $parent->elaborated_by,
            'attorney_name'        => $parent->attorney_name,
            'supplier_signatory'   => $parent->supplier_signatory,
            'authorized_signatory' => $parent->authorized_signatory,
        ];

        $count    = 0;
        $maxItems = 60;

        while ($current->lte($end) && $count < $maxItems) {
            $child = PurchaseOrder::create(array_merge($childData, [
                'folio'                 => $nextFolio,
                'recurrence_start_date' => $current->format('Y-m-d'),
            ]));
            $nextFolio++;

            // Copiar ítems al hijo
            foreach ($parent->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $child->id,
                    'purchase_request_item_id' => $item->purchase_request_item_id,
                    'concept_id'        => $item->concept_id,
                    'description'       => $item->description,
                    'unit'              => $item->unit,
                    'quantity'          => $item->quantity,
                    'unit_price'        => $item->unit_price,
                    'delivery_date'     => $item->delivery_date,
                ]);
            }

            match ($parent->recurrence_frequency) {
                'semanal'   => $current->addWeek(),
                'quincenal' => $current->addWeeks(2),
                'mensual'   => $current->addMonth(),
                default     => $current->addMonth(),
            };

            $count++;
        }

        return $count;
    }
    
    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'supplier',
            'milestones.payments',
            'milestones.invoices',
            'invoices.milestones',
            'items.concept',
            'purchaseRequest.purchaseOrders:id,purchase_request_id,folio,created_at',
            'annex',
        ]);

        return view('purchase_orders.show', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            return $redirect;
        }

        $suppliers             = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();
        $projects              = Project::where('status', 'active')->orderBy('name')->get();
        $authorizedSignatories = config('purchase_orders.authorized_signatories', []);

        return view('purchase_orders.edit', compact('purchaseOrder', 'suppliers', 'projects', 'authorizedSignatories'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            return $redirect;
        }

        $rules = [
            'type'                 => 'required|in:materiales_servicios,mantenimiento',
            'supplier_id'          => 'required|exists:suppliers,id',
            'currency'             => 'required|in:MXN,USD,EUR',
            'tax_rate'             => 'required|in:0,8,16,exempt',
            'isr_rate'             => 'nullable|numeric|min:0|max:100',
            'retention_iva_rate'   => 'nullable|numeric|min:0|max:100',
            'retention_isr_rate'   => 'nullable|numeric|min:0|max:100',
            'status'               => 'required|in:emitida,pendiente,autorizada',
            'recurrence_type'      => 'required|in:unico,recurrente',
            'elaborated_by'        => 'nullable|string|max:255',
            'attorney_name'        => 'nullable|string|max:255',
            'supplier_signatory'   => 'nullable|string|max:255',
            'authorized_signatory' => 'nullable|string|max:255',
        ];

        if ($request->type === 'materiales_servicios') {
            $rules['project_id']      = 'nullable|exists:projects,id';
            $rules['project_work_id'] = 'nullable|exists:project_works,id';
        }

        if ($request->type === 'mantenimiento') {
            $rules['mobile_asset_id'] = 'nullable|exists:mobile_assets,id';
        }

        if ($request->recurrence_type === 'recurrente') {
            $rules['recurrence_frequency']  = 'required|in:semanal,quincenal,mensual';
            $rules['recurrence_start_date'] = 'required|date';
            $rules['recurrence_end_date']   = 'nullable|date|after_or_equal:recurrence_start_date';
        }

        $validated = $request->validate($rules);

        if (!Auth::user()->hasRole('admin') && ($validated['status'] ?? '') === 'autorizada') {
            $validated['status'] = 'pendiente';
        }

        // Normalizar tax_rate
        $validated['tax_rate'] = ($validated['tax_rate'] ?? '16') === 'exempt' ? null : (float) ($validated['tax_rate'] ?? 16);
        $validated['isr_rate'] = $this->normalizeOptionalRate($validated['isr_rate'] ?? null);
        $validated['retention_iva_rate'] = $this->normalizeOptionalRate($validated['retention_iva_rate'] ?? null);
        $validated['retention_isr_rate'] = $this->normalizeOptionalRate($validated['retention_isr_rate'] ?? null);

        // Recalcular amount desde los ítems actuales (no viene del form)
        unset($validated['amount']);

        if ($request->type === 'mantenimiento') {
            $validated['project_id']      = null;
            $validated['project_work_id'] = null;
            $validated['project']         = null;
            $validated['site']            = null;
        } else {
            $validated['mobile_asset_id'] = null;
        }

        if ($request->recurrence_type === 'unico') {
            $validated['recurrence_frequency']  = null;
            $validated['recurrence_start_date'] = null;
            $validated['recurrence_end_date']   = null;
        }

        $purchaseOrder->update($validated);

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

    public function approve(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status === 'autorizada') {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'La orden de compra ya está autorizada.');
        }

        $purchaseOrder->update(['status' => 'autorizada']);

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

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($redirect = $this->blockIfAuthorizedAndNotAdmin($purchaseOrder)) {
            return $redirect;
        }

        $folio        = $purchaseOrder->folio ?? $purchaseOrder->id;
        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        $purchaseOrder->delete();

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => 0,
            'data'         => 'eliminó la orden de compra #' . $folio . ' de ' . $supplierName . '.',
        ]);

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
        $purchaseOrder->update(['archived_at' => null]);

        $folio = $purchaseOrder->folio ?? $purchaseOrder->id;

        return redirect()->route('purchase_orders.archived')
            ->with('success', 'Orden de compra #' . $folio . ' restaurada al listado activo.');
    }

    public function archived(Request $request): View
    {
        $search = trim($request->input('search', ''));

        $orders = PurchaseOrder::with(['supplier', 'items'])
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

        return view('purchase_orders.archive', compact('orders', 'search'));
    }

    public function softDeleted(Request $request): View
    {
        $search = trim($request->input('search', ''));

        $orders = PurchaseOrder::onlyTrashed()
            ->with(['supplier'])
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
        $purchaseOrder->restore();

        $folio = $purchaseOrder->folio ?? $purchaseOrder->id;

        return redirect()->route('purchase_orders.soft_deleted')
            ->with('success', 'Orden de compra #' . $folio . ' restaurada.');
    }

    public function forceDestroy(int $id): RedirectResponse
    {
        $purchaseOrder = PurchaseOrder::onlyTrashed()->findOrFail($id);

        $folio        = $purchaseOrder->folio ?? $purchaseOrder->id;
        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        $purchaseOrder->forceDelete();

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'force_destroy',
            'model_id'     => 0,
            'data'         => 'eliminó permanentemente la orden de compra #' . $folio . ' de ' . $supplierName . '.',
        ]);

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

        $data = $request->validate([
            'concept_id'    => 'nullable|exists:concepts,id',
            'description'   => 'required|string|max:500',
            'unit'          => 'required|string|max:50',
            'quantity'      => 'required|numeric|min:0.01',
            'unit_price'    => 'required|numeric|min:0',
            'delivery_date' => 'nullable|string|max:100',
        ]);

        $data['purchase_order_id'] = $purchaseOrder->id;
        $item = PurchaseOrderItem::create($data);

        $purchaseOrder->recalculateAmount();
        $purchaseOrder->refresh();

        if ($request->wantsJson()) {
            return response()->json([
                'id'             => $item->id,
                'description'    => $item->description,
                'unit'           => $item->unit,
                'quantity'       => (float) $item->quantity,
                'unit_price'     => (float) $item->unit_price,
                'total'          => $item->total,
                'delivery_date'  => $item->delivery_date,
                'subtotal'       => $purchaseOrder->subtotal,
                'iva'            => $purchaseOrder->iva,
                'isr_amount'     => $purchaseOrder->isr_amount,
                'retention_iva_amount' => $purchaseOrder->retention_iva_amount,
                'retention_isr_amount' => $purchaseOrder->retention_isr_amount,
                'total_with_iva' => $purchaseOrder->total_with_iva,
                'amount'         => (float) $purchaseOrder->amount,
                'tax_rate'       => $purchaseOrder->tax_rate,
                'isr_rate'       => $purchaseOrder->isr_rate,
                'retention_iva_rate' => $purchaseOrder->retention_iva_rate,
                'retention_isr_rate' => $purchaseOrder->retention_isr_rate,
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
            'quantity'      => 'sometimes|numeric|min:0.01',
            'unit_price'    => 'sometimes|numeric|min:0',
            'delivery_date' => 'nullable|string|max:100',
        ]);

        $item->update($data);

        $purchaseOrder->recalculateAmount();
        $purchaseOrder->refresh();

        if ($request->wantsJson()) {
            return response()->json([
                'id'             => $item->id,
                'quantity'       => (float) $item->quantity,
                'unit_price'     => (float) $item->unit_price,
                'total'          => $item->total,
                'delivery_date'  => $item->delivery_date,
                'subtotal'       => $purchaseOrder->subtotal,
                'iva'            => $purchaseOrder->iva,
                'isr_amount'     => $purchaseOrder->isr_amount,
                'retention_iva_amount' => $purchaseOrder->retention_iva_amount,
                'retention_isr_amount' => $purchaseOrder->retention_isr_amount,
                'total_with_iva' => $purchaseOrder->total_with_iva,
                'amount'         => (float) $purchaseOrder->amount,
                'tax_rate'       => $purchaseOrder->tax_rate,
                'isr_rate'       => $purchaseOrder->isr_rate,
                'retention_iva_rate' => $purchaseOrder->retention_iva_rate,
                'retention_isr_rate' => $purchaseOrder->retention_isr_rate,
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
        $purchaseOrder->recalculateAmount();

        if ($request->wantsJson()) {
            $purchaseOrder->refresh();
            return response()->json([
                'success'        => true,
                'subtotal'       => $purchaseOrder->subtotal,
                'iva'            => $purchaseOrder->iva,
                'isr_amount'     => $purchaseOrder->isr_amount,
                'retention_iva_amount' => $purchaseOrder->retention_iva_amount,
                'retention_isr_amount' => $purchaseOrder->retention_isr_amount,
                'total_with_iva' => $purchaseOrder->total_with_iva,
                'amount'         => (float) $purchaseOrder->amount,
                'tax_rate'       => $purchaseOrder->tax_rate,
                'isr_rate'       => $purchaseOrder->isr_rate,
                'retention_iva_rate' => $purchaseOrder->retention_iva_rate,
                'retention_isr_rate' => $purchaseOrder->retention_isr_rate,
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
        $purchaseOrder->load([
            'supplier',
            'items.concept',
            'milestones',
            'purchaseRequest.materialRequest.requestedBy',
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
            'items.concept',
            'milestones',
            'purchaseRequest.materialRequest.requestedBy',
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
        $merger = new Fpdi();
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
