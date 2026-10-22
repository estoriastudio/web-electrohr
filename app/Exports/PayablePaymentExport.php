<?php

namespace App\Exports;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayablePaymentExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
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
            ->where('status', 'autorizado')
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

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:G1');
        $sheet->getRowDimension(1)->setRowHeight(24);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F4E78'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'BFBFBF'],
                    ],
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }
}
