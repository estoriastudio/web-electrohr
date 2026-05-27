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

    public function index(Request $request): View
    {
        $urgentDate = Carbon::now()->addDays(7);
        $search     = trim($request->input('search', ''));

        $payments = Payment::with(['milestone.purchaseOrder.supplier', 'milestone.purchaseOrder.projectRelation', 'milestone.purchaseOrder.workRelation'])
            ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
            ->select('payments.*')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('payments.folio', 'like', '%' . $search . '%')
                        ->orWhere('payments.reference_number', 'like', '%' . $search . '%');
                });
            })
            ->orderByRaw("
                CASE
                    WHEN payments.folio = ? THEN 0
                    WHEN payments.reference_number = ? THEN 0
                    WHEN payments.folio LIKE ? THEN 1
                    WHEN payments.reference_number LIKE ? THEN 1
                    ELSE 2
                END ASC,
                CASE
                    WHEN purchase_order_milestones.due_date <= ? THEN 0
                    ELSE 1
                END ASC,
                purchase_order_milestones.due_date ASC
            ", [
                $search,
                $search,
                $search . '%',
                $search . '%',
                $urgentDate->toDateString(),
            ])
            ->get();

        return view('payments.index', compact('payments', 'urgentDate', 'search'));
    }

    public function create()
    {
        return redirect()->route('payments.index');
    }

    public function interactive(): View
    {
        $urgentDate = Carbon::now()->addDays(7);

        $payments = Payment::with(['milestone.purchaseOrder.supplier', 'milestone.purchaseOrder.projectRelation', 'milestone.purchaseOrder.workRelation'])
            ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
            ->select('payments.*')
            ->where('payments.status', 'por_autorizar')
            ->orderByRaw("
                CASE
                    WHEN purchase_order_milestones.due_date <= ? THEN 0
                    ELSE 1
                END ASC,
                purchase_order_milestones.due_date ASC
            ", [$urgentDate->toDateString()])
            ->get();

        return view('payments.interactive', compact('payments', 'urgentDate'));
    }

    public function swipe(Request $request, Payment $payment): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:autorizado,rechazado,por_autorizar',
        ]);

        $payment->update(['status' => $validated['status']]);

        // Solo notificar si no es un undo (revertir a por_autorizar)
        if ($validated['status'] !== 'por_autorizar') {
            $milestone = $payment->milestone;
            $this->notification->send([
                'type'         => 'Payment',
                'action_by'    => Auth::id(),
                'model_action' => 'update',
                'model_id'     => $payment->id,
                'data'         => $validated['status'] === 'autorizado'
                    ? 'autorizó el pago #' . $payment->folio . ' del hito #' . $milestone->id . ' de la orden de compra #' . $milestone->purchase_order_id
                    : 'rechazó el pago #' . $payment->folio . ' del hito #' . $milestone->id . ' de la orden de compra #' . $milestone->purchase_order_id,
            ]);
        }

        return response()->json([
            'success' => true,
            'status'  => $validated['status'],
        ]);
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

        $saldoPendiente = $milestone->effective_amount - (float) $milestone->covered_amount;

        if ($validated['amount'] > $saldoPendiente) {
            return redirect()->route('purchase_orders.show', $milestone->purchase_order_id)
                ->with('error', 'El monto del pago excede el saldo pendiente del hito (' . number_format($saldoPendiente, 2) . ').');
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
            'status' => 'required|in:por_autorizar,autorizado,pagado,rechazado',
        ]);

        $previousStatus = $payment->status;
        $newStatus      = $validated['status'];

        // Reglas de transición de estatus (no se puede ir "hacia atrás")
        $isAdmin = Auth::user()->hasRole('admin');

        $allowed = match ($previousStatus) {
            'por_autorizar' => ['autorizado', 'rechazado'],
            'autorizado'    => $isAdmin ? ['pagado', 'por_autorizar'] : ['pagado'],
            'pagado'        => [],                                      // pagado es estado final
            'rechazado'     => $isAdmin ? ['por_autorizar'] : [],       // solo admin puede reactivar
            default         => [],
        };

        $milestone = $payment->milestone()->firstOrFail();
        $orderId   = $milestone->purchase_order_id;

        if (!in_array($newStatus, $allowed)) {
            return redirect()->route('purchase_orders.show', $orderId)
                ->with('error', 'Transición de estatus no permitida.');
        }

        $payment->update(['status' => $newStatus]);

        // Ajustar saldo cubierto del hito según transición
        if ($previousStatus !== 'pagado' && $newStatus === 'pagado') {
            $milestone->increment('covered_amount', $payment->amount);
        } elseif ($previousStatus === 'pagado' && $newStatus !== 'pagado') {
            $milestone->decrement('covered_amount', $payment->amount);
        }

        // Notificación
        $this->notification->send([
            'type'         => 'Payment',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $payment->id,
            'data'         => 'actualizó el estatus del pago #' . $payment->folio . ' a «' . $newStatus . '» en el hito #' . $milestone->id . ' de la orden de compra #' . $orderId,
        ]);

        return redirect()->route('purchase_orders.show', $orderId)
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
