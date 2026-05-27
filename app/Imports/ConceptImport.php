<?php

namespace App\Imports;

use App\Models\Concept;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ConceptImport implements ToModel, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    public function chunkSize(): int
    {
        return 500;
    }

    public function model(array $row): null
    {
        // WithHeadingRow normaliza los encabezados (minúsculas, sin acentos, guiones bajos)
        // "Codigo" → "codigo" | "Descripción" → "descripcion" | "Costo" → "costo"
        $code = strtoupper(trim($row['codigo'] ?? $row['code'] ?? ''));

        // Se requiere código para procesar la fila
        if (empty($code)) {
            return null;
        }

        $description = trim($row['descripcion'] ?? $row['description'] ?? $row['descripción'] ?? '');
        $unit        = trim($row['unidad']      ?? $row['unit']        ?? '');
        $cost        = $row['costo']            ?? $row['unit_price']  ?? $row['precio']     ?? null;

        // Convertir costo a decimal; si está vacío o no es numérico, queda en 0
        $unitPrice = is_numeric($cost) ? (float) $cost : 0.0;

        Concept::updateOrCreate(
            ['code' => $code],
            [
                'description' => $description ?: $code,
                'unit'        => $unit        ?: '—',
                'unit_price'  => $unitPrice,
                'status'      => 'active',
            ]
        );

        return null;
    }
}
