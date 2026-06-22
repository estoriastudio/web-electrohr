<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/* Modelos */
use App\Models\MobileAsset;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Project;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/* PDF */
use Barryvdh\DomPDF\Facade\Pdf;

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
        $nextFolio             = (PurchaseOrder::max('folio') ?? 0) + 1;
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
        $nextFolio             = (PurchaseOrder::max('folio') ?? 0) + 1;
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
        $purchaseRequest->load(['project', 'projectWork', 'items.concept']);

        $suppliers             = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();
        $projects              = Project::where('status', 'active')->orderBy('name')->get();
        $nextFolio             = (PurchaseOrder::max('folio') ?? 0) + 1;
        $authorizedSignatories = config('purchase_orders.authorized_signatories', []);

        return view('purchase_orders.create_from_solcom', compact(
            'purchaseRequest', 'suppliers', 'projects', 'nextFolio', 'authorizedSignatories'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'folio'                => 'required|integer|unique:purchase_orders,folio',
            'type'                 => 'required|in:materiales_servicios,mantenimiento',
            'supplier_id'          => 'required|exists:suppliers,id',
            'currency'             => 'required|in:MXN,USD,EUR',
            'tax_rate'             => 'required|in:0,8,16,exempt',
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
        } else {
            $validated['mobile_asset_id'] = null;
        }

        // Normalizar tax_rate: 'exempt' → 0 para almacenar, flag aparte no needed (0% y exento son 0 en cálculo)
        // Guardamos el valor como decimal: exempt se almacena como null para distinguirlo visualmente
        $validated['tax_rate'] = $validated['tax_rate'] === 'exempt' ? null : (float) $validated['tax_rate'];

        if (empty($validated['elaborated_by'])) {
            $validated['elaborated_by'] = Auth::user()->name;
        }

        // amount siempre parte en 0; se recalculará cuando se agreguen conceptos
        $validated['amount'] = 0;

        $order = PurchaseOrder::create($validated);

        // Si viene vinculada a una SOLCOM: copiar sus ítems
        if (!empty($validated['purchase_request_id'])) {
            $pr = PurchaseRequest::with('items.concept')->find($validated['purchase_request_id']);

            if ($pr) {
                foreach ($pr->items as $item) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id,
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

                // Marcar la SOLCOM como completada para que salga de la Pila SOLCOM
                $pr->update(['status' => 'completed']);
            }
        }

        // Generar órdenes hijas si es recurrente
        $childrenCreated = 0;
        if ($order->recurrence_type === 'recurrente'
            && $order->recurrence_start_date
            && $order->recurrence_end_date) {
            $childrenCreated = $this->generateRecurringChildren($order);
        }

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
    private function generateRecurringChildren(PurchaseOrder $parent): int
    {
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
                'folio'                 => (PurchaseOrder::max('folio') ?? 0) + 1,
                'recurrence_start_date' => $current->format('Y-m-d'),
            ]));

            // Copiar ítems al hijo
            foreach ($parent->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $child->id,
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
            'purchaseRequest',
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
                'total_with_iva' => $purchaseOrder->total_with_iva,
                'amount'         => (float) $purchaseOrder->amount,
                'tax_rate'       => $purchaseOrder->tax_rate,
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
                'total_with_iva' => $purchaseOrder->total_with_iva,
                'amount'         => (float) $purchaseOrder->amount,
                'tax_rate'       => $purchaseOrder->tax_rate,
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
                'total_with_iva' => $purchaseOrder->total_with_iva,
                'amount'         => (float) $purchaseOrder->amount,
                'tax_rate'       => $purchaseOrder->tax_rate,
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
}
