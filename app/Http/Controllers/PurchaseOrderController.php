<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/* Modelos */
use App\Models\PurchaseOrder;
use App\Models\Supplier;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/* Notificaciones */
use App\Services\NotificationService;

class PurchaseOrderController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index(): View
    {
        $orders = PurchaseOrder::with('supplier')
            ->withCount('milestones')
            ->latest()
            ->paginate(25);

        $suppliers = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();

        return view('purchase_orders.index', compact('orders', 'suppliers'));
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
            $rules['project'] = 'nullable|string|max:255';
            $rules['site']    = 'nullable|string|max:255';
        }

        if ($request->recurrence_type === 'recurrente') {
            $rules['recurrence_frequency']   = 'required|in:semanal,quincenal,mensual';
            $rules['recurrence_start_date']  = 'required|date';
            $rules['recurrence_end_date']    = 'nullable|date|after_or_equal:recurrence_start_date';
        }

        $validated = $request->validate($rules);

        $order = PurchaseOrder::create($validated);

        // Notificación
        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $order->id,
            'data'         => 'creó una nueva orden de compra.',
        ]);

        return redirect()->route('purchase_orders.show', $order)
            ->with('success', 'Orden de compra creada correctamente.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'milestones.payments']);

        return view('purchase_orders.show', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $suppliers = Supplier::orderBy('rfc_name')->orderBy('commercial_name')->get();

        return view('purchase_orders.edit', compact('purchaseOrder', 'suppliers'));
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
            $rules['project'] = 'nullable|string|max:255';
            $rules['site']    = 'nullable|string|max:255';
        }

        if ($request->recurrence_type === 'recurrente') {
            $rules['recurrence_frequency']   = 'required|in:semanal,quincenal,mensual';
            $rules['recurrence_start_date']  = 'required|date';
            $rules['recurrence_end_date']    = 'nullable|date|after_or_equal:recurrence_start_date';
        }

        $validated = $request->validate($rules);

        if ($request->type === 'mantenimiento') {
            $validated['project'] = null;
            $validated['site']    = null;
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
        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'actualizó la información de la orden de compra #' . $purchaseOrder->id,
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Orden de compra actualizada correctamente.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->delete();

        // Notificación
        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'eliminó la orden de compra #' . $purchaseOrder->id,
        ]);

        return redirect()->route('purchase_orders.index')
            ->with('success', 'Orden de compra eliminada.');
    }
}
