<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/* Modelos */
use App\Models\PurchaseOrderMilestone;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

/* Notificaciones */
use App\Services\NotificationService;

class PurchaseOrderMilestoneController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index()
    {
        return redirect()->route('purchase_orders.index');
    }

    public function create()
    {
        return redirect()->route('purchase_orders.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'type'              => 'required|in:anticipo,regular',
            'value_type'        => 'required|in:fijo,porcentaje',
            'value'             => 'required|numeric|min:0.01',
            'invoice_date'      => 'nullable|date',
            'due_date'          => 'nullable|date',
        ]);

        $validated['covered_amount'] = 0;

        $order = PurchaseOrderMilestone::create($validated);

        // Notificación
        $this->notification->send([
            'type'         => 'PurchaseOrderMilestone',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $order->id,
            'data'         => 'creó un nuevo hito de en la orden de compra #' . $order->id,
        ]);

        return redirect()->route('purchase_orders.show', $request->purchase_order_id)
            ->with('success', 'Hito creado correctamente.');
    }

    public function show(PurchaseOrderMilestone $purchaseOrderMilestone)
    {
        return redirect()->route('purchase_orders.show', $purchaseOrderMilestone->purchase_order_id);
    }

    public function edit(PurchaseOrderMilestone $purchaseOrderMilestone)
    {
        return redirect()->route('purchase_orders.show', $purchaseOrderMilestone->purchase_order_id);
    }

    public function update(Request $request, PurchaseOrderMilestone $purchaseOrderMilestone): RedirectResponse
    {
        if ($purchaseOrderMilestone->payments()->count() > 0) {
            return redirect()->route('purchase_orders.show', $purchaseOrderMilestone->purchase_order_id)
                ->with('error', 'No se puede editar un hito que ya tiene pagos registrados.');
        }

        $validated = $request->validate([
            'type'         => 'required|in:anticipo,regular',
            'value_type'   => 'required|in:fijo,porcentaje',
            'value'        => 'required|numeric|min:0.01',
            'invoice_date' => 'nullable|date',
            'due_date'     => 'nullable|date',
        ]);

        $purchaseOrderMilestone->update($validated);

        // Notificación
        $this->notification->send([
            'type'         => 'PurchaseOrderMilestone',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrderMilestone->id,
            'data'         => 'actualizó un hito de la orden de compra #' . $purchaseOrderMilestone->purchase_order_id,
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrderMilestone->purchase_order_id)
            ->with('success', 'Hito actualizado correctamente.');
    }

    public function destroy(PurchaseOrderMilestone $purchaseOrderMilestone): RedirectResponse
    {
        $orderId = $purchaseOrderMilestone->purchase_order_id;

        if ($purchaseOrderMilestone->payments()->count() > 0) {
            return redirect()->route('purchase_orders.show', $orderId)
                ->with('error', 'No se puede eliminar un hito que ya tiene pagos registrados.');
        }

        $purchaseOrderMilestone->delete();

        // Notificación
        $this->notification->send([
            'type'         => 'PurchaseOrderMilestone',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $purchaseOrderMilestone->id,
            'data'         => 'eliminó un hito de la orden de compra #' . $purchaseOrderMilestone->purchase_order_id,
        ]);

        return redirect()->route('purchase_orders.show', $orderId)
            ->with('success', 'Hito eliminado.');
    }
}
