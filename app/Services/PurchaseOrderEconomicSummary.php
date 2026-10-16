<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;

/**
 * Resumen económico de una OC: facturado (solo facturas aceptadas, neto de notas de crédito) vs. pagado.
 */
class PurchaseOrderEconomicSummary
{
    public function forOrder(PurchaseOrder $order): array
    {
        $total = round((float) $order->total_with_iva, 2);
        $invoiced = round((float) $order->invoices()
            ->where('status', PurchaseOrderInvoice::STATUS_ACEPTADA)
            ->selectRaw('COALESCE(SUM(COALESCE(net_scope, amount)), 0) as total')
            ->value('total'), 2);
        $paid = round((float) $order->saldo_cubierto, 2);

        return [
            'total' => $total,
            'invoiced' => $invoiced,
            'pending_invoicing' => max(0.0, round($total - $invoiced, 2)),
            'paid' => $paid,
            'pending_payment' => max(0.0, round($total - $paid, 2)),
            'invoiced_unpaid' => max(0.0, round($invoiced - $paid, 2)),
            'paid_without_invoice' => max(0.0, round($paid - $invoiced, 2)),
        ];
    }
}
