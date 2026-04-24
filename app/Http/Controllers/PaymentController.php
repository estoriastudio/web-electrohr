<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/* Modelos */
use App\Models\Payment;
use App\Models\PurchaseOrderMilestone;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

use Carbon\Carbon;

/* Notificaciones */
use App\Services\NotificationService;

class PaymentController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index(): View
    {
        $urgentDate = Carbon::now()->addDays(7);

        $payments = Payment::with(['milestone.purchaseOrder.supplier'])
            ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
            ->select('payments.*')
            ->orderByRaw("
                CASE
                    WHEN purchase_order_milestones.fecha_vencimiento <= ? THEN 0
                    ELSE 1
                END ASC,
                purchase_order_milestones.fecha_vencimiento ASC
            ", [$urgentDate->toDateString()])
            ->get();

        return view('payments.index', compact('payments', 'urgentDate'));
    }

    public function create()
    {
        return redirect()->route('payments.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'milestone_id'     => 'required|exists:purchase_order_milestones,id',
            'folio'            => 'nullable|string|max:100',
            'amount'           => 'required|numeric|min:0.01',
            'payment_date'     => 'required|date',
            'status'           => 'required|in:por_autorizar,autorizado,pagado',
            'reference_number' => 'nullable|string|max:255',
        ]);

        $milestone = PurchaseOrderMilestone::findOrFail($validated['milestone_id']);

        if ($milestone->is_complete) {
            return redirect()->route('purchase_orders.show', $milestone->purchase_order_id)
                ->with('error', 'El hito ya está completamente cubierto. No se pueden agregar más pagos.');
        }

        if (empty($validated['folio'])) {
            $validated['folio'] = strtoupper('PAY-' . random_int(10000, 99999));
        }

        $payment = Payment::create($validated);

        if ($payment->status === 'pagado') {
            $milestone->increment('covered_amount', $payment->amount);
        }

        // Notificación
        $this->notification->send([
            'type'         => 'Payment',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $payment->id,
            'data'         => 'creó un nuevo pago en el hito #' . $milestone->id . ' de la orden de compra #' . $milestone->purchase_order_id,
        ]);

        return redirect()->route('purchase_orders.show', $milestone->purchase_order_id)
            ->with('success', 'Pago registrado correctamente.');
    }

    public function show(Payment $payment)
    {
        return redirect()->route('purchase_orders.show', $payment->milestone->purchase_order_id);
    }

    public function edit(Payment $payment)
    {
        return redirect()->route('purchase_orders.show', $payment->milestone->purchase_order_id);
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:por_autorizar,autorizado,pagado',
        ]);

        $previousStatus = $payment->status;
        $payment->update($validated);

        $milestone = $payment->milestone;

        if ($previousStatus !== 'pagado' && $validated['status'] === 'pagado') {
            $milestone->increment('covered_amount', $payment->amount);
        } elseif ($previousStatus === 'pagado' && $validated['status'] !== 'pagado') {
            $milestone->decrement('covered_amount', $payment->amount);
        }

        // Notificación
        $this->notification->send([
            'type'         => 'Payment',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $payment->id,
            'data'         => 'actualizó el estatus del pago en el hito #' . $milestone->id . ' de la orden de compra #' . $milestone->purchase_order_id,
        ]);
        
        return redirect()->route('payments.index')
            ->with('success', 'Estatus del pago actualizado.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $milestone = $payment->milestone;
        $orderId   = $milestone->purchase_order_id;

        if ($payment->status === 'pagado') {
            $milestone->decrement('covered_amount', $payment->amount);
        }

        $payment->delete();

        // Notificación
        $this->notification->send([
            'type'         => 'Payment',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $payment->id,
            'data'         => 'eliminó un pago en el hito #' . $milestone->id . ' de la orden de compra #' . $milestone->purchase_order_id,
        ]);

        return redirect()->route('purchase_orders.show', $orderId)
            ->with('success', 'Pago eliminado.');
    }
}
