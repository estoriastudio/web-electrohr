<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Evalúa si una OC puede cerrarse.
 *
 * OC cerrable = autorizada + entregada + checklist de todos sus hitos completo
 * + pagos totalmente pagados sin saldo + facturación aceptada sin regularizaciones pendientes.
 */
class PurchaseOrderClosureEvaluator
{
    public function __construct(private PurchaseOrderMilestoneChecklist $checklist) {}

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
            $this->milestoneChecklists($milestones),
            $this->payments($order, $milestones),
            $this->invoices($order, $milestones),
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
        $met = $total === 0 || $order->delivery_status === 'entregado';

        return [
            'key' => 'delivery',
            'label' => 'Entrega completa',
            'met' => $met,
            'detail' => $total === 0
                ? 'La OC no tiene conceptos por entregar'
                : ($met ? 'Todos los conceptos entregados' : $delivered . ' de ' . $total . ' concepto(s) entregados'),
        ];
    }

    private function milestoneChecklists($milestones): array
    {
        $checklists = $this->checklist->forMilestones($milestones);
        $incomplete = collect($checklists)->reject(fn ($checklist) => $checklist['complete'])->count();
        $met = $milestones->isNotEmpty() && $incomplete === 0;

        return [
            'key' => 'milestone_checklists',
            'label' => 'Checklist de todos los hitos',
            'met' => $met,
            'detail' => $milestones->isEmpty()
                ? 'La OC no tiene hitos'
                : ($met ? 'Todos los hitos cumplen su checklist' : $incomplete . ' hito(s) con checklist pendiente'),
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

    private function invoices(PurchaseOrder $order, $milestones): array
    {
        $acceptedByMilestone = DB::table('invoice_milestone')
            ->join('purchase_order_invoices', 'purchase_order_invoices.id', '=', 'invoice_milestone.purchase_order_invoice_id')
            ->whereIn('invoice_milestone.purchase_order_milestone_id', $milestones->pluck('id'))
            ->where('purchase_order_invoices.status', PurchaseOrderInvoice::STATUS_ACEPTADA)
            ->distinct()
            ->pluck('invoice_milestone.purchase_order_milestone_id');

        $withoutInvoice = $milestones->reject(fn ($milestone) => $acceptedByMilestone->contains($milestone->id))->count();
        $inProcess = $order->invoices()->where('status', PurchaseOrderInvoice::STATUS_EN_PROCESO)->count();

        $problems = array_filter([
            $milestones->isEmpty() ? 'La OC no tiene hitos' : null,
            $withoutInvoice > 0 && $milestones->isNotEmpty() ? $withoutInvoice . ' hito(s) sin factura aceptada' : null,
            $inProcess > 0 ? $inProcess . ' factura(s) en proceso de revisión' : null,
        ]);

        return [
            'key' => 'invoices',
            'label' => 'Facturación aceptada',
            'met' => $problems === [],
            'detail' => $problems === [] ? 'Todos los hitos tienen factura aceptada' : implode('; ', $problems),
        ];
    }
}
