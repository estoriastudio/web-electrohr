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
                'purchaseOrder:id,folio,supplier_id,elaborated_by',
                'purchaseOrder.supplier:id,rfc_name,commercial_name',
                'purchaseOrder.milestones:id,purchase_order_id,payment_condition',
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
            'Folio',
            'OC',
            'Tipo',
            'Proveedor',
            'Comprador',
            'Alcance líquido',
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
        $netScope = (float) ($invoice->net_scope ?? $invoice->amount ?? 0);

        return [
            $statusLabels[$invoice->status] ?? 'En Proceso',
            $invoice->attached_at?->format('d/m/Y H:i') ?? $invoice->created_at?->format('d/m/Y H:i'),
            $invoice->issue_date?->format('d/m/Y') ?? '',
            $invoice->due_date?->format('d/m/Y') ?? '',
            $invoice->folio ?: ('FACT-' . $invoice->id),
            $purchaseOrder?->folio ?? $purchaseOrder?->id ?? '',
            $paymentConditions,
            $supplierName ?? '',
            $purchaseOrder?->elaborated_by ?? '',
            $netScope,
            $invoice->currency,
        ];
    }
}