<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;
use App\Models\PurchaseOrderMilestoneCredit;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Caja de los hitos de una OC: autorizar con monto, registrar pagado con importe, abonar el
 * excedente a los hitos siguientes, sustituir los PAY pendientes que ya no caben y corregir
 * (admin) un pago pagado. Toda operación corre en una transacción que bloquea OC → hitos → pagos.
 *
 * La vista previa (`$dryRun`) ejecuta la misma lógica y deshace la transacción: no bloquea,
 * no genera folios y no notifica.
 */
class MilestonePaymentService
{
    private const TOLERANCE = PurchaseOrder::AMOUNT_TOLERANCE;

    private const EPSILON = 0.004;

    private bool $dryRun = false;

    private int $dryFolios = 0;

    private PurchaseOrder $order;

    /** @var array<int, int> */
    private array $milestoneIds = [];

    /** @var array<int, string> */
    private array $movements = [];

    private ?Payment $rootPayment = null;

    public function __construct(
        private PaymentFolioGenerator $folios,
        private PurchaseOrderMilestoneChecklist $checklist,
        private PurchaseOrderPaymentSequenceValidator $sequence,
        private NotificationService $notification,
    ) {}

    /**
     * Autoriza un PAY por $amount (admin). Si es menor genera el resto; si es mayor que el saldo
     * libre del hito, el excedente se convierte en PAY de abono autorizados en los hitos siguientes.
     *
     * @return array{movements: array<int, string>, warnings: array<int, string>}
     */
    public function authorizeWithAmount(Payment $payment, float $amount, bool $skipChecklist = false, bool $dryRun = false): array
    {
        return $this->run($payment, $dryRun, function (PurchaseOrder $order, Payment $payment) use ($amount, $skipChecklist) {
            $this->ensureActionable($payment, Payment::PENDING_STATUSES, 'authorized_amount');
            $this->ensureOrderAuthorized($order, 'authorized_amount');

            $milestone = $this->milestone($payment->milestone_id);
            $this->sequence->ensureAuthorizationOrder($order, [$payment->id], 'authorized_amount');
            $this->sequence->ensureStatusSequence($order, [$payment->id => 'autorizado']);
            if (! $skipChecklist) {
                $this->checklist->ensureComplete([$payment], 'authorized_amount');
            }

            $amount = round($amount, 2);
            $original = round((float) $payment->amount, 2);
            $free = $this->free($milestone);
            $touched = [$milestone->id];

            if ($amount <= $free + self::TOLERANCE + self::EPSILON) {
                $this->markAuthorized($payment, $amount, $original);
                $this->log("Autoriza {$payment->folio} por {$this->money($amount)} (Hito {$this->position($milestone->id)}).");

                $rest = round($original - $amount, 2);
                if ($rest > self::TOLERANCE + self::EPSILON) {
                    $restPayment = $this->createPayment($milestone, [
                        'amount' => $rest,
                        'payment_date' => $milestone->due_date ?? $payment->payment_date,
                        'invoice_date' => $payment->invoice_date,
                        'status' => 'por_autorizar',
                        'origin' => Payment::ORIGIN_REST,
                        'source_payment_id' => $payment->id,
                    ]);
                    $this->log("Genera {$restPayment->folio} por {$this->money($rest)} por autorizar (resto de {$payment->folio}).");
                }
            } else {
                $chain = $this->planExcessChain($milestone, $free, $amount, 'authorized_amount');

                $this->markAuthorized($payment, $free, $original);
                $this->log("Autoriza {$payment->folio} por {$this->money($free)} (saldo del Hito {$this->position($milestone->id)}).");

                foreach ($chain as [$target, $credit]) {
                    $creditPayment = $this->createPayment($target, [
                        'amount' => $credit,
                        'payment_date' => $target->due_date ?? $payment->payment_date,
                        'status' => 'autorizado',
                        'origin' => Payment::ORIGIN_CREDIT,
                        'source_payment_id' => $payment->id,
                        'checklist_verified_at' => now(),
                    ]);
                    $this->log("Genera {$creditPayment->folio} autorizado por {$this->money($credit)} como abono al Hito {$this->position($target->id)} (excedente de {$payment->folio}).");
                    $touched[] = $target->id;
                }
            }

            foreach (array_unique($touched) as $id) {
                $this->recalculatePending($this->milestone($id));
                $this->ensureWithinLimit($this->milestone($id), 'authorized_amount');
                $this->noteTolerance($this->milestone($id));
            }
        });
    }

