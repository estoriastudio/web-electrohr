<?php

namespace App\Services;

use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use Illuminate\Support\Facades\DB;

/**
 * Estado de un hito como evento de pago, en sus tres dimensiones independientes:
 * cumplimiento (entrega confirmada), facturación (factura aceptada) y pago.
 * El evento se cierra solo cuando las tres están completas.
 */
class MilestoneEventStatus
{
    /**
     * @param  iterable<PurchaseOrderMilestone>  $milestones
     * @return array<int, array{fulfillment: bool, invoicing: bool, payment: string, closed: bool, regularization_pending: bool, pending: array<int, string>, label: string, class: string}>
     */
    public function forMilestones(iterable $milestones): array
    {
        $milestones = collect($milestones)->keyBy('id');

        if ($milestones->isEmpty()) {
            return [];
        }

        $ids = $milestones->keys()->all();

        $acceptedIds = $this->milestoneIdsWithAcceptedInvoice($ids);
        $paidCounts = DB::table('payments')
            ->whereIn('milestone_id', $ids)
            ->where('status', 'pagado')
            ->selectRaw('milestone_id, COUNT(*) as total')
            ->groupBy('milestone_id')
            ->pluck('total', 'milestone_id');

        return $milestones->map(function (PurchaseOrderMilestone $milestone) use ($acceptedIds, $paidCounts) {
            $fulfillment = $milestone->delivery_confirmed_at !== null;
            $invoicing = in_array($milestone->id, $acceptedIds, true);
            $payment = $milestone->is_complete
                ? 'complete'
                : ((float) $milestone->covered_amount > 0 ? 'partial' : 'pending');
            $hasPaidPayments = (int) ($paidCounts[$milestone->id] ?? 0) > 0;
            $regularizationPending = $hasPaidPayments && !$invoicing;

            $pending = array_values(array_filter([
                $fulfillment ? null : 'cumplimiento',
                $invoicing ? null : 'factura',
                $payment === 'complete' ? null : 'pago',
            ]));
            $closed = $pending === [];

            return [
                'fulfillment' => $fulfillment,
                'invoicing' => $invoicing,
                'payment' => $payment,
                'closed' => $closed,
                'regularization_pending' => $regularizationPending,
                'pending' => $pending,
                'label' => $closed ? 'Evento cerrado' : 'Abierto – pendiente de ' . $this->join($pending),
                'class' => $closed
                    ? 'bg-success-subtle text-success'
                    : ($regularizationPending ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary'),
            ];
        })->all();
    }

    public function forMilestone(PurchaseOrderMilestone $milestone): array
    {
        return $this->forMilestones([$milestone])[$milestone->id];
    }

    /**
     * @param  array<int, int>  $milestoneIds
     * @return array<int, int>
     */
    public function milestoneIdsWithAcceptedInvoice(array $milestoneIds): array
    {
        return DB::table('invoice_milestone')
            ->join('purchase_order_invoices', 'purchase_order_invoices.id', '=', 'invoice_milestone.purchase_order_invoice_id')
            ->whereIn('invoice_milestone.purchase_order_milestone_id', $milestoneIds)
            ->where('purchase_order_invoices.status', PurchaseOrderInvoice::STATUS_ACEPTADA)
            ->distinct()
            ->pluck('invoice_milestone.purchase_order_milestone_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function join(array $items): string
    {
        return count($items) > 1
            ? implode(', ', array_slice($items, 0, -1)) . ' y ' . end($items)
            : $items[0];
    }
}
