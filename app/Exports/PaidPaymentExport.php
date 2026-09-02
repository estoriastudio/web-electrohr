<?php

namespace App\Exports;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaidPaymentExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly string $startDate,
        private readonly string $endDate,
    ) {
    }

    public function query(): Builder
    {
        return Payment::query()
            ->with([
                'milestone.invoices:id,folio',
                'milestone.purchaseOrder:id,folio,supplier_id,currency',
                'milestone.purchaseOrder.supplier:id,rfc_name,commercial_name',
            ])
            ->has('milestone.purchaseOrder')
            ->where('status', 'pagado')
            ->whereBetween('payment_date', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay(),
            ])
            ->orderBy('payment_date')
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'Factura',
            'Folio Pago',
            'Orden de Compra',
            'Proveedor',
            'Monto',
            'Moneda',
            'Fecha Pago',
        ];
    }

    public function map($payment): array
    {
        $purchaseOrder = $payment->milestone?->purchaseOrder;
        $supplier = $purchaseOrder?->supplier;

        return [
            $payment->milestone?->invoices->pluck('folio')->filter()->implode(', ') ?: 'Sin factura',
            $payment->folio,
            $purchaseOrder?->folio ?? '',
            $supplier?->rfc_name ?: $supplier?->commercial_name ?: '',
            (float) $payment->amount,
            $purchaseOrder?->currency ?? '',
            $payment->payment_date?->format('d/m/Y') ?? '',
        ];
    }
}