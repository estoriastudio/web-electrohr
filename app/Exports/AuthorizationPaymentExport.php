<?php

namespace App\Exports;

use App\Models\Payment;
use App\Services\PurchaseOrderMilestoneChecklist;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AuthorizationPaymentExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithMapping, WithStrictNullComparison, WithStyles
{
    public function __construct(
        private readonly Collection $payments,
        private readonly array $checklists,
        private readonly Carbon $urgent_date,
    ) {}

    public function collection(): Collection
    {
        return $this->payments;
    }

    public function headings(): array
    {
        return [
            'Semáforo', 'Orden de Compra', 'Condición de pago', 'Proveedor', 'Proyecto / Obra', 'Monto', 'Moneda',
            'Importe original', 'Origen', 'Tipo de hito', 'Condición de pago del hito', 'Pago / Vencimiento',
            'Entrega confirmada', 'Factura aceptada', 'NOTA',
        ];
    }

    public function map($payment): array
    {
        $milestone = $payment->milestone;
        $order = $milestone->purchaseOrder;
        $supplier = $order->supplier;
        $due_date = $milestone->due_date;
        $traffic_light = $due_date && $due_date->lt(Carbon::today())
            ? 'Vencido'
            : ($due_date && $due_date->lte($this->urgent_date) ? 'Próximo a vencer' : 'Al día');
        $conditions = $order->milestones->pluck('payment_condition')->filter()->unique()->values();
        $order_condition = $conditions->count() > 1
            ? 'Ambas'
            : ($conditions->first() === 'contado' ? 'Contado' : 'Crédito');
        $project = $order->projectRelation?->name ?? $order->project ?? null;
        $work = $order->workRelation?->name ?? $order->site ?? null;
        $original_amount = $payment->original_amount !== null
            && abs((float) $payment->original_amount - (float) $payment->amount) > 0.004
            ? (float) $payment->original_amount
            : null;
        $origin = match ($payment->origin) {
            Payment::ORIGIN_REST => 'Resto'.($payment->sourcePayment ? ' de '.$payment->sourcePayment->folio : ''),
            Payment::ORIGIN_REPLACEMENT => 'Saldo del hito',
            default => '',
        };
        $checklist = $this->checklists[$payment->milestone_id];
        $requirements = collect($checklist['requirements'])->keyBy('key')->map(function ($requirement) {
            return ! ($requirement['applicable'] ?? true)
                ? 'No aplica'
                : ($requirement['met'] ? 'Cumplido' : ($requirement['required'] ? 'Pendiente' : 'Posterior'));
        });

        return [
            $traffic_light,
            (string) ($order->folio ?? '—'),
            $order_condition,
            $supplier->rfc_name ?? $supplier->commercial_name ?? '—',
            implode("\n", array_filter([$project, $work], fn ($value) => (bool) $value)) ?: '—',
            (float) $payment->amount,
            $order->currency,
            $original_amount,
            $origin,
            $milestone->is_advance ? 'Anticipo' : 'Pago regular',
            ($milestone->payment_condition ?? 'credito') === 'contado' ? 'Contado' : 'Crédito',
            $payment->payment_date->format('d/m/Y'),
            $requirements[PurchaseOrderMilestoneChecklist::KEY_DELIVERY],
            $requirements[PurchaseOrderMilestoneChecklist::KEY_INVOICE],
            $checklist['complete'] ? 'LISTO PARA APROBAR' : 'NO APROBAR',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:O1');
        $sheet->getStyle('A:O')->getAlignment()->setWrapText(true);

        return [1 => ['font' => ['bold' => true]]];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
