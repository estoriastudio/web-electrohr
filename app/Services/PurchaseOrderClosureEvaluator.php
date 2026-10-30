<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderItemDelivery;
use App\Models\PurchaseOrderMilestoneFulfillment;
use Illuminate\Support\Facades\DB;

/**
 * Evalúa si una OC puede cerrarse (solicitud funcional §12):
 * OC autorizada + entregada + todos sus eventos (hitos) cerrados + sin saldo por pagar
 * + importe facturado completo + sin regularizaciones documentales pendientes.
 */
class PurchaseOrderClosureEvaluator
{
    /** Misma tolerancia de centavos que se acepta al facturar una OC. */
    private const AMOUNT_TOLERANCE = 0.5;

    public function __construct(private MilestoneEventStatus $eventStatus) {}

    /**
     * @return array{ready: bool, requirements: array<int, array{key: string, label: string, met: bool, detail: string}>, pending: array<int, string>}
     */
    public function evaluate(PurchaseOrder $order): array
    {
        // Instancia nueva para no alterar las relaciones ya cargadas por quien llama.
        $order = PurchaseOrder::with(['items', 'milestones'])->findOrFail($order->id);
        $milestones = $order->milestones->sortBy('id')->values();
        $milestones->each(fn ($milestone) => $milestone->setRelation('purchaseOrder', $order));

        $requirements = [
            $this->authorization($order),
            $this->delivery($order),
            $this->events($milestones),
            $this->payments($order, $milestones),
            $this->invoicedAmount($order),
            $this->documentary($order),
        ];

        $pending = collect($requirements)->where('met', false)->pluck('label')->values()->all();

        return [
            'ready' => $pending === [],
            'requirements' => $requirements,
            'pending' => $pending,
        ];
    }

    private function authorization(PurchaseOrder $order): array
    {
        $met = $order->status === 'autorizada';

        return [
            'key' => 'authorized',
            'label' => 'OC autorizada',
            'met' => $met,
            'detail' => $met ? 'Autorizada' : 'La OC aún no está autorizada',
        ];
    }

    private function delivery(PurchaseOrder $order): array
    {
        $total = $order->items->count();
        $delivered = $order->items->whereNotNull('delivered_at')->count();
        $conceptsDelivered = $total === 0 || $order->delivery_status === 'entregado';
        $fulfilledByRecords = !$conceptsDelivered && $this->allMilestonesFulfilledByRecord($order);
        $met = $conceptsDelivered || $fulfilledByRecords;

        return [
            'key' => 'delivery',
            'label' => 'Entrega completa',
            'met' => $met,
            'detail' => $total === 0
                ? 'La OC no tiene conceptos por entregar'
                : ($fulfilledByRecords
                    ? 'Todos los hitos cumplidos por registro de cumplimiento'
                    : ($met ? 'Todos los conceptos entregados' : $delivered . ' de ' . $total . ' concepto(s) entregados')),
        ];
    }

    /** Sin entregas físicas en la OC y con un registro de cumplimiento en cada hito. */
    private function allMilestonesFulfilledByRecord(PurchaseOrder $order): bool
    {
        if ($order->milestones->isEmpty()) {
            return false;
        }

        $hasPhysicalDeliveries = PurchaseOrderItemDelivery::whereIn('purchase_order_item_id', $order->items->pluck('id'))->exists();
        if ($hasPhysicalDeliveries) {
            return false;
        }

        return PurchaseOrderMilestoneFulfillment::whereIn('purchase_order_milestone_id', $order->milestones->pluck('id'))
            ->distinct()
            ->count('purchase_order_milestone_id') === $order->milestones->count();
    }

