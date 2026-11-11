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

        $differences = [
            'pending_invoicing' => round($total - $invoiced, 2),
            'pending_payment' => round($total - $paid, 2),
            'invoiced_unpaid' => round($invoiced - $paid, 2),
            'paid_without_invoice' => round($paid - $invoiced, 2),
        ];

        // Un saldo positivo dentro de la tolerancia se considera saldado.
        $summary = ['total' => $total, 'invoiced' => $invoiced, 'paid' => $paid];
        $toleranceApplied = [];
        foreach ($differences as $key => $difference) {
            $withinTolerance = $difference > 0 && $difference <= PurchaseOrder::AMOUNT_TOLERANCE;
            $summary[$key] = $withinTolerance || $difference < 0 ? 0.0 : $difference;
            if ($withinTolerance) {
                $toleranceApplied[$key] = $difference;
            }
        }

        $summary['tolerance'] = PurchaseOrder::AMOUNT_TOLERANCE;
        $summary['tolerance_applied'] = $toleranceApplied;

        return $summary;
    }
}
