<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/* Modelos */
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/* Notificaciones */
use App\Services\NotificationService;
use App\Services\PaymentFolioGenerator;

class PurchaseOrderMilestoneController extends Controller
{
    public function __construct(
        private NotificationService $notification,
        private PaymentFolioGenerator $paymentFolioGenerator,
    ) {}

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
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));

        $milestones = PurchaseOrderMilestone::with([
                'purchaseOrder.supplier', // para mostrar proveedor sin N+1
                'invoices',               // para detectar semáforo negro
                'payments',               // disponible en vista para desglose futuro
            ])
            ->has('purchaseOrder')
            ->when($search, function ($query) use ($search) {
                $query->whereHas('purchaseOrder', function ($orderQuery) use ($search) {
                    $orderQuery->where('folio', 'like', '%' . $search . '%')
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                            $supplierQuery->where('rfc_name', 'like', '%' . $search . '%')
                                ->orWhere('commercial_name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderByRaw('due_date IS NULL ASC') // nulos al final
            ->orderBy('due_date', 'asc')
            ->get();

        // Umbral de urgencia: hitos que vencen dentro de este número de días
        // se marcan en AMARILLO. Cambiar el valor para ajustar la regla.
        $urgentDate = Carbon::today()->addDays(7);

        return view('milestones.index', compact('milestones', 'urgentDate', 'search'));
    }

    public function create()
    {
        return redirect()->route('purchase_orders.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'concept'           => 'nullable|string|max:255',
            'payment_condition' => 'required|in:contado,credito',
            'is_advance'        => 'boolean',
            'value_type'        => 'required|in:fijo,porcentaje',
            'value'             => 'required|numeric|min:0.01',
            'invoice_date'      => 'nullable|date',
            'due_date'          => 'required|date',
        ]);

        $purchaseOrder = PurchaseOrder::findOrFail((int) $validated['purchase_order_id']);
        if ($purchaseOrder->status === 'autorizada'
            && !$purchaseOrder->is_destajo
            && !Auth::user()->hasRole('admin')) {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'Solo admin puede agregar hitos a una OC autorizada que no es destajo.');
        }

        $validated['covered_amount'] = 0;
        $validated['is_advance']     = $request->boolean('is_advance');
        $validated['type']           = 'regular'; // campo legacy, valor fijo

        $order = DB::transaction(function () use ($validated) {
            $lockedPurchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail((int) $validated['purchase_order_id']);

            $this->ensureMilestoneValueIsWithinOrderTotal(
                $lockedPurchaseOrder,
                (string) $validated['value_type'],
                (float) $validated['value'],
            );

            $milestone = PurchaseOrderMilestone::create($validated);
            $milestone->load('purchaseOrder');

            Payment::create([
                'milestone_id'     => $milestone->id,
                'folio'            => $this->paymentFolioGenerator->generate(),
                'amount'           => $milestone->effective_amount,
                'payment_date'     => $milestone->due_date,
                'invoice_date'     => null,
                'status'           => 'por_autorizar',
                'reference_number' => null,
            ]);

            return $milestone;
        });

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
        $validated = $request->validate([
            'concept'           => 'nullable|string|max:255',
            'payment_condition' => 'required|in:contado,credito',
            'is_advance'        => 'boolean',
            'value_type'        => 'required|in:fijo,porcentaje',
            'value'             => 'required|numeric|min:0.01',
            'invoice_date'      => 'nullable|date',
            'due_date'          => 'required|date',
        ]);

        $purchaseOrder = $purchaseOrderMilestone->purchaseOrder;
        if ($purchaseOrder?->status === 'autorizada'
            && !$purchaseOrder->is_destajo
            && !Auth::user()->hasRole('admin')) {
            return redirect()->route('purchase_orders.show', $purchaseOrderMilestone->purchase_order_id)
                ->with('error', 'Solo admin puede editar hitos de una OC autorizada que no es destajo.');
        }

        $this->ensureMilestoneValueIsWithinOrderTotal(
            $purchaseOrder,
            (string) $validated['value_type'],
            (float) $validated['value'],
            $purchaseOrderMilestone,
        );

        $validated['is_advance'] = $request->boolean('is_advance');
        $validated['type']       = $purchaseOrderMilestone->type; // preservar valor legacy existente

        // Capturar valores anteriores para el log de auditoría
        $before = [
            'type'              => $purchaseOrderMilestone->type,
            'payment_condition' => $purchaseOrderMilestone->payment_condition,
            'is_advance'        => $purchaseOrderMilestone->is_advance,
            'value_type'        => $purchaseOrderMilestone->value_type,
            'value'             => (float) $purchaseOrderMilestone->value,
            'due_date'          => $purchaseOrderMilestone->due_date?->format('d/m/Y'),
        ];

        DB::transaction(function () use ($purchaseOrderMilestone, $validated): void {
            $payments = $purchaseOrderMilestone->payments()
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $purchaseOrderMilestone->update($validated);
            $purchaseOrderMilestone->refresh()->load('purchaseOrder');

            $targetAmount = $purchaseOrderMilestone->effective_amount;

            if ($payments->isEmpty()) {
                Payment::create([
                    'milestone_id'     => $purchaseOrderMilestone->id,
                    'folio'            => $this->paymentFolioGenerator->generate(),
                    'amount'           => $targetAmount,
                    'payment_date'     => $purchaseOrderMilestone->due_date,
                    'invoice_date'     => null,
                    'status'           => 'por_autorizar',
                    'reference_number' => null,
                ]);

                return;
            }

            $pendingPayments = $payments->where('status', 'por_autorizar')->values();
            $lockedAmount = round((float) $payments
                ->where('status', '!=', 'por_autorizar')
                ->sum('amount'), 2);

            if ($pendingPayments->isEmpty()) {
                if (abs($targetAmount - $lockedAmount) > 0.009) {
                    throw ValidationException::withMessages([
                        'value' => 'No se puede modificar el monto del hito porque no tiene pagos por autorizar.',
                    ]);
                }

                return;
            }

            $pendingAmount = round($targetAmount - $lockedAmount, 2);
            if ($pendingAmount < 0) {
                throw ValidationException::withMessages([
                    'value' => 'El monto del hito no puede ser menor que los pagos ya autorizados, pagados o bloqueados ('
                        . number_format($lockedAmount, 2, '.', ',') . ').',
                ]);
            }

            $currentPendingAmount = (float) $pendingPayments->sum('amount');
            $remainingAmount = $pendingAmount;

            foreach ($pendingPayments as $index => $payment) {
                $isLastPayment = $index === $pendingPayments->count() - 1;
                $amount = $isLastPayment
                    ? $remainingAmount
                    : round($pendingAmount * ((float) $payment->amount / $currentPendingAmount), 2);

                $remainingAmount = round($remainingAmount - $amount, 2);

                $payment->update([
                    'amount'       => $amount,
                    'payment_date' => $purchaseOrderMilestone->due_date,
                ]);
            }
        });

        // Construir resumen de cambios para auditoría
        $changes = [];
        if ($before['due_date'] !== $purchaseOrderMilestone->fresh()->due_date?->format('d/m/Y')) {
            $changes[] = 'vencimiento: ' . ($before['due_date'] ?? 'sin fecha') . ' → ' . ($purchaseOrderMilestone->due_date?->format('d/m/Y') ?? 'sin fecha');
        }
        if ($before['value'] !== (float) $validated['value']) {
            $changes[] = 'valor: ' . $before['value'] . ' → ' . $validated['value'];
        }
        if ($before['payment_condition'] !== $validated['payment_condition']) {
            $changes[] = 'condición: ' . $before['payment_condition'] . ' → ' . $validated['payment_condition'];
        }
        if ($before['is_advance'] !== $validated['is_advance']) {
            $changes[] = 'anticipo: ' . ($before['is_advance'] ? 'sí' : 'no') . ' → ' . ($validated['is_advance'] ? 'sí' : 'no');
        }

        $ocFolio = $purchaseOrderMilestone->purchaseOrder?->folio ?? $purchaseOrderMilestone->purchase_order_id;
        $summary = count($changes) > 0 ? ' (' . implode('; ', $changes) . ')' : '';

        // Notificación de auditoría
        $this->notification->send([
            'type'         => 'PurchaseOrderMilestone',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrderMilestone->id,
            'data'         => "actualizó el Hito #{$purchaseOrderMilestone->id} de OC #{$ocFolio}{$summary}.",
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrderMilestone->purchase_order_id)
            ->with('success', 'Hito actualizado correctamente.');
    }

    private function ensureMilestoneValueIsWithinOrderTotal(
        PurchaseOrder $purchaseOrder,
        string $valueType,
        float $value,
        ?PurchaseOrderMilestone $existingMilestone = null,
    ): void
    {
        if ($valueType === 'porcentaje' && $value > 100) {
            throw ValidationException::withMessages([
                'value' => 'El porcentaje no puede ser mayor a 100%.',
            ]);
        }

        $orderTotal = (float) $purchaseOrder->total_with_iva;
        $projectedMilestones = $purchaseOrder->milestones()
            ->orderBy('id')
            ->get(['id', 'value_type', 'value'])
            ->map(function (PurchaseOrderMilestone $milestone) use ($existingMilestone, $valueType, $value) {
                $isBeingUpdated = $existingMilestone?->id === $milestone->id;

                return [
                    'value_type' => $isBeingUpdated ? $valueType : $milestone->value_type,
                    'value' => (float) ($isBeingUpdated ? $value : $milestone->value),
                ];
            });

        if (!$existingMilestone) {
            $projectedMilestones->push([
                'value_type' => $valueType,
                'value' => $value,
            ]);
        }

        $fixedAmount = (float) $projectedMilestones
            ->where('value_type', 'fijo')
            ->sum('value');
        $percentageMilestones = $projectedMilestones
            ->where('value_type', 'porcentaje')
            ->values();
        $percentageTotal = (float) $percentageMilestones->sum('value');
        $previousPercentageAmounts = 0.0;
        $percentageAmount = 0.0;

        foreach ($percentageMilestones as $index => $milestone) {
            $isLastPercentageMilestone = abs($percentageTotal - 100) < 0.0001
                && $index === $percentageMilestones->count() - 1;
            $amount = $isLastPercentageMilestone
                ? round($orderTotal - $previousPercentageAmounts, 2)
                : round($orderTotal * (float) $milestone['value'] / 100, 2);

            $previousPercentageAmounts = round($previousPercentageAmounts + $amount, 2);
            $percentageAmount = round($percentageAmount + $amount, 2);
        }

        $projectedTotal = round($fixedAmount + $percentageAmount, 2);
        if ($projectedTotal <= $orderTotal + 0.009) {
            return;
        }

        throw ValidationException::withMessages([
            'value' => 'El total acumulado de los hitos ('
                . number_format($projectedTotal, 2, '.', ',')
                . ') no puede superar el total de la OC ('
                . number_format($orderTotal, 2, '.', ',') . ').',
        ]);
    }

    public function destroy(PurchaseOrderMilestone $purchaseOrderMilestone): RedirectResponse
    {
        $milestoneId = $purchaseOrderMilestone->id;
        $orderId     = $purchaseOrderMilestone->purchase_order_id;

        $purchaseOrder = $purchaseOrderMilestone->purchaseOrder;
        if ($purchaseOrder?->status === 'autorizada' && !Auth::user()->hasRole('admin')) {
            return redirect()->route('purchase_orders.show', $orderId)
                ->with('error', 'Solo admin puede editar hitos de una OC autorizada.');
        }

        $payments = $purchaseOrderMilestone->payments()->orderBy('id')->get();

        if ($payments->contains('status', 'pagado')) {
            return redirect()->route('purchase_orders.show', $orderId)
                ->with('error', 'No se puede eliminar un hito con pago en estatus pagado.');
        }

        foreach ($payments as $payment) {
            if ($payment->spei_receipt_path && Storage::disk('s3')->exists($payment->spei_receipt_path)) {
                Storage::disk('s3')->delete($payment->spei_receipt_path);
            }
            $payment->delete();
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

    /**
     * Devuelve los hitos de una OC en formato JSON (para AJAX en Alta de Facturas).
     */
    public function forOrder(PurchaseOrder $purchaseOrder): \Illuminate\Http\JsonResponse
    {
        $milestones = $purchaseOrder->milestones()
            ->select('id', 'purchase_order_id', 'concept', 'payment_condition', 'value_type', 'value', 'covered_amount', 'due_date', 'is_advance')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function ($m, $index) {
                return [
                    'id'               => $m->id,
                    'position'         => $index + 1,
                    'concept'          => $m->concept,
                    'payment_condition'=> $m->payment_condition ?? 'credito',
                    'value_type'       => $m->value_type,
                    'value'            => $m->value,
                    'effective_amount' => $m->effective_amount,
                    'covered_amount'   => $m->covered_amount,
                    'due_date'         => $m->due_date ? $m->due_date->format('d/m/Y') : null,
                    'is_advance'       => $m->is_advance,
                ];
            });

        return response()->json($milestones);
    }
}