    private function events($milestones): array
    {
        $statuses = $this->eventStatus->forMilestones($milestones);
        $open = collect($statuses)->reject(fn ($status) => $status['closed']);
        $met = $milestones->isNotEmpty() && $open->isEmpty();

        return [
            'key' => 'events',
            'label' => 'Todos los eventos cerrados',
            'met' => $met,
            'detail' => $milestones->isEmpty()
                ? 'La OC no tiene hitos'
                : ($met
                    ? 'Cumplimiento, facturación y pago completos en todos los hitos'
                    : $open->count() . ' de ' . $milestones->count() . ' hito(s) abiertos (pendiente de '
                        . $open->flatMap(fn ($status) => $status['pending'])->unique()->implode(', ') . ')'),
        ];
    }

    private function payments(PurchaseOrder $order, $milestones): array
    {
        $payments = $order->payments()->get();
        $unpaid = $payments->where('status', '!=', 'pagado')->count();
        $incompleteMilestones = $milestones->reject(fn ($milestone) => $milestone->is_complete)->count();
        $allocated = round((float) $milestones->sum(fn ($milestone) => $milestone->effective_amount), 2);
        $total = (float) $order->total_with_iva;
        $uncovered = $total > 0 && $allocated < $total - 0.01;

        $problems = array_filter([
            $payments->isEmpty() ? 'Sin pagos registrados' : null,
            $unpaid > 0 ? $unpaid . ' pago(s) sin pagar' : null,
            $incompleteMilestones > 0 ? $incompleteMilestones . ' hito(s) con saldo pendiente' : null,
            $uncovered ? 'Los hitos no cubren el total de la OC' : null,
        ]);

        return [
            'key' => 'payments',
            'label' => 'Pagos completos sin saldo',
            'met' => $problems === [],
            'detail' => $problems === [] ? 'Todo el importe fue pagado' : implode('; ', $problems),
        ];
    }

    private function invoicedAmount(PurchaseOrder $order): array
    {
        $total = round((float) $order->total_with_iva, 2);
        $invoiced = round((float) $order->invoices()
            ->where('status', PurchaseOrderInvoice::STATUS_ACEPTADA)
            ->selectRaw('COALESCE(SUM(COALESCE(net_scope, amount)), 0) as total')
            ->value('total'), 2);
        $met = $total <= 0 || $invoiced >= $total - self::AMOUNT_TOLERANCE;

        return [
            'key' => 'invoiced_amount',
            'label' => 'Importe facturado completo',
            'met' => $met,
            'detail' => $met
                ? 'Facturado ' . number_format($invoiced, 2) . ' de ' . number_format($total, 2)
                : 'Faltan ' . number_format($total - $invoiced, 2) . ' por facturar (aceptadas ' . number_format($invoiced, 2) . ' de ' . number_format($total, 2) . ')',
        ];
    }

    private function documentary(PurchaseOrder $order): array
    {
        $withoutRegularization = DB::table('payments')
            ->join('purchase_order_milestones', 'purchase_order_milestones.id', '=', 'payments.milestone_id')
            ->where('purchase_order_milestones.purchase_order_id', $order->id)
            ->where('payments.status', 'pagado')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('invoice_milestone')
                    ->join('purchase_order_invoices', 'purchase_order_invoices.id', '=', 'invoice_milestone.purchase_order_invoice_id')
                    ->whereColumn('invoice_milestone.purchase_order_milestone_id', 'payments.milestone_id')
                    ->where('purchase_order_invoices.status', PurchaseOrderInvoice::STATUS_ACEPTADA);
            })
            ->count();
        $inProcess = $order->invoices()->where('status', PurchaseOrderInvoice::STATUS_EN_PROCESO)->count();

        $problems = array_filter([
            $withoutRegularization > 0 ? $withoutRegularization . ' pago(s) pendientes de regularización documental' : null,
            $inProcess > 0 ? $inProcess . ' factura(s) en proceso de revisión' : null,
        ]);

        return [
            'key' => 'documentary',
            'label' => 'Sin regularizaciones documentales pendientes',
            'met' => $problems === [],
            'detail' => $problems === [] ? 'Toda la documentación está regularizada' : implode('; ', $problems),
        ];
    }
}
