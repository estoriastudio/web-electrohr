<?php

namespace App\Exports;

use App\Models\Worker;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WorkerExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private bool $includeAmounts = true) {}

    public function collection()
    {
        return Worker::with(['projectWork', 'positionCategory'])->orderBy('last_name')->orderBy('first_name')->get();
    }

    public function headings(): array
    {
        $headings = [
            'No. de empleado (NSS)',
            'No. de cuenta bancaria',
            'Nombre(s)',
            'Apellidos',
            'Puesto',
            'Sueldo semanal',
            'Obra base',
            'Fecha de alta',
            'Estatus',
        ];

        // El sueldo solo se exporta a quien tiene seeAmounts (HR-020).
        return $this->includeAmounts ? $headings : array_values(array_diff_key($headings, [5 => true]));
    }

    public function map($worker): array
    {
        $row = [
            $worker->nss,
            $worker->employee_code,
            $worker->first_name,
            $worker->last_name,
            $worker->positionCategory?->name,
            $worker->weekly_salary,
            $worker->projectWork?->name,
            $worker->hire_date?->format('d/m/Y'),
            $worker->status,
        ];

        return $this->includeAmounts ? $row : array_values(array_diff_key($row, [5 => true]));
    }
}