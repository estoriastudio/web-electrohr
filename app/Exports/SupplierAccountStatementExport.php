<?php

namespace App\Exports;

use App\Services\SupplierAccountStatementService;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class SupplierAccountStatementExport extends DefaultValueBinder implements FromGenerator, WithHeadings, ShouldAutoSize, WithCustomValueBinder, WithEvents
{
    private const CURRENCY_FORMATS = [
        'MXN' => '"$"#,##0.00',
        'USD' => '"US$"#,##0.00',
        'EUR' => '"€"#,##0.00',
    ];

    // Columnas K a Q de las filas de detalle: total fiscal, nota de credito, liquido, pagado, saldo y pagos por regularizar/conciliar.
    private const DETAIL_AMOUNT_COLUMNS = ['K', 'L', 'M', 'N', 'O', 'P', 'Q'];

    private array $moneyCells = [];

    private ?int $filtersLine = null;

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
        $this->moneyCells = [];
        $this->filtersLine = null;
        $line = 1; // La fila 1 son los encabezados.
        $money = function (string $currency, array $columns) use (&$line) {
            $this->moneyCells[] = [$line, $columns, $currency];
        };
        foreach ($this->statement->scopeRows($this->filters) as $row) {
            $this->statement->accumulate($summary, $row);
            if (!$this->statement->matchesStatus($row, $this->filters)) {
                continue;
            }
            $line++;
            $money($row['currency'], self::DETAIL_AMOUNT_COLUMNS);
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
        // Maatwebsite omite las filas vacias, por eso no se cuentan.
        $line++;
        $this->filtersLine = $line;
        yield ['Filtros', json_encode($this->filters, JSON_UNESCAPED_UNICODE)];
        $line++;
        yield ['Moneda', 'Facturado liquido', 'Pagado', 'Pendiente confirmado', 'Vencido confirmado', 'Sin factura', 'Pago por conciliar', 'Facturas', 'Registros', 'Saldos completos'];
        foreach ($summary as $currency => $totals) {
            $line++;
            $money($currency, ['B', 'C', 'D', 'E', 'F', 'G']);
            yield [
                $currency, $totals['invoiced'] / 100, $totals['paid'] / 100,
                $totals['pending'] / 100, $totals['overdue'] / 100, $totals['unregularized'] / 100,
                $totals['unreconciled'] / 100, $totals['invoices'], $totals['records'],
                $totals['complete'] ? 'Si' : 'No: relaciones por conciliar',
            ];
            foreach ($totals['statuses'] as $status => $detail) {
                $line++;
                $money($currency, ['D']);
                yield [$currency, SupplierAccountStatementService::STATUSES[$status], $detail['count'], $detail['amount'] / 100];
            }
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                // Se combina para que el texto largo de filtros no ensanche la columna B.
                if ($this->filtersLine) {
                    $sheet->mergeCells('B' . $this->filtersLine . ':J' . $this->filtersLine);
                }
                foreach ($this->moneyCells as [$line, $columns, $currency]) {
                    $format = self::CURRENCY_FORMATS[$currency] ?? self::CURRENCY_FORMATS['MXN'];
                    foreach ($columns as $column) {
                        $sheet->getStyle($column . $line)->getNumberFormat()->setFormatCode($format);
                    }
                }
            },
        ];
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