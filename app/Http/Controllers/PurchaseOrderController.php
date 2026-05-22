<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/* Modelos */
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Project;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/* Notificaciones */
use App\Services\NotificationService;

class PurchaseOrderController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index(Request $request): View
    {
        $search  = trim($request->input('search', ''));
        $tipo    = $request->input('tipo', '');
        $sortDue = $request->input('sort_due', '');

        $orders = PurchaseOrder::with('supplier')
            ->withCount(['milestones', 'children'])
            ->when($search, function ($q) use ($search) {
                $q->whereHas('supplier', function ($sub) use ($search) {
                    $sub->where('rfc_name', 'like', '%' . $search . '%')
                        ->orWhere('commercial_name', 'like', '%' . $search . '%');
                });
            })
            ->when($tipo, fn ($q) => $q->where('type', $tipo))
            ->when($sortDue === 'asc', function ($q) {
                // Ordena por el vencimiento más próximo de sus hitos
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

        $suppliers = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();
        $projects  = Project::where('status', 'active')->orderBy('name')->get();

        return view('purchase_orders.index', compact('orders', 'suppliers', 'search', 'tipo', 'sortDue', 'projects'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('purchase_orders.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'type'             => 'required|in:materiales_servicios,mantenimiento',
            'supplier_id'      => 'required|exists:suppliers,id',
            'currency'         => 'required|in:MXN,USD,EUR',
            'amount'           => 'required|numeric|min:0',
            'status'           => 'required|in:emitida,pendiente,autorizada',
            'recurrence_type'  => 'required|in:unico,recurrente',
        ];

        if ($request->type === 'materiales_servicios') {
            $rules['project_id']      = 'nullable|exists:projects,id';
            $rules['project_work_id'] = 'nullable|exists:project_works,id';
        }

        if ($request->recurrence_type === 'recurrente') {
            $rules['recurrence_frequency']   = 'required|in:semanal,quincenal,mensual';
            $rules['recurrence_start_date']  = 'required|date';
            $rules['recurrence_end_date']    = 'nullable|date|after_or_equal:recurrence_start_date';
        }

        // Solo admin puede asignar el estatus «autorizada» directamente
        if (!Auth::user()->hasRole('admin')) {
            $rules['status'] = 'required|in:emitida,pendiente';
        }

        $validated = $request->validate($rules);

        if ($request->type === 'mantenimiento') {
            $validated['project_id']      = null;
            $validated['project_work_id'] = null;
        }

        $order = PurchaseOrder::create($validated);

        // Generar órdenes hijas si es recurrente y tiene rango de fechas
        $childrenCreated = 0;
        if ($order->recurrence_type === 'recurrente'
            && $order->recurrence_start_date
            && $order->recurrence_end_date) {
            $childrenCreated = $this->generateRecurringChildren($order);
        }

        // Notificación
        $supplierName = $order->supplier->rfc_name ?? $order->supplier->commercial_name ?? 'Proveedor desconocido';

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $order->id,
            'data'         => 'creó una nueva orden de compra para ' . $supplierName . '.',
        ]);

        $successMsg = 'Orden de compra creada correctamente.';
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
            'parent_id'             => $parent->id,
            'type'                  => $parent->type,
            'supplier_id'           => $parent->supplier_id,
            'project_id'            => $parent->project_id,
            'project_work_id'       => $parent->project_work_id,
            'project'               => $parent->project,
            'site'                  => $parent->site,
            'currency'              => $parent->currency,
            'amount'                => $parent->amount,
            'status'                => $parent->status,
            'recurrence_type'       => 'unico',
            'recurrence_frequency'  => null,
            'recurrence_end_date'   => null,
        ];

        $count    = 0;
        $maxItems = 60; // límite de seguridad

        while ($current->lte($end) && $count < $maxItems) {
            PurchaseOrder::create(array_merge($childData, [
                'recurrence_start_date' => $current->format('Y-m-d'),
            ]));

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
        $purchaseOrder->load(['supplier', 'milestones.payments', 'milestones.invoices', 'invoices.milestones']);

        return view('purchase_orders.show', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $suppliers = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();
        $projects  = Project::where('status', 'active')->orderBy('name')->get();

        return view('purchase_orders.edit', compact('purchaseOrder', 'suppliers', 'projects'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $rules = [
            'type'             => 'required|in:materiales_servicios,mantenimiento',
            'supplier_id'      => 'required|exists:suppliers,id',
            'currency'         => 'required|in:MXN,USD,EUR',
            'amount'           => 'required|numeric|min:0',
            'status'           => 'required|in:emitida,pendiente,autorizada',
            'recurrence_type'  => 'required|in:unico,recurrente',
        ];

        if ($request->type === 'materiales_servicios') {
            $rules['project_id']      = 'nullable|exists:projects,id';
            $rules['project_work_id'] = 'nullable|exists:project_works,id';
        }

        if ($request->recurrence_type === 'recurrente') {
            $rules['recurrence_frequency']   = 'required|in:semanal,quincenal,mensual';
            $rules['recurrence_start_date']  = 'required|date';
            $rules['recurrence_end_date']    = 'nullable|date|after_or_equal:recurrence_start_date';
        }

        $validated = $request->validate($rules);

        // Solo admin puede asignar el estatus «autorizada» directamente
        if (!Auth::user()->hasRole('admin') && ($validated['status'] ?? '') === 'autorizada') {
            $validated['status'] = 'pendiente';
        }

        if ($request->type === 'mantenimiento') {
            $validated['project_id']      = null;
            $validated['project_work_id'] = null;
            $validated['project']         = null;
            $validated['site']            = null;
        }

        if ($request->recurrence_type === 'unico') {
            $validated['recurrence_frequency']  = null;
            $validated['recurrence_start_date'] = null;
            $validated['recurrence_end_date']   = null;
        }

        // Proteger importe si la OC ya tiene hitos configurados
        if ($purchaseOrder->milestones()->count() > 0 &&
            (float) $request->input('amount') !== (float) $purchaseOrder->amount) {
            return redirect()->back()->withInput()
                ->withErrors(['amount' => 'No se puede modificar el importe de una orden de compra que ya tiene hitos configurados.']);
        }

        $purchaseOrder->update($validated);

        // Notificación
        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'actualizó la orden de compra #' . $purchaseOrder->id . ' de ' . $supplierName . '.',
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
            'data'         => 'autorizó la orden de compra #' . $purchaseOrder->id . ' de ' . $supplierName . '.',
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Orden de compra #' . $purchaseOrder->id . ' autorizada correctamente.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->delete();

        // Notificación
        $supplierName = $purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? 'Proveedor desconocido';

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'eliminó la orden de compra #' . $purchaseOrder->id . ' de ' . $supplierName . '.',
        ]);

        return redirect()->route('purchase_orders.index')
            ->with('success', 'Orden de compra eliminada.');
    }
}
