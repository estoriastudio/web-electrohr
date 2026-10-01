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
    private const TOLERANCE_CENTS = 50;

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
                'supplier', 'projectRelation', 'buyer', 'milestones.payments', 'invoices.milestones',
            ]);
    }

    public function rows(array $filters = []): Generator
    {
        foreach ($this->orderQuery($filters)->lazyById(100) as $order) {
            foreach ($this->calculateOrder($order)['rows'] as $row) {
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
        $order->loadMissing(['supplier', 'projectRelation', 'buyer', 'milestones.payments', 'invoices.milestones']);
        $result = $this->calculateOrder($order);
        return array_merge($result, ['summary' => $this->summarize($result['rows'])]);
    }

    public function calculateOrder(PurchaseOrder $order): array
    {
        $invoices = $order->invoices
            ->where('status', PurchaseOrderInvoice::STATUS_ACEPTADA)
            ->keyBy('id');
        $allPayments = $order->milestones->flatMap(fn ($milestone) => $milestone->payments);
        $payments = $allPayments->where('status', 'pagado')->keyBy('id')->sortKeys();
        $scheduled = [];
        foreach ($allPayments->where('status', '!=', 'rechazado') as $payment) {
            $scheduled[$payment->milestone_id] = ($scheduled[$payment->milestone_id] ?? 0) + $this->cents($payment->amount);
        }
        $positions = $order->milestones->sortBy('id')->values()->pluck('id')->flip()->map(fn ($index) => $index + 1);
        $capacities = $invoices->mapWithKeys(fn ($invoice) => [$invoice->id => $this->cents(
            $invoice->net_scope ?? ((float) $invoice->amount - (float) ($invoice->credit_note_amount ?? 0))
        )])->all();
        $applied = array_fill_keys($invoices->keys()->all(), 0);
        $appliedPayments = [];
        $used = array_fill_keys($payments->keys()->all(), 0);
        $flaggedInvoices = [];
        $flaggedPayments = [];
        $invoiceReasons = [];
        $paymentReasons = [];
        $invoiceRef = fn ($id) => $invoices[$id]->folio ?: ('FACT-' . $id);
        $usable = $invoices->filter(fn ($invoice) => $capacities[$invoice->id] >= 0
            && ($invoice->currency ?: $order->currency) === $order->currency);

        foreach ($payments as $payment) {
            $linked = $invoices->filter(fn ($invoice) => $invoice->milestones->contains('id', $payment->milestone_id));
            if ($linked->isNotEmpty() && $linked->intersectByKeys($usable)->isEmpty()) {
                $flaggedPayments[$payment->id] = true;
                $paymentReasons[$payment->id][] = $this->reason('payment_unusable_invoices', ['payment' => '#' . $payment->id]);
            }
        }

        // Facturas que comparten hitos se evaluan en conjunto: sus importes deben cotejar con los pagos del hito.
        foreach ($this->components($usable) as $group) {
            $groupInvoices = collect($group['invoices'])->map(fn ($id) => $invoices[$id])
                ->sortBy(fn ($invoice) => [$invoice->due_date?->toDateString() ?? '9999-12-31', $invoice->id])->values();
            $groupPayments = $payments->filter(fn ($payment) => in_array($payment->milestone_id, $group['milestones'], true));
            $scheduledTotal = array_sum(array_map(fn ($id) => $scheduled[$id] ?? 0, $group['milestones']));
            $invoicedTotal = $groupInvoices->sum(fn ($invoice) => $this->cents($invoice->amount));

            if ($scheduledTotal > 0 && $invoicedTotal > $scheduledTotal + self::TOLERANCE_CENTS) {
                $reason = $this->reason('invoices_exceed_payments', [
                    'milestones' => implode(', ', array_map(fn ($id) => 'Hito #' . ($positions[$id] ?? $id), $group['milestones'])),
                    'invoices' => $groupInvoices->map(fn ($invoice) => $invoiceRef($invoice->id))->implode(', '),
                    'invoiced' => $this->money($invoicedTotal, $order->currency),
                    'scheduled' => $this->money($scheduledTotal, $order->currency),
                ]);
                foreach ($groupInvoices as $invoice) {
                    $flaggedInvoices[$invoice->id] = true;
                    $invoiceReasons[$invoice->id][] = $reason;
                }
                foreach ($groupPayments as $payment) {
                    $flaggedPayments[$payment->id] = true;
                    $paymentReasons[$payment->id][] = $reason;
                }
                continue;
            }

            foreach ($groupPayments as $payment) {
                $remaining = $this->cents($payment->amount);
                foreach ($groupInvoices as $invoice) {
                    $take = min($remaining, max(0, $capacities[$invoice->id] - $applied[$invoice->id]));
                    if ($take <= 0) {
                        continue;
                    }
                    $applied[$invoice->id] += $take;
                    $used[$payment->id] += $take;
                    $remaining -= $take;
                    $appliedPayments[$invoice->id][] = $payment->id;
                }
            }
        }

        $rows = collect();
        foreach ($invoices as $invoice) {
            $currency = $invoice->currency ?: $order->currency;
            if ($capacities[$invoice->id] < 0) {
                $invoiceReasons[$invoice->id][] = $this->reason('negative_net', ['invoice' => $invoiceRef($invoice->id)]);
            }
            if ($currency !== $order->currency) {
                $invoiceReasons[$invoice->id][] = $this->reason('invoice_currency', ['invoice' => $invoiceRef($invoice->id), 'currency' => $currency, 'order' => $order->currency]);
            }
            $needsReconciliation = isset($flaggedInvoices[$invoice->id]) || $capacities[$invoice->id] < 0
                || $currency !== $order->currency;
            $balance = $needsReconciliation ? null : $capacities[$invoice->id] - $applied[$invoice->id];
            $status = $needsReconciliation ? 'conciliacion' : ($balance === 0 ? 'pagado' : (
                $invoice->due_date && $invoice->due_date->toDateString() < today()->toDateString()
                    ? 'vencido' : 'pendiente'
            ));
            $appliedPaymentIds = $appliedPayments[$invoice->id] ?? [];
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
                'review_reasons' => $needsReconciliation ? $this->uniqueReasons($invoiceReasons[$invoice->id] ?? []) : [],
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
            $hasInvoice = isset($flaggedPayments[$payment->id]);
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
                'review_reasons' => $hasInvoice ? $this->uniqueReasons($paymentReasons[$payment->id] ?? []) : [],
                'receipt_payment_ids' => $payment->spei_receipt_path ? [$payment->id] : [],
            ]));
        }

        return [
            'rows' => $rows,
            'economic' => [
                'total' => $this->cents($order->amount),
                'invoiced' => $invoices->contains(fn ($invoice) => ($invoice->currency ?: $order->currency) !== $order->currency)
                    ? null : array_sum($capacities),
                'pending_invoice' => $invoices->contains(fn ($invoice) => ($invoice->currency ?: $order->currency) !== $order->currency)
                    ? null : $this->cents($order->amount) - array_sum($capacities),
                'paid' => $payments->sum(fn ($payment) => $this->cents($payment->amount)),
                'pending_order' => $this->cents($order->amount) - $payments->sum(fn ($payment) => $this->cents($payment->amount)),
                'pending_invoices' => $rows->contains('status', 'conciliacion')
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

    private function reason(string $code, array $p): array
    {
        return match ($code) {
            'invoices_exceed_payments' => [
                'message' => "Las facturas {$p['invoices']} ({$p['milestones']}) suman {$p['invoiced']}, mas que los pagos del hito ({$p['scheduled']}).",
                'action' => 'Revisa los importes: corrige o rechaza la factura que no corresponda, o registra el pago faltante en el hito.',
            ],
            'payment_unusable_invoices' => [
                'message' => "El pago {$p['payment']} pertenece a un hito cuyas facturas tienen moneda distinta a la de la OC o un importe invalido.",
                'action' => 'Corrige la moneda o el importe de la factura ligada al hito.',
            ],
            'negative_net' => [
                'message' => "La nota de credito de la factura {$p['invoice']} es mayor que su importe.",
                'action' => 'Corrige el importe de la factura o de su nota de credito.',
            ],
            'invoice_currency' => [
                'message' => "La factura {$p['invoice']} esta en {$p['currency']} y la OC en {$p['order']}; no se convierten monedas.",
                'action' => 'Corrige la moneda de la factura o de la OC.',
            ],
        };
    }

    // Une facturas que comparten hitos para cotejar sus importes contra los pagos del conjunto.
    private function components(Collection $invoices): array
    {
        $groups = [];
        foreach ($invoices as $invoice) {
            $merged = ['invoices' => [$invoice->id], 'milestones' => $invoice->milestones->pluck('id')->all()];
            foreach ($groups as $key => $group) {
                if (array_intersect($group['milestones'], $merged['milestones'])) {
                    $merged['invoices'] = array_merge($merged['invoices'], $group['invoices']);
                    $merged['milestones'] = array_values(array_unique(array_merge($merged['milestones'], $group['milestones'])));
                    unset($groups[$key]);
                }
            }
            $groups[] = $merged;
        }
        return array_values($groups);
    }

    private function money(int $cents, string $currency): string
    {
        return $currency . ' $' . number_format($cents / 100, 2);
    }

    private function uniqueReasons(array $reasons): array
    {
        return array_values(array_unique($reasons, SORT_REGULAR));
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