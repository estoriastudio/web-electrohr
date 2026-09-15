<?php

namespace App\Exports;

use App\Models\WorkerAttendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WorkerAttendanceExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly ?int $responsibleUserId,
    ) {
    }

    public function query(): Builder
    {
        return WorkerAttendance::query()
            ->with([
                'worker:id,first_name,last_name',
                'workerGroup:id,name,project_work_id',
                'workerGroup.projectWork:id,name,supervisor_user_id,resident_user_id',
            ])
            ->whereBetween('date', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay(),
            ])
            ->when($this->responsibleUserId, function (Builder $query) {
                $query->whereHas('workerGroup.projectWork', function (Builder $projectWorkQuery) {
                    $projectWorkQuery->where('supervisor_user_id', $this->responsibleUserId)
                        ->orWhere('resident_user_id', $this->responsibleUserId);
                });
            })
            ->orderBy('worker_group_id')
            ->orderBy('date')
            ->orderBy('worker_id');
    }

    public function headings(): array
    {
        return [
            'Cuadrilla',
            'Obra',
            'Fecha',
            'Trabajador',
            'Puesto',
            'Estatus',
            'Motivo de inasistencia',
            'Horas extra',
            'Notas',
        ];
    }

    public function map($attendance): array
    {
        return [
            $attendance->workerGroup?->name ?? '',
            $attendance->workerGroup?->projectWork?->name ?? '',
            $attendance->date?->format('d/m/Y') ?? '',
            trim(($attendance->worker?->first_name ?? '') . ' ' . ($attendance->worker?->last_name ?? '')),
            $attendance->role ?? '',
            $attendance->attended ? 'Show' : 'No show',
            $this->absenceReasonLabel($attendance->absence_reason),
            (float) $attendance->overtime_hours,
            $attendance->notes ?? '',
        ];
    }

    private function absenceReasonLabel(?string $reason): string
    {
        return match ($reason) {
            'absence' => 'Inasistencia',
            'rest' => 'Descanso',
            'incapacity' => 'Incapacidad',
            'permission' => 'Permiso',
            default => '',
        };
    }
}