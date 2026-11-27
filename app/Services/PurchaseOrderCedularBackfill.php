<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use Illuminate\Support\Facades\DB;

class PurchaseOrderCedularBackfill
{
    public function preview(): array
    {
        $report = [
            'eligible' => [],
            'conflicts' => [],
            'closed' => [],
            'excluded' => [],
        ];

        PurchaseOrder::withTrashed()
            ->whereNotNull('cedular_rate')
            ->where('cedular_rate', '>', 0)
            ->with(['items', 'milestones.payments', 'milestones.credits', 'invoices'])
            ->orderBy('folio')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$report): void {
                foreach ($orders as $order) {
                    $previous = round((float) $order->amount, 2);
                    $proposed = $this->newTotal($order);

                    if ($order->closed_at !== null) {
                        $report['closed'][] = $this->row($order, $previous, $proposed, 'OC cerrada');
                        continue;
                    }

                    $excludeReason = match (true) {
                        $order->deleted_at !== null => 'OC eliminada',
                        $order->archived_at !== null => 'OC archivada',
                        $order->status === 'autorizada' => 'OC autorizada',
                        $order->status !== 'emitida' => 'estado distinto de emitida',
                        default => null,
                    };
                    if ($excludeReason !== null) {
                        $report['excluded'][] = $this->row($order, $previous, $proposed, $excludeReason);
                        continue;
                    }

                    $conflict = $this->conflictDetails($order, $proposed);
                    if ($conflict !== []) {
                        $row = $this->row($order, $previous, $proposed, 'conflicto financiero');
                        $row['motivos_conflicto'] = $conflict;
                        $row['pagos_cambiarian'] = [];
                        $report['conflicts'][] = $row;
                        continue;
                    }

                    $row = $this->row($order, $previous, $proposed, 'eligible');
                    $row['pagos_cambiarian'] = $this->pendingPaymentChanges($order, $proposed);
                    $report['eligible'][] = $row;
                }
            });

        return $report;
    }

    public function apply(): array
    {
        $report = $this->preview();
        $applied = [];
        $errors = [];

        foreach ($report['eligible'] as $row) {
            try {
                $result = DB::transaction(function () use ($row): array {
                    $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($row['id']);

                    if ($order->deleted_at !== null
                        || $order->archived_at !== null
                        || $order->closed_at !== null
                        || $order->status !== 'emitida'
                        || (float) ($order->cedular_rate ?? 0) <= 0) {
                        return ['status' => 'omitida', 'reason' => 'los datos cambiaron antes de la ejecución'];
                    }

                    $order->load(['items', 'milestones.payments', 'milestones.credits', 'invoices']);
                    $proposed = $this->newTotal($order);
                    $conflict = $this->conflictDetails($order, $proposed);
                    if ($conflict !== []) {
                        return ['status' => 'conflicto', 'reason' => implode('; ', $conflict)];
                    }

                    $previous = round((float) $order->amount, 2);
                    $changed = abs($previous - $proposed) >= 0.005;
                    $order->recalculateAmount();
                    $order->refresh();
                    $paymentChanged = $this->pendingPaymentChanges($order, $proposed) !== [];

                    return [
                        'status' => 'actualizada',
                        'changed' => $changed || $paymentChanged,
                        'previous' => $previous,
                        'proposed' => round((float) $order->amount, 2),
                    ];
                });

                $row['resultado'] = $result['status'];
                if (isset($result['reason'])) {
                    $row['motivo'] = $result['reason'];
                }
                $applied[] = $row;
            } catch (\Throwable $error) {
                $row['resultado'] = 'error';
                $row['motivo'] = $error->getMessage();
                $errors[] = $row;
            }
        }

        return [
            'preview' => $report,
            'applied' => $applied,
            'errors' => $errors,
        ];
    }

    public function newTotal(PurchaseOrder $order): float
    {
        $subtotal = (float) $order->items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);
        $taxAmount = round($subtotal * ((float) ($order->tax_rate ?? 0) / 100), 2);
        $isrAmount = round($subtotal * ((float) ($order->isr_rate ?? 0) / 100), 2);
        $retentionIvaAmount = round($subtotal * ((float) ($order->retention_iva_rate ?? 0) / 100), 2);
        $retentionIsrAmount = round($subtotal * ((float) ($order->retention_isr_rate ?? 0) / 100), 2);
        $cedularAmount = round($subtotal * ((float) ($order->cedular_rate ?? 0) / 100), 2);

        return round(
            $subtotal + $taxAmount - $isrAmount - $retentionIvaAmount - $retentionIsrAmount - $cedularAmount,
            2
        );
    }

    private function conflictDetails(PurchaseOrder $order, float $newTotal): array
    {
        $paid = round((float) $order->payments()
            ->where('payments.status', 'pagado')
            ->sum(DB::raw('COALESCE(payments.paid_amount, payments.amount)')), 2);
        $authorized = round((float) $order->payments()
            ->where('payments.status', 'autorizado')
            ->sum('payments.amount'), 2);
        $invoiced = round((float) $order->invoices()
            ->where('status', PurchaseOrderInvoice::STATUS_ACEPTADA)
            ->get()
            ->sum(fn (PurchaseOrderInvoice $invoice) => (float) ($invoice->net_scope
                ?? ((float) $invoice->amount - (float) ($invoice->credit_note_amount ?? 0)))), 2);

        $details = [];
        if ($paid > $newTotal) {
            $details[] = 'pagado ' . number_format($paid, 2) . ' supera nuevo total ' . number_format($newTotal, 2);
        }
        if ($authorized > $newTotal) {
            $details[] = 'autorizado ' . number_format($authorized, 2) . ' supera nuevo total ' . number_format($newTotal, 2);
        }
        if ($invoiced > $newTotal) {
            $details[] = 'facturado aceptado ' . number_format($invoiced, 2) . ' supera nuevo total ' . number_format($newTotal, 2);
        }

        return $details;
    }

    private function pendingPaymentChanges(PurchaseOrder $order, float $orderTotal): array
    {
        $milestones = $order->milestones
            ->where('value_type', 'porcentaje')
            ->sortBy('id')
            ->values();
        if ($milestones->isEmpty()) {
            return [];
        }

        $percentageTotal = (float) $milestones->sum('value');
        $previousAmounts = 0.0;
        $changes = [];

        foreach ($milestones as $milestone) {
            $isLast = abs($percentageTotal - 100) < 0.0001
                && $milestone->id === $milestones->last()?->id;
            $targetAmount = $isLast
                ? round($orderTotal - $previousAmounts, 2)
                : round($orderTotal * (float) $milestone->value / 100, 2);
            $previousAmounts = round($previousAmounts + $targetAmount, 2);

            $payments = $milestone->payments
                ->where('status', '!=', Payment::STATUS_SUSTITUIDO)
                ->sortBy('id')
                ->values();
            $pending = $payments->where('status', 'por_autorizar')->values();
            if ($pending->isEmpty()) {
                continue;
            }

            $lockedAmount = round((float) $payments
                ->where('status', '!=', 'por_autorizar')
                ->sum('amount') + (float) $milestone->credits->sum('amount'), 2);
            $pendingTarget = max(0, round($targetAmount - $lockedAmount, 2));
            $pendingCurrent = (float) $pending->sum('amount');
            $remaining = $pendingTarget;

            foreach ($pending as $index => $payment) {
                $isLastPayment = $index === $pending->count() - 1;
                $amount = $isLastPayment
                    ? $remaining
                    : ($pendingCurrent > 0
                        ? round($pendingTarget * ((float) $payment->amount / $pendingCurrent), 2)
                        : 0.0);
                $remaining = round($remaining - $amount, 2);
                if (abs((float) $payment->amount - $amount) > 0.009) {
                    $changes[] = [
                        'payment_id' => $payment->id,
                        'hito_id' => $milestone->id,
                        'anterior' => round((float) $payment->amount, 2),
                        'propuesto' => round($amount, 2),
                    ];
                }
            }
        }

        return $changes;
    }

    private function row(PurchaseOrder $order, float $previous, float $proposed, string $reason): array
    {
        return [
            'id' => $order->id,
            'folio' => $order->folio ?: ('OC-' . $order->id),
            'status' => $order->status,
            'anterior' => $previous,
            'propuesto' => $proposed,
            'motivo' => $reason,
        ];
    }
}
