<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/* Modelos */
use App\Models\PurchaseOrderMilestone;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/* Notificaciones */
use App\Services\NotificationService;

class PurchaseOrderMilestoneController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    /**
     * Listado global de hitos con semáforo de urgencia.
     *
     * Lógica del semáforo (evaluada en la vista por prioridad descendente):
     *
     *  NEGRO   — El hito está COMPLETO (covered_amount >= monto objetivo) pero NO
     *            tiene ninguna factura vinculada en la tabla pivot `invoice_milestone`.
     *            Indica que el hito se liquidó sin respaldo documental.
     *
     *  ROJO    — El hito NO está completo y su `due_date` ya es anterior a hoy.
     *            El pago está vencido.
     *
     *  AMARILLO — El hito NO está completo y su `due_date` cae dentro de los próximos
     *             N días (actualmente 7, controlado por $urgentDate).
     *             Para cambiar el umbral basta con ajustar addDays() abajo.
     *
     *  VERDE   — Cualquier otro caso: hito al día, sin vencimiento próximo o sin
     *            fecha de vencimiento registrada.
     *
     * Ordenamiento: los hitos con due_date más cercana aparecen primero;
     * los que no tienen due_date se muestran al final.
     */
    public function index()
    {
        $milestones = PurchaseOrderMilestone::with([
                'purchaseOrder.supplier', // para mostrar proveedor sin N+1
                'invoices',               // para detectar semáforo negro
                'payments',               // disponible en vista para desglose futuro
            ])
            ->orderByRaw('due_date IS NULL ASC') // nulos al final
            ->orderBy('due_date', 'asc')
            ->get();

        // Umbral de urgencia: hitos que vencen dentro de este número de días
        // se marcan en AMARILLO. Cambiar el valor para ajustar la regla.
        $urgentDate = Carbon::today()->addDays(7);

        return view('milestones.index', compact('milestones', 'urgentDate'));
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
            'data'         => 'creó un nuevo hito en la orden de compra #' . $order->purchase_order_id,
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
        $milestoneId = $purchaseOrderMilestone->id;
        $orderId     = $purchaseOrderMilestone->purchase_order_id;

        if ($purchaseOrderMilestone->payments()->count() > 0) {
            return redirect()->route('purchase_orders.show', $orderId)
                ->with('error', 'No se puede eliminar un hito que ya tiene pagos registrados.');
        }

        $purchaseOrderMilestone->delete();

        // Notificación (después del delete para evitar que cascadas limpien el modelo)
        $this->notification->send([
            'type'         => 'PurchaseOrderMilestone',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $milestoneId,
            'data'         => 'eliminó un hito de la orden de compra #' . $orderId,
        ]);

        return redirect()->route('purchase_orders.show', $orderId)
            ->with('success', 'Hito eliminado.');
    }
}
