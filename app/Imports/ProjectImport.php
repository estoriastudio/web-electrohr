<?php

namespace App\Imports;

use App\Models\Project;
use App\Models\ProjectWork;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ProjectImport implements ToModel, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    public function chunkSize(): int
    {
        return 200;
    }

    public function model(array $row): null
    {
        $obraName = trim($row['obra'] ?? '');

        // Se requiere al menos el nombre de la obra para procesar la fila
        if (empty($obraName)) {
            return null;
        }

        $projectName  = trim($row['proyecto']  ?? '');
        $clientName   = trim($row['cliente']   ?? '');

        // Se requiere proyecto para poder asociar la obra
        if (empty($projectName)) {
            return null;
        }

        // ── Buscar o crear el Proyecto ────────────────────────────────────────
        $project = Project::firstOrCreate(
            ['name' => $projectName],
            [
                'client_name' => $clientName ?: $projectName,
                'status'      => 'active',
            ]
        );

        // Si ya existía el proyecto pero no tenía cliente, actualizarlo
        if ($project->wasRecentlyCreated === false && empty($project->client_name) && $clientName) {
            $project->update(['client_name' => $clientName]);
        }

        // ── Parsear fecha (puede venir como serial de Excel o string) ─────────
        $startDate = $this->parseDate($row['fecha_inicio_contrato'] ?? null);

        // ── Buscar o crear la Obra ────────────────────────────────────────────
        $work = ProjectWork::firstOrNew([
            'project_id' => $project->id,
            'name'       => $obraName,
        ]);

        $work->contract_number     = trim($row['num_contrato']        ?? '') ?: null;
        $work->contract_start_date = $startDate;
        $work->contract_value      = trim($row['importe_contratado']  ?? '') ?: null;
        $work->resident            = trim($row['residente']           ?? '') ?: null;
        $work->supervisor          = trim($row['supervisor']          ?? '') ?: null;
        $work->currency            = strtoupper(trim($row['moneda']   ?? '')) ?: null;

        if (!$work->exists) {
            $work->status = 'active';
        }

        $work->save();

        return null;
    }

    private function parseDate(mixed $value): ?string
    {
        if (empty($value) || trim((string) $value) === '') {
            return null;
        }

        // Excel numeric serial date
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                    ->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        // String date
        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
