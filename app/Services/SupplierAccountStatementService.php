<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SupplierAccountStatementService
{
    public const STATUSES = [
        'pendiente' => 'Pendiente',
        'pagado' => 'Pagado',
        'vencido' => 'Vencido',
        'sin_factura' => 'Pago pendiente de regularizar',
        'conciliacion' => 'Por conciliar',
    ];

    public function orderQuery(array $filters = []): Builder
    {
        return PurchaseOrder::query()
            ->when($filters['order'] ?? null, fn ($query, $value) => $query->where('folio', 'like', '%' . $value . '%'))
            ->when($filters['supplier_id'] ?? null, fn ($query, $value) => $query->where('supplier_id', $value))
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['buyer_id'] ?? null, fn ($query, $value) => $query->where('buyer_id', $value))
            ->with([
                'supplier', 'projectRelation', 'buyer', 'milestones.payments',
                'invoices.milestones', 'invoices.paymentAllocations',
            ]);
    }

    public function rows(array $filters = []): Generator
    {
        foreach ($this->orderQuery($filters)->lazyById(100) as $order) {
            $applications = $order->invoices->flatMap(fn ($invoice) => $invoice->paymentAllocations)->toArray();
            foreach ($this->calculateOrder($order, $applications)['rows'] as $row) {
                if (!empty($filters['currency']) && $row['currency'] !== $filters['currency']) {
                    continue;
                }
                if (!empty($filters['status']) && $row['status'] !== $filters['status']) {
                    continue;
                }
                yield $row;
            }
        }
    }

    public function report(array $filters, int $page, int $perPage): array
    {
        $summary = [];
        $items = [];
        $total = 0;
        $offset = ($page - 1) * $perPage;
        foreach ($this->rows($filters) as $row) {
            $this->accumulate($summary, $row);
            if ($total >= $offset && count($items) < $perPage) {
                $items[] = $row;
            }
            $total++;
        }
        return [
            'summary' => $summary,
            'rows' => new LengthAwarePaginator($items, $total, $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]),
        ];
    }

    public function purchaseOrderSummary(PurchaseOrder $order): array
    {
        $order->loadMissing(['supplier', 'projectRelation', 'buyer', 'milestones.payments', 'invoices.milestones', 'invoices.paymentAllocations']);
        $result = $this->calculateOrder($order, $order->invoices->flatMap(fn ($invoice) => $invoice->paymentAllocations)->toArray());
        return array_merge($result, ['summary' => $this->summarize($result['rows'])]);
    }

    public function calculateOrder(PurchaseOrder $order, array $applications = []): array
    {
        $invoices = $order->invoices
            ->where('status', PurchaseOrderInvoice::STATUS_ACEPTADA)
            ->keyBy('id');
        $payments = $order->milestones->flatMap(fn ($milestone) => $milestone->payments)
            ->where('status', 'pagado')->keyBy('id')->sortKeys();
        $capacities = $invoices->mapWithKeys(fn ($invoice) => [$invoice->id => $this->cents(
            $invoice->net_scope ?? ((float) $invoice->amount - (float) ($invoice->credit_note_amount ?? 0))
        )])->all();
        $applied = array_fill_keys($invoices->keys()->all(), 0);
        $used = array_fill_keys($payments->keys()->all(), 0);
        $uncertain = [];
        $candidates = [];
        $explicitPayments = [];
        $uncertainPayments = [];
        $effectiveApplications = [];

        foreach ($payments as $payment) {
            $candidates[$payment->id] = $invoices->filter(fn ($invoice) =>
                $invoice->milestones->contains('id', $payment->milestone_id)
                && ($invoice->currency ?: $order->currency) === $order->currency
            )->keys()->all();
            if (!$candidates[$payment->id] && $invoices->contains(fn ($invoice) => $invoice->milestones->contains('id', $payment->milestone_id))) {
                $uncertainPayments[$payment->id] = true;
            }
        }

        $invoiceTotals = [];
        $paymentTotals = [];
        foreach ($applications as $application) {
            $invoiceId = (int) $application['purchase_order_invoice_id'];
            $paymentId = (int) $application['payment_id'];
            $explicitPayments[$paymentId] = true;
            if (!$invoices->has($invoiceId) || !$payments->has($paymentId)) {
                continue;
            }
            $amount = $this->cents($application['amount']);
            $invoiceTotals[$invoiceId] = ($invoiceTotals[$invoiceId] ?? 0) + $amount;
            $paymentTotals[$paymentId] = ($paymentTotals[$paymentId] ?? 0) + $amount;
        }

        foreach ($applications as $application) {
            $invoiceId = (int) $application['purchase_order_invoice_id'];
            $paymentId = (int) $application['payment_id'];
            if (!$invoices->has($invoiceId) || !$payments->has($paymentId)) {
                continue;
            }
            $amount = $this->cents($application['amount']);
            if ($amount <= 0 || !in_array($invoiceId, $candidates[$paymentId], true)
                || $invoiceTotals[$invoiceId] > $capacities[$invoiceId]
                || $paymentTotals[$paymentId] > $this->cents($payments[$paymentId]->amount)) {
                $uncertain[$invoiceId] = true;
                $uncertainPayments[$paymentId] = true;
                continue;
            }
            $applied[$invoiceId] += $amount;
            $used[$paymentId] += $amount;
            $effectiveApplications[] = ['payment_id' => $paymentId, 'purchase_order_invoice_id' => $invoiceId, 'amount' => $amount / 100];
        }

        foreach ($payments as $payment) {
            $remaining = $this->cents($payment->amount) - $used[$payment->id];
            if ($remaining <= 0) {
                continue;
            }
            if (isset($explicitPayments[$payment->id])) {
                continue;
            }
            if (count($candidates[$payment->id]) > 1) {
                $uncertainPayments[$payment->id] = true;
                foreach ($candidates[$payment->id] as $invoiceId) {
                    $uncertain[$invoiceId] = true;
                }
            } elseif (count($candidates[$payment->id]) === 1) {
                $invoiceId = $candidates[$payment->id][0];
                if (!isset($uncertain[$invoiceId])) {
                    $amount = min($remaining, max(0, $capacities[$invoiceId] - $applied[$invoiceId]));
                    $applied[$invoiceId] += $amount;
                    $used[$payment->id] += $amount;
                    if ($amount > 0) {
                        $effectiveApplications[] = ['payment_id' => $payment->id, 'purchase_order_invoice_id' => $invoiceId, 'amount' => $amount / 100];
                    }
                }
            }
        }

        $rows = collect();
        foreach ($invoices as $invoice) {
            $currency = $invoice->currency ?: $order->currency;
            $needsReconciliation = isset($uncertain[$invoice->id]) || $capacities[$invoice->id] < 0
                || $currency !== $order->currency;
            $balance = $needsReconciliation ? null : $capacities[$invoice->id] - $applied[$invoice->id];
            $status = $needsReconciliation ? 'conciliacion' : ($balance === 0 ? 'pagado' : (
                $invoice->due_date && $invoice->due_date->toDateString() < today()->toDateString()
                    ? 'vencido' : 'pendiente'
            ));
                    $appliedPaymentIds = collect($effectiveApplications)->where('purchase_order_invoice_id', $invoice->id)->pluck('payment_id')->all();
            $rows->push(array_merge($this->orderFields($order), [
                'key' => 'invoice-' . $invoice->id,
                'type' => 'invoice',
                'invoice_id' => $invoice->id,
                'payment_id' => null,
                'folio' => $invoice->folio ?: ('FACT-' . $invoice->id),
                'issue_date' => $invoice->issue_date?->format('d/m/Y'),
                'due_date' => $invoice->due_date?->format('d/m/Y'),
                'payment_date' => null,
                'currency' => $currency,
                'gross' => $this->cents($invoice->amount),
                'credit_note' => $this->cents($invoice->credit_note_amount ?? 0),
                'invoiced' => $capacities[$invoice->id],
                'paid' => $applied[$invoice->id],
                'balance' => $balance,
                'unregularized' => 0,
                'unreconciled' => 0,
                'status' => $status,
                'receipt_payment_ids' => $payments->filter(fn ($payment) =>
                    in_array($payment->id, $appliedPaymentIds, true)
                    && $payment->spei_receipt_path
                )->keys()->all(),
            ]));
        }

        foreach ($payments as $payment) {
            $remaining = $this->cents($payment->amount) - $used[$payment->id];
            if ($remaining <= 0) {
                continue;
            }
            $hasInvoice = isset($uncertainPayments[$payment->id]);
            $rows->push(array_merge($this->orderFields($order), [
                'key' => 'payment-' . $payment->id,
                'type' => 'payment',
                'invoice_id' => null,
                'payment_id' => $payment->id,
                'folio' => null,
                'issue_date' => null,
                'due_date' => null,
                'payment_date' => $payment->payment_date?->format('d/m/Y'),
                'currency' => $order->currency,
                'gross' => 0,
                'credit_note' => 0,
                'invoiced' => 0,
                'paid' => $remaining,
                'balance' => null,
                'unregularized' => $hasInvoice ? 0 : $remaining,
                'unreconciled' => $hasInvoice ? $remaining : 0,
                'status' => $hasInvoice ? 'conciliacion' : 'sin_factura',
                'receipt_payment_ids' => $payment->spei_receipt_path ? [$payment->id] : [],
            ]));
        }

        return [
            'rows' => $rows,
            'applications' => $effectiveApplications,
            'economic' => [
                'total' => $this->cents($order->amount),
                'invoiced' => $invoices->contains(fn ($invoice) => ($invoice->currency ?: $order->currency) !== $order->currency)
                    ? null : array_sum($capacities),
                'pending_invoice' => $invoices->contains(fn ($invoice) => ($invoice->currency ?: $order->currency) !== $order->currency)
                    ? null : $this->cents($order->amount) - array_sum($capacities),
                'paid' => $payments->sum(fn ($payment) => $this->cents($payment->amount)),
                'pending_order' => $this->cents($order->amount) - $payments->sum(fn ($payment) => $this->cents($payment->amount)),
                'pending_invoices' => $uncertain || $rows->contains('status', 'conciliacion')
                    ? null : $rows->where('type', 'invoice')->sum('balance'),
            ],
        ];
    }

    public function summarize(Collection $rows): array
    {
        $summary = [];
        foreach ($rows as $row) {
            $this->accumulate($summary, $row);
        }
        return $summary;
    }

    public function accumulate(array &$summary, array $row): void
    {
        $currency = $row['currency'];
        if (!isset($summary[$currency])) {
            $summary[$currency] = [
                'invoiced' => 0, 'paid' => 0, 'pending' => 0, 'overdue' => 0,
                'unregularized' => 0, 'unreconciled' => 0, 'invoices' => 0, 'records' => 0,
                'complete' => true, 'statuses' => [],
            ];
            foreach (self::STATUSES as $status => $label) {
                $summary[$currency]['statuses'][$status] = ['count' => 0, 'amount' => 0];
            }
        }
        foreach (['invoiced', 'paid', 'unregularized', 'unreconciled'] as $field) {
            $summary[$currency][$field] += $row[$field];
        }
        $summary[$currency]['records']++;
        $summary[$currency]['invoices'] += $row['type'] === 'invoice' ? 1 : 0;
        $summary[$currency]['pending'] += $row['balance'] ?? 0;
        $summary[$currency]['overdue'] += $row['status'] === 'vencido' ? $row['balance'] : 0;
        $summary[$currency]['complete'] = $summary[$currency]['complete'] && $row['status'] !== 'conciliacion';
        $summary[$currency]['statuses'][$row['status']]['count']++;
        $summary[$currency]['statuses'][$row['status']]['amount'] += match ($row['status']) {
            'pendiente', 'vencido' => $row['balance'],
            'pagado' => $row['invoiced'],
            default => $row['paid'],
        };
    }

    private function cents($amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function orderFields(PurchaseOrder $order): array
    {
        return [
            'order_id' => $order->id,
            'order_folio' => $order->folio ?: ('OC-' . $order->id),
            'supplier' => $order->supplier?->rfc_name ?: ($order->supplier?->commercial_name ?: 'Proveedor no disponible'),
            'project' => $order->projectRelation?->name ?: ($order->project ?: 'Sin proyecto'),
            'buyer' => $order->buyer?->name ?: ($order->elaborated_by ?: 'Responsable no asignado'),
        ];
    }
}