<?php

namespace App\Exports;

use App\Models\PurchaseOrderInvoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PurchaseOrderInvoiceExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly string $paymentCondition,
    ) {
    }

    public function query(): Builder
    {
        return PurchaseOrderInvoice::query()
            ->with([
                    'purchaseOrder:id,folio,supplier_id,elaborated_by,tax_rate,isr_rate,retention_iva_rate,retention_isr_rate,cedular_rate',
                'purchaseOrder.supplier:id,rfc_name,commercial_name',
                'purchaseOrder.milestones:id,purchase_order_id,payment_condition',
                'purchaseOrder.items:id,purchase_order_id,quantity,unit_price',
                'milestones.payments:id,milestone_id,payment_date',
            ])
            ->whereBetween('attached_at', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay(),
            ])
            ->when($this->paymentCondition !== 'ambas', function (Builder $query) {
                $query->whereHas('purchaseOrder.milestones', function (Builder $milestones) {
                    $milestones->where('payment_condition', $this->paymentCondition);
                });
            })
            ->orderBy('attached_at')
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'Estatus',
            'Fecha carga',
            'Emisión',
            'Vencimiento',
            'Fecha de pago',
            'Folio',
            'OC',
            'Tipo',
            'Proveedor',
            'Comprador',
            'Alcance líquido',
            'Subtotal',
            'IVA',
            'Retenciones',
            'Total',
            'Moneda',
        ];
    }

    public function map($invoice): array
    {
        $statusLabels = [
            PurchaseOrderInvoice::STATUS_EN_PROCESO => 'En Proceso',
            PurchaseOrderInvoice::STATUS_ACEPTADA => 'Aceptada',
            PurchaseOrderInvoice::STATUS_RECHAZADA => 'Rechazada',
        ];
        $purchaseOrder = $invoice->purchaseOrder;
        $supplierName = $purchaseOrder?->supplier?->commercial_name ?: $purchaseOrder?->supplier?->rfc_name;
        $paymentConditions = $purchaseOrder?->milestones
            ->pluck('payment_condition')
            ->filter()
            ->unique()
            ->map(fn (string $condition) => $condition === 'contado' ? 'Contado' : 'Crédito')
            ->implode(', ');
        $paymentDates = $invoice->milestones
            ->flatMap(fn ($milestone) => $milestone->payments)
            ->filter(fn ($payment) => $payment->payment_date)
            ->sortBy(fn ($payment) => $payment->payment_date->format('Y-m-d'))
            ->map(fn ($payment) => $payment->payment_date->format('d/m/Y'))
            ->unique()
            ->implode(', ');
        $netScope = (float) ($invoice->net_scope ?? $invoice->amount ?? 0);

        return [
            $statusLabels[$invoice->status] ?? 'En Proceso',
            $invoice->attached_at?->format('d/m/Y H:i') ?? $invoice->created_at?->format('d/m/Y H:i'),
            $invoice->issue_date?->format('d/m/Y') ?? '',
            $invoice->due_date?->format('d/m/Y') ?? '',
            $paymentDates,
            $invoice->folio ?: ('FACT-' . $invoice->id),
            $purchaseOrder?->folio ?? $purchaseOrder?->id ?? '',
            $paymentConditions,
            $supplierName ?? '',
            $purchaseOrder?->elaborated_by ?? '',
            $netScope,
            $purchaseOrder?->subtotal ?? 0,
            $purchaseOrder?->iva ?? 0,
            $purchaseOrder?->additional_taxes_amount ?? 0,
            $purchaseOrder?->total_with_iva ?? 0,
            $invoice->currency,
        ];
    }
}