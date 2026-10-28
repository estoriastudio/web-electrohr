<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use App\Models\PurchaseOrderMilestoneAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MilestoneAmountAdjuster
{
    public function __construct(
        private PaymentFolioGenerator $folios,
        private PurchaseOrderPaymentSequenceValidator $sequence,
    ) {}

    public function adjust(PurchaseOrder $order, PurchaseOrderMilestone $milestone, User $user, array $data): void
    {
        DB::transaction(function () use ($order, $milestone, $user, $data) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($user->hasRole('admin')
                || ($user->hasRole('Orden de compra') && (int) $order->buyer_id === (int) $user->id), 403);
            $order->ensureOpen();
            $milestone = $order->milestones()->lockForUpdate()->findOrFail($milestone->id);
            $order->load(['items', 'milestones']);
            $milestone->setRelation('purchaseOrder', $order);
            $payments = $milestone->payments()->lockForUpdate()->orderBy('id')->get();

            if ($payments->whereIn('status', ['autorizado', 'pagado'])->isNotEmpty()) {
                throw ValidationException::withMessages(['amount' => 'No se puede modificar un hito con pagos autorizados o pagados.']);
            }

            $amount = round((float) $data['amount'], 2);
            $previous = $milestone->effective_amount;
            if ($amount === $previous) {
                throw ValidationException::withMessages(['amount' => 'El monto nuevo debe ser diferente al actual.']);
            }
            $invoiced = (float) $milestone->invoices()
                ->where('status', PurchaseOrderInvoice::STATUS_ACEPTADA)
                ->selectRaw('COALESCE(SUM(COALESCE(net_scope, amount)), 0) as accepted_amount')
                ->value('accepted_amount');
            if ($amount < max($invoiced, (float) $milestone->covered_amount)) {
                throw ValidationException::withMessages(['amount' => 'El monto no puede ser menor que lo facturado aceptado o pagado.']);
            }

            $previousType = $milestone->value_type;
            $previousValue = $milestone->value;
            $beforeAmounts = $order->milestones->mapWithKeys(fn ($m) => [$m->id => $m->effective_amount]);
            $milestone->update(['value_type' => 'fijo', 'value' => $amount]);
            $partial = null;
            if ($amount < $previous) {
                $isCreditPartial = ($data['partial']['payment_condition'] ?? null) === 'credito'
                    && ! ($data['partial']['is_advance'] ?? false);
                Validator::make($data, [
                    'partial.payment_condition' => 'required|in:contado,credito',
                    'partial.due_date' => 'required|date',
                    'partial.credit_start' => [Rule::requiredIf($isCreditPartial), 'nullable', Rule::in([
                        PurchaseOrderMilestone::CREDIT_START_INVOICE, PurchaseOrderMilestone::CREDIT_START_FULFILLMENT,
                    ])],
                    'partial.credit_days' => [Rule::requiredIf($isCreditPartial), 'nullable', 'integer', 'min:0', 'max:365'],
                ])->validate();
                $terms = $data['partial'] ?? [];
                $isAdvance = (bool) ($terms['is_advance'] ?? false);
                $isCredit = $terms['payment_condition'] === 'credito' && ! $isAdvance;
                $this->sequence->ensureNewInitialPaymentDate($order, $terms['due_date'], 'partial.due_date');
                $partial = $order->milestones()->create([
                    'type' => 'regular',
                    'concept' => $terms['concept'] ?? $milestone->concept,
                    'payment_condition' => $terms['payment_condition'],
                    'is_advance' => $isAdvance,
                    'credit_days' => $isCredit ? $terms['credit_days'] : null,
                    'credit_start' => $isCredit ? $terms['credit_start'] : null,
                    'value_type' => 'fijo',
                    'value' => round($previous - $amount, 2),
                    'covered_amount' => 0,
                    'due_date' => $terms['due_date'],
                    'split_from_milestone_id' => $milestone->id,
                ]);
                $this->createPayment($partial, $partial->effective_amount);
            }

            $order->load('milestones');
            foreach ($order->milestones as $other) {
                $other->setRelation('purchaseOrder', $order);
                if ($other->id !== $milestone->id && $beforeAmounts->has($other->id)
                    && abs($beforeAmounts[$other->id] - $other->effective_amount) > 0.001) {
                    throw ValidationException::withMessages([
                        'amount' => 'La conversión a monto fijo cambiaría el redondeo de otro hito porcentual. Dirección debe revisar la distribución.',
                    ]);
                }
            }
            $total = round($order->milestones->sum(fn ($m) => $m->effective_amount), 2);
            if ($total > round((float) $order->total_with_iva, 2)) {
                throw ValidationException::withMessages(['amount' => 'La suma de los hitos no puede superar el total de la OC.']);
            }

            $pending = $payments->whereIn('status', ['por_autorizar', 'pospuesto'])->values();
            if ($pending->isEmpty()) {
                $this->sequence->ensureExistingMilestoneFirstPaymentDate($order, $milestone->id, $milestone->due_date, 'amount');
                $this->createPayment($milestone, $amount);
            } else {
                $remaining = (int) round($amount * 100);
                $weight = (float) $pending->sum('amount');
                foreach ($pending as $index => $payment) {
                    $cents = $index === $pending->count() - 1
                        ? $remaining
                        : min($remaining, (int) round($amount * 100 * ($weight > 0 ? (float) $payment->amount / $weight : 1 / $pending->count())));
                    $payment->update(['amount' => $cents / 100]);
                    $remaining -= $cents;
                }
            }
            $milestone->ensurePaymentTotalWithinAmount(
                (float) $milestone->payments()->whereIn('status', Payment::COMMITTED_STATUSES)->sum('amount'),
            );
            PurchaseOrderMilestoneAdjustment::create([
                'milestone_id' => $milestone->id,
                'user_id' => $user->id,
                'previous_amount' => $previous,
                'new_amount' => $amount,
                'previous_value_type' => $previousType,
                'previous_value' => $previousValue,
                'reason' => $data['reason'],
                'partial_milestone_id' => $partial?->id,
            ]);
        });
    }

    private function createPayment(PurchaseOrderMilestone $milestone, float $amount): void
    {
        Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => $this->folios->generate(),
            'amount' => $amount,
            'payment_date' => $milestone->due_date,
            'status' => 'por_autorizar',
        ]);
    }
}