    /**
     * Registra pagado un PAY autorizado con el importe del SPEI.
     *
     * @param  array{path: string, name: string}|null  $receipt
     * @return array{movements: array<int, string>, warnings: array<int, string>}
     */
    public function registerPaid(Payment $payment, float $paidAmount, ?array $receipt = null, bool $skipChecklist = false, bool $dryRun = false): array
    {
        return $this->run($payment, $dryRun, function (PurchaseOrder $order, Payment $payment) use ($paidAmount, $receipt, $skipChecklist) {
            $this->ensureActionable($payment, ['autorizado'], 'paid_amount');

            $this->sequence->ensurePaymentOrder($order, [$payment->id], 'paid_amount');
            $this->sequence->ensureStatusSequence($order, [$payment->id => 'pagado']);
            if ($payment->origin === Payment::ORIGIN_CREDIT && ! $skipChecklist) {
                $this->checklist->ensureComplete([$payment], 'paid_amount');
            }

            $this->applyPayment($payment, round($paidAmount, 2), $receipt, false);
        });
    }

    /**
     * Corrección de admin: cambia el importe pagado de un pago `pagado` (revierte y vuelve a aplicar).
     *
     * @return array{movements: array<int, string>, warnings: array<int, string>}
     */
    public function correctPaidAmount(Payment $payment, float $newPaidAmount, bool $confirmed = false, bool $dryRun = false): array
    {
        return $this->run($payment, $dryRun, function (PurchaseOrder $order, Payment $payment) use ($newPaidAmount) {
            $this->ensureActionable($payment, ['pagado'], 'paid_amount');

            $touched = $this->revertPaid($payment);
            $payment->update(['status' => 'autorizado', 'paid_amount' => null, 'applied_amount' => null]);
            $touched = array_merge($touched, $this->applyPayment($payment->refresh(), round($newPaidAmount, 2), null, true));

            $this->fillGaps($touched);

            return $touched;
        }, $confirmed);
    }

    /**
     * Corrección de admin: regresa a `autorizado` un pago `pagado` y revierte sus efectos.
     *
     * @return array{movements: array<int, string>, warnings: array<int, string>}
     */
    public function revertPaidToAuthorized(Payment $payment, bool $confirmed = false, bool $dryRun = false): array
    {
        return $this->run($payment, $dryRun, function (PurchaseOrder $order, Payment $payment) {
            $this->ensureActionable($payment, ['pagado'], 'status');

            $touched = $this->revertPaid($payment);
            $payment->update([
                'status' => 'autorizado',
                'paid_amount' => null,
                'applied_amount' => null,
                'spei_receipt_path' => null,
                'spei_receipt_name' => null,
            ]);
            $this->log("Regresa {$payment->folio} a autorizado por {$this->money((float) $payment->amount)}.");
            $this->fillGaps($touched);

            return $touched;
        }, $confirmed);
    }

    /**
     * Corrección de admin: elimina un pago `pagado` y revierte sus efectos.
     *
     * @return array{movements: array<int, string>, warnings: array<int, string>}
     */
    public function deletePaid(Payment $payment, bool $confirmed = false, bool $dryRun = false): array
    {
        return $this->run($payment, $dryRun, function (PurchaseOrder $order, Payment $payment) {
            $this->ensureActionable($payment, ['pagado'], 'status');

            $touched = $this->revertPaid($payment);
            $this->log("Elimina {$payment->folio} (pagado por {$this->money($payment->paid_amount_value)}).");
            $payment->delete();
            $this->fillGaps($touched);

            return $touched;
        }, $confirmed);
    }

    /**
     * Recalcula los PAY pendientes del hito de $payment tras rechazar, eliminar o reactivar uno.
     *
     * @return array{movements: array<int, string>, warnings: array<int, string>}
     */
    public function recalculateAfterPendingChange(int $milestoneId): array
    {
        return $this->run($milestoneId, false, function () use ($milestoneId) {
            $this->recalculatePending($this->milestone($milestoneId));
        });
    }

    /** Un PAY que ya generó resto, abonos o sustituciones no puede regresar, rechazarse, posponerse ni eliminarse. */
    public function ensureNotGenerated(Payment $payment): void
    {
        $generated = Payment::query()->where('source_payment_id', $payment->id)->orderBy('id')->get();

        if ($generated->isNotEmpty()) {
            $milestoneIds = PurchaseOrderMilestone::query()
                ->where('purchase_order_id', $payment->milestone->purchase_order_id)
                ->orderBy('id')
                ->pluck('id')
                ->values();
            $detail = $generated
                ->map(fn (Payment $item) => $item->folio.' (Hito '.(($milestoneIds->search($item->milestone_id) ?: 0) + 1).')')
                ->implode(', ');

            throw ValidationException::withMessages([
                'status' => "Este PAY ya generó movimientos y no se puede regresar, rechazar, posponer ni eliminar: {$detail}.",
            ]);
        }
    }

    /**
     * Caja por hito para el detalle de la OC. Requiere milestones.payments y milestones.credits cargados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cashByMilestone(PurchaseOrder $order): array
    {
        $cash = [];

        foreach ($order->milestones as $milestone) {
            $payments = $milestone->payments->reject(fn (Payment $payment) => in_array($payment->status, [Payment::STATUS_SUSTITUIDO, 'rechazado'], true));
            $effective = round($milestone->effective_amount, 2);
            $covered = round((float) $milestone->covered_amount, 2);
            $paid = round((float) $payments->where('status', 'pagado')->sum(fn (Payment $payment) => $payment->applied_amount_value), 2);
            $credits = round((float) $milestone->credits->sum('amount'), 2);
            $authorized = round((float) $payments->where('status', 'autorizado')->sum('amount'), 2);
            $pending = round((float) $payments->whereIn('status', Payment::PENDING_STATUSES)->sum('amount'), 2);
            $difference = round($effective - $covered - $authorized - $pending, 2);
            $tolerance = $difference > self::EPSILON && $difference <= self::TOLERANCE ? $difference : 0.0;

            $cash[$milestone->id] = [
                'effective' => $effective,
                'covered' => $covered,
                'paid' => $paid,
                'credits' => $credits,
                'tolerance' => $tolerance,
                'authorized' => $authorized,
                'pending' => $pending,
                'balance' => $milestone->is_complete ? 0.0 : max(0.0, round($effective - $covered, 2)),
                'free' => max(0.0, round($effective - $covered - $authorized, 2)),
            ];
        }

        return $cash;
    }

    // -----------------------------------------------------------------------------------------
    // Núcleo
    // -----------------------------------------------------------------------------------------

    /**
     * @param  Closure(PurchaseOrder, ?Payment): mixed  $callback  devuelve, opcionalmente, los ids de hitos afectados
     * @return array{movements: array<int, string>, warnings: array<int, string>}
     */
    private function run(Payment|int $subject, bool $dryRun, Closure $callback, bool $confirmed = true): array
    {
        $this->dryRun = $dryRun;
        $this->movements = [];
        $this->dryFolios = 0;

        $payment = $subject instanceof Payment ? $subject : null;
        $milestoneId = $payment?->milestone_id ?? $subject;
        $orderId = PurchaseOrderMilestone::query()->whereKey($milestoneId)->value('purchase_order_id');
        $warnings = [];

        DB::beginTransaction();

        try {
            $this->order = PurchaseOrder::query()
                ->when(! $dryRun, fn ($query) => $query->lockForUpdate())
                ->findOrFail($orderId);
            $this->order->ensureOpen();
            $this->order->load('items');
            $this->order->setRelation('milestones', $this->order->milestones()->orderBy('id')->get());
            $this->milestoneIds = PurchaseOrderMilestone::query()
                ->where('purchase_order_id', $orderId)
                ->orderBy('id')
                ->when(! $dryRun, fn ($query) => $query->lockForUpdate())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            Payment::query()
                ->whereIn('milestone_id', $this->milestoneIds)
                ->orderBy('id')
                ->when(! $dryRun, fn ($query) => $query->lockForUpdate())
                ->get(['id']);

            $fresh = $payment ? Payment::query()->findOrFail($payment->id) : null;
            $this->rootPayment = $fresh;

            $touched = $callback($this->order, $fresh);

            if (is_array($touched) && $touched !== []) {
                $warnings = $this->correctionWarnings($touched);
                if ($warnings !== [] && ! $dryRun && ! $confirmed) {
                    throw ValidationException::withMessages(['confirm_correction' => implode(' ', $warnings)]);
                }
            }
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return ['movements' => $this->movements, 'warnings' => $warnings];
    }

    private function milestone(int $id): PurchaseOrderMilestone
    {
        $milestone = PurchaseOrderMilestone::query()->findOrFail($id);
        $milestone->setRelation('purchaseOrder', $this->order);

        return $milestone;
    }

    private function position(int $milestoneId): int
    {
        return (int) array_search($milestoneId, $this->milestoneIds, true) + 1;
    }

    /** Saldo libre = monto efectivo − cubierto − autorizado sin pagar (nunca negativo). */
    private function free(PurchaseOrderMilestone $milestone): float
    {
        $authorized = (float) $milestone->payments()->where('status', 'autorizado')->sum('amount');

        return max(0.0, round($milestone->effective_amount - (float) $milestone->covered_amount - $authorized, 2));
    }

    private function pendingPayments(PurchaseOrderMilestone $milestone): Collection
    {
        return $milestone->payments()->whereIn('status', Payment::PENDING_STATUSES)->orderBy('id')->get();
    }

    private function ensureActionable(Payment $payment, array $allowedStatuses, string $errorKey): void
    {
        if ($payment->status === Payment::STATUS_SUSTITUIDO) {
            throw ValidationException::withMessages([$errorKey => 'Este PAY fue sustituido y ya no se puede autorizar ni pagar.']);
        }

        if (! in_array($payment->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([$errorKey => 'El pago ya no está en un estatus que permita esta acción.']);
        }
    }

    private function ensureOrderAuthorized(PurchaseOrder $order, string $errorKey): void
    {
        if ($order->status !== 'autorizada') {
            throw ValidationException::withMessages([$errorKey => 'Solo se pueden autorizar pagos de una OC autorizada.']);
        }
    }

    private function markAuthorized(Payment $payment, float $amount, float $original): void
    {
        $payment->update([
            'status' => 'autorizado',
            'amount' => $amount,
            'original_amount' => abs($amount - $original) > self::EPSILON ? ($payment->original_amount ?? $original) : $payment->original_amount,
            'checklist_verified_at' => now(),
        ]);
    }

    /**
     * Reparte el excedente de una autorización entre los hitos siguientes, en orden y sin saltar
     * ninguno: se detiene en el primero que no puede recibirlo.
     *
     * @return array<int, array{0: PurchaseOrderMilestone, 1: float}>
     */
    private function planExcessChain(PurchaseOrderMilestone $milestone, float $free, float $amount, string $errorKey): array
    {
        if ($free < 0.01) {
            throw ValidationException::withMessages([$errorKey => 'El hito no tiene saldo por autorizar.']);
        }

        $remaining = round($amount - $free, 2);
        $chain = [];
        $anyLaterFree = false;
        $later = array_slice($this->milestoneIds, ($this->position($milestone->id)));

        foreach ($later as $id) {
            $target = $this->milestone($id);
            $targetFree = $this->free($target);
            $anyLaterFree = $anyLaterFree || $targetFree > self::EPSILON;

            if ($targetFree <= self::EPSILON) {
                if ($target->is_complete) {
                    continue;
                }
                break;
            }

            if (! $this->checklist->forMilestone($target)['complete']) {
                break;
            }

            $credit = min($remaining, $targetFree);
            $chain[] = [$target, $credit];
            $remaining = round($remaining - $credit, 2);

            if ($remaining <= self::EPSILON) {
                break;
            }
        }

        if ($remaining > self::TOLERANCE + self::EPSILON) {
            $max = round($free + array_sum(array_column($chain, 1)) + self::TOLERANCE, 2);
            $message = $anyLaterFree
                ? "Lo más que se puede autorizar es {$this->money($max)}: el saldo de este hito y de los siguientes."
                : 'No hay hito siguiente con saldo donde abonar el excedente. Lo más que se puede autorizar es '
                    .$this->money(round($free + self::TOLERANCE, 2)).', el saldo de este hito.';

            throw ValidationException::withMessages([$errorKey => $message]);
        }

        if ($remaining > self::EPSILON && $chain !== []) {
            $chain[count($chain) - 1][1] = round($chain[count($chain) - 1][1] + $remaining, 2);
        }

        return $chain;
    }

    /**
     * Aplica el SPEI al hito del PAY y abona el excedente a los hitos siguientes; lo que ya no cabe
     * pasa a saldo a favor del proveedor.
     *
     * @param  array{path: string, name: string}|null  $receipt
     * @return array<int, int> ids de hitos afectados
     */
    private function applyPayment(Payment $payment, float $paid, ?array $receipt, bool $allowShort): array
    {
        $milestone = $this->milestone($payment->milestone_id);
        $authorized = round((float) $payment->amount, 2);

        if (! $allowShort && round($authorized - $paid, 2) > self::TOLERANCE + self::EPSILON) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Pagar menos de lo autorizado no está permitido: el monto se decide al autorizar.',
            ]);
        }

