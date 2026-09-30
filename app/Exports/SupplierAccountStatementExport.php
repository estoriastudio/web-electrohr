<?php

namespace App\Exports;

use App\Services\SupplierAccountStatementService;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class SupplierAccountStatementExport extends DefaultValueBinder implements FromGenerator, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    public function __construct(private SupplierAccountStatementService $statement, private array $filters) {}

    public function headings(): array
    {
        return [
            'Tipo', 'Estatus', 'Orden de compra', 'Proveedor', 'Proyecto', 'Factura',
            'Emision', 'Vencimiento', 'Fecha de pago registrada', 'Moneda',
            'Total fiscal', 'Nota de credito', 'Importe factura (liquido)', 'Importe pagado',
            'Saldo pendiente', 'Pago pendiente de regularizar', 'Pago por conciliar', 'Responsable',
        ];
    }

    public function generator(): Generator
    {
        $summary = [];
        foreach ($this->statement->rows($this->filters) as $row) {
            $this->statement->accumulate($summary, $row);
            yield [
                $row['type'] === 'invoice' ? 'Factura' : 'Pago',
                SupplierAccountStatementService::STATUSES[$row['status']],
                $row['order_folio'], $row['supplier'], $row['project'], $row['folio'],
                $row['issue_date'], $row['due_date'], $row['payment_date'], $row['currency'],
                $row['type'] === 'invoice' ? $row['gross'] / 100 : null,
                $row['type'] === 'invoice' ? $row['credit_note'] / 100 : null,
                $row['type'] === 'invoice' ? $row['invoiced'] / 100 : null,
                $row['paid'] / 100,
                $row['balance'] === null ? null : $row['balance'] / 100,
                $row['unregularized'] / 100, $row['unreconciled'] / 100, $row['buyer'],
            ];
        }
        yield [];
        yield ['Filtros', json_encode($this->filters, JSON_UNESCAPED_UNICODE)];
        yield ['Moneda', 'Facturado liquido', 'Pagado', 'Pendiente confirmado', 'Vencido confirmado', 'Sin factura', 'Pago por conciliar', 'Facturas', 'Registros', 'Saldos completos'];
        foreach ($summary as $currency => $totals) {
            yield [
                $currency, $totals['invoiced'] / 100, $totals['paid'] / 100,
                $totals['pending'] / 100, $totals['overdue'] / 100, $totals['unregularized'] / 100,
                $totals['unreconciled'] / 100, $totals['invoices'], $totals['records'],
                $totals['complete'] ? 'Si' : 'No: relaciones por conciliar',
            ];
            foreach ($totals['statuses'] as $status => $detail) {
                yield [$currency, SupplierAccountStatementService::STATUSES[$status], $detail['count'], $detail['amount'] / 100];
            }
        }
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