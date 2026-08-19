<?php

namespace App\Exports;

use App\Models\Worker;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WorkerExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function collection()
    {
        return Worker::with(['projectWork', 'positionCategory'])->orderBy('last_name')->orderBy('first_name')->get();
    }

    public function headings(): array
    {
        return [
            'No. de cuenta',
            'Nombre(s)',
            'Apellidos',
            'Puesto',
            'Sueldo semanal',
            'Obra base',
            'Fecha de alta',
            'Estatus',
        ];
    }

    public function map($worker): array
    {
        return [
            $worker->employee_code,
            $worker->first_name,
            $worker->last_name,
            $worker->positionCategory?->name,
            $worker->weekly_salary,
            $worker->projectWork?->name,
            $worker->hire_date?->format('d/m/Y'),
            $worker->status,
        ];
    }
}