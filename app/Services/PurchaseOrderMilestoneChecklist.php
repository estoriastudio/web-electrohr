<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Checklist de comprobación por hito previo a la autorización de sus pagos.
 *
 * - Anticipo (is_advance): sin requisitos.
 * - Contado: entrega confirmada.
 * - Crédito: entrega confirmada; además factura aceptada ligada al hito cuando el plazo
 *   de crédito inicia con la validación de factura (valor predeterminado de hitos legados).
 */
class PurchaseOrderMilestoneChecklist
{
    public const KEY_DELIVERY = 'delivery';
    public const KEY_INVOICE = 'invoice';

    public function forMilestone(PurchaseOrderMilestone $milestone): array
    {
        return $this->forMilestones(collect([$milestone]))[$milestone->id];
    }

    /**
     * @param  iterable<PurchaseOrderMilestone>  $milestones
     * @return array<int, array{complete: bool, requirements: array<int, array>, pending: array<int, string>}>
     */
    public function forMilestones(iterable $milestones): array
    {
        $milestones = collect($milestones)->keyBy('id');

        if ($milestones->isEmpty()) {
            return [];
        }

        $ids = $milestones->keys()->all();

        $deliveryCounts = DB::table('purchase_order_item_deliveries')
            ->whereIn('milestone_id', $ids)
            ->selectRaw('milestone_id, COUNT(*) as total')
            ->groupBy('milestone_id')
            ->pluck('total', 'milestone_id');

        $invoiceCounts = DB::table('invoice_milestone')
            ->join('purchase_order_invoices', 'purchase_order_invoices.id', '=', 'invoice_milestone.purchase_order_invoice_id')
            ->whereIn('invoice_milestone.purchase_order_milestone_id', $ids)
            ->selectRaw('invoice_milestone.purchase_order_milestone_id as milestone_id, purchase_order_invoices.status, COUNT(*) as total')
            ->groupBy('invoice_milestone.purchase_order_milestone_id', 'purchase_order_invoices.status')
            ->get()
            ->groupBy('milestone_id');

        return $milestones->map(function (PurchaseOrderMilestone $milestone) use ($deliveryCounts, $invoiceCounts) {
            $byStatus = ($invoiceCounts->get($milestone->id) ?? collect())->pluck('total', 'status');

            return $this->evaluate(
                $milestone,
                (int) ($deliveryCounts[$milestone->id] ?? 0),
                (int) ($byStatus[PurchaseOrderInvoice::STATUS_ACEPTADA] ?? 0),
                (int) ($byStatus[PurchaseOrderInvoice::STATUS_EN_PROCESO] ?? 0),
            );
        })->all();
    }

    /**
     * Bloquea la autorización si algún hito de los pagos no cumple su checklist.
     *
     * @param  iterable<Payment>  $payments
     */
    public function ensureComplete(iterable $payments, string $errorKey = 'payment_ids'): void
    {
        $payments = collect($payments);
        $milestones = PurchaseOrderMilestone::query()
            ->whereIn('id', $payments->pluck('milestone_id')->unique())
            ->get();

        $checklists = $this->forMilestones($milestones);
        $messages = [];

        foreach ($payments as $payment) {
            $checklist = $checklists[$payment->milestone_id] ?? null;

            if ($checklist && !$checklist['complete']) {
                $messages[$payment->milestone_id] = 'El hito #' . $payment->milestone_id
                    . ' no cumple su checklist (pendiente: ' . implode(', ', $checklist['pending']) . ').';
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages([$errorKey => implode(' ', $messages)]);
        }
    }

    private function evaluate(PurchaseOrderMilestone $milestone, int $deliveries, int $acceptedInvoices, int $pendingInvoices): array
    {
        $isAdvance = (bool) $milestone->is_advance;
        $isCredit = ($milestone->payment_condition ?? 'credito') === 'credito';
        $deliveryConfirmed = $milestone->delivery_confirmed_at !== null;
        $invoiceRequired = !$isAdvance && $isCredit
            && ($milestone->credit_start ?: PurchaseOrderMilestone::CREDIT_START_INVOICE) === PurchaseOrderMilestone::CREDIT_START_INVOICE;
        $invoiceApplicable = !$isAdvance;

        $requirements = [
            [
                'key' => self::KEY_DELIVERY,
                'label' => 'Entrega confirmada',
                'required' => !$isAdvance,
                'applicable' => !$isAdvance,
                'met' => $isAdvance || $deliveryConfirmed,
                'detail' => $isAdvance
                    ? 'No aplica a anticipos'
                    : ($deliveryConfirmed
                        ? 'Confirmada'
                        : ($deliveries > 0 ? $deliveries . ' entrega(s) ligada(s), falta confirmar' : 'Sin entregas ligadas al hito')),
            ],
            [
                'key' => self::KEY_INVOICE,
                'label' => 'Factura aceptada',
                'required' => $invoiceRequired,
                'applicable' => $invoiceApplicable,
                'met' => !$invoiceRequired || $acceptedInvoices > 0,
                'detail' => $acceptedInvoices > 0
                    ? $acceptedInvoices . ' aceptada(s)'
                    : ($invoiceRequired
                        ? ($pendingInvoices > 0 ? $pendingInvoices . ' factura(s) en proceso' : 'Sin factura ligada al hito')
                        : ($invoiceApplicable ? 'No requerida para autorizar el pago' : 'No aplica a anticipos')),
            ],
        ];

        $pending = collect($requirements)->where('required', true)->where('met', false)->pluck('label')->all();

        return [
            'complete' => $pending === [],
            'requirements' => $requirements,
            'pending' => $pending,
        ];
    }
}