        // Lo que el hito puede recibir: su saldo sin contar lo autorizado a otros PAY.
        $capacity = round($this->free($milestone) + $authorized, 2);
        $applied = $paid <= $capacity + self::TOLERANCE + self::EPSILON ? $paid : $capacity;
        $excess = round($paid - $applied, 2);

        $payment->fill(['status' => 'pagado', 'paid_amount' => $paid, 'applied_amount' => $applied]);
        if ($receipt !== null) {
            $payment->fill(['spei_receipt_path' => $receipt['path'], 'spei_receipt_name' => $receipt['name']]);
        }
        $payment->save();
        $milestone->increment('covered_amount', $applied);
        $this->log("Registra pagado {$payment->folio} por {$this->money($paid)}: {$this->money($applied)} cubren el Hito {$this->position($milestone->id)}.");

        $touched = [$milestone->id];
        if ($excess > self::EPSILON) {
            $touched = array_merge($touched, $this->distributeExcess($payment, $excess));
        }

        foreach ($touched as $id) {
            $this->recalculatePending($this->milestone($id));
            $this->ensureWithinLimit($this->milestone($id), 'paid_amount');
            $this->noteTolerance($this->milestone($id));
        }

        return $touched;
    }

    /** @return array<int, int> ids de hitos que recibieron abono */
    private function distributeExcess(Payment $payment, float $excess): array
    {
        $remaining = $excess;
        $touched = [];
        $previous = $this->milestone($payment->milestone_id);

        foreach (array_slice($this->milestoneIds, $this->position($payment->milestone_id)) as $id) {
            if (! $previous->is_complete) {
                break;
            }

            $target = $this->milestone($id);
            $targetFree = $this->free($target);

            if ($targetFree <= self::EPSILON) {
                if ($target->is_complete) {
                    $previous = $target;

                    continue;
                }
                break;
            }

            if (! $this->checklist->forMilestone($target)['complete']) {
                break;
            }

            $credit = min($remaining, $targetFree);
            PurchaseOrderMilestoneCredit::create([
                'purchase_order_id' => $this->order->id,
                'milestone_id' => $target->id,
                'source_payment_id' => $payment->id,
                'amount' => $credit,
                'user_id' => Auth::id(),
            ]);
            $target->increment('covered_amount', $credit);
            $this->log("Abona {$this->money($credit)} al Hito {$this->position($target->id)} (excedente de {$payment->folio}).");
            $touched[] = $target->id;
            $remaining = round($remaining - $credit, 2);

            if ($remaining <= self::EPSILON) {
                break;
            }
            $previous = $this->milestone($id);
        }

        if ($remaining > self::EPSILON) {
            $this->order->increment('supplier_credit_amount', $remaining);
            $this->log("Saldo a favor del proveedor: {$this->money($remaining)} (excedente de {$payment->folio} que no cabe en ningún hito).");
        }

        return $touched;
    }

    /**
     * Si los PAY pendientes de un hito superan su saldo libre, los sustituye por uno solo por el saldo.
     */
    private function recalculatePending(PurchaseOrderMilestone $milestone): void
    {
        $pending = $this->pendingPayments($milestone);

        if ($pending->isEmpty()) {
            return;
        }

        $free = $this->free($milestone);

        if (round((float) $pending->sum('amount'), 2) <= $free + self::EPSILON) {
            return;
        }

        $replacement = null;
        if ($free > self::TOLERANCE + self::EPSILON) {
            $first = $pending->first();
            $replacement = $this->createPayment($milestone, [
                'amount' => $free,
                'payment_date' => $first->payment_date,
                'invoice_date' => $first->invoice_date,
                'status' => 'por_autorizar',
                'origin' => Payment::ORIGIN_REPLACEMENT,
                'source_payment_id' => $first->id,
            ]);
        }

        $position = $this->position($milestone->id);
        foreach ($pending as $payment) {
            $payment->update(['status' => Payment::STATUS_SUSTITUIDO, 'replaced_by_payment_id' => $replacement?->id]);
            $this->log($replacement
                ? "{$payment->folio} pasa a sustituido por {$replacement->folio} de {$this->money($free)} (saldo del Hito {$position})."
                : ($free > self::EPSILON
                    ? "{$payment->folio} pasa a sustituido: la diferencia de {$this->money($free)} del Hito {$position} se cubre por tolerancia."
                    : "{$payment->folio} pasa a sustituido: el Hito {$position} ya quedó cubierto."), $payment);
        }
    }

    private function noteTolerance(PurchaseOrderMilestone $milestone): void
    {
        $milestone->refresh();
        $authorized = (float) $milestone->payments()->where('status', 'autorizado')->sum('amount');
        $pending = (float) $milestone->payments()->whereIn('status', Payment::PENDING_STATUSES)->sum('amount');
        $difference = round($milestone->effective_amount - (float) $milestone->covered_amount - $authorized - $pending, 2);

        if ($difference > self::EPSILON && $difference <= self::TOLERANCE) {
            $this->log("La diferencia de {$this->money($difference)} del Hito {$this->position($milestone->id)} se cubre por tolerancia.");
        }
    }

    private function ensureWithinLimit(PurchaseOrderMilestone $milestone, string $errorKey): void
    {
        $milestone->ensurePaymentTotalWithinAmount(
            $milestone->committedPaymentsAmount(),
            $errorKey,
            self::TOLERANCE,
        );
    }

    // -----------------------------------------------------------------------------------------
    // Corrección de admin
    // -----------------------------------------------------------------------------------------

    /**
     * Revierte los efectos de un pago `pagado`: lo aplicado al hito, sus abonos y su saldo a favor.
     *
     * @return array<int, int> ids de hitos afectados
     */
    private function revertPaid(Payment $payment): array
    {
        $creditPayments = Payment::query()
            ->where('source_payment_id', $payment->id)
            ->where('origin', Payment::ORIGIN_CREDIT)
            ->where('status', '!=', Payment::STATUS_SUSTITUIDO)
            ->get();

        if ($creditPayments->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status' => 'Este pago generó abonos autorizados en '.$creditPayments->pluck('folio')->implode(', ').' y no se puede corregir.',
            ]);
        }

        $milestone = $this->milestone($payment->milestone_id);
        $applied = $payment->applied_amount_value;
        $milestone->decrement('covered_amount', $applied);
        $this->log("Revierte {$this->money($applied)} aplicados de {$payment->folio} al Hito {$this->position($milestone->id)}.");
        $touched = [$milestone->id];

        $credits = PurchaseOrderMilestoneCredit::query()
            ->where('source_payment_id', $payment->id)
            ->orderByDesc('milestone_id')
            ->get();

        foreach ($credits as $credit) {
            $this->milestone($credit->milestone_id)->decrement('covered_amount', (float) $credit->amount);
            $this->log("Revierte el abono de {$this->money((float) $credit->amount)} al Hito {$this->position($credit->milestone_id)}.");
            $touched[] = $credit->milestone_id;
            $credit->delete();
        }

        $generated = round($payment->paid_amount_value - $applied - (float) $credits->sum('amount'), 2);
        if ($generated > self::EPSILON) {
            $reverted = min($generated, (float) $this->order->supplier_credit_amount);
            $this->order->decrement('supplier_credit_amount', $reverted);
            $this->log("Revierte {$this->money($reverted)} de saldo a favor del proveedor.");
        }

        return $touched;
    }

    /**
     * Cada hito afectado recibe un PAY por autorizar por el saldo libre que sus PAY pendientes no cubren.
     *
     * @param  array<int, int>  $milestoneIds
     */
    private function fillGaps(array $milestoneIds): void
    {
        foreach (array_unique($milestoneIds) as $id) {
            $milestone = $this->milestone($id);
            $this->recalculatePending($milestone);

            $gap = round($this->free($milestone) - (float) $this->pendingPayments($milestone)->sum('amount'), 2);

            if ($gap > self::TOLERANCE + self::EPSILON) {
                $replacement = $this->createPayment($milestone, [
                    'amount' => $gap,
                    'payment_date' => $milestone->due_date ?? now()->toDateString(),
                    'status' => 'por_autorizar',
                    'origin' => Payment::ORIGIN_REPLACEMENT,
                ]);
                $this->log("Genera {$replacement->folio} por {$this->money($gap)} por autorizar (saldo del Hito {$this->position($milestone->id)}).");
            }
        }
    }

    /**
     * Hitos afectados que quedan con saldo mientras hitos siguientes ya están pagados.
     *
     * @param  array<int, int>  $touched
     * @return array<int, string>
     */
    private function correctionWarnings(array $touched): array
    {
        $warnings = [];

        foreach (array_unique($touched) as $id) {
            $milestone = $this->milestone($id);
            if ($milestone->is_complete) {
                continue;
            }

            $paidLater = collect(array_slice($this->milestoneIds, $this->position($id)))
                ->filter(fn ($laterId) => $this->milestone($laterId)->is_complete)
                ->map(fn ($laterId) => $this->position($laterId))
                ->values();

            if ($paidLater->isNotEmpty()) {
                $list = $paidLater->count() > 1
                    ? $paidLater->slice(0, -1)->implode(', ').' y '.$paidLater->last()
                    : (string) $paidLater->first();
                $verb = $paidLater->count() > 1 ? 'ya están pagados' : 'ya está pagado';
                $noun = $paidLater->count() > 1 ? 'hitos' : 'hito';
                $warnings[] = "Los {$noun} {$list} {$verb}; esta corrección deja el Hito {$this->position($id)} con saldo antes que ellos.";
            }
        }

        return $warnings;
    }

    // -----------------------------------------------------------------------------------------
    // Utilidades
    // -----------------------------------------------------------------------------------------

    private function createPayment(PurchaseOrderMilestone $milestone, array $attributes): Payment
    {
        return Payment::create($attributes + [
            'milestone_id' => $milestone->id,
            'folio' => $this->dryRun ? 'PAY-NUEVO-'.(++$this->dryFolios) : $this->folios->generate(),
        ]);
    }

    private function log(string $text, ?Payment $payment = null): void
    {
        $this->movements[] = $text;

        if ($this->dryRun) {
            return;
        }

        $target = $payment ?? $this->rootPayment;
        $this->notification->send([
            'type' => 'Payment',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $target?->id ?? 0,
            'data' => lcfirst($text).' (OC #'.$this->order->id.')',
        ]);
    }

    private function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }
}
