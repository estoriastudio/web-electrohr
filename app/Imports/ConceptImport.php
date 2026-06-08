<?php

namespace App\Imports;

use App\Models\Concept;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ConceptImport implements ToModel, WithHeadingRow, WithChunkReading, WithBatchInserts, WithUpserts, SkipsEmptyRows
{
    public function batchSize(): int
    {
        return 500;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function uniqueBy(): string
    {
        return 'code';
    }

    public function model(array $row): ?Concept
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

        // Convertir costo a decimal y tolerar formato con separador de miles
        $normalizedCost = is_string($cost) ? str_replace(',', '', trim($cost)) : $cost;
        $unitPrice = is_numeric($normalizedCost) ? (float) $normalizedCost : 0.0;

        return new Concept([
            'code'        => $code,
            'description' => $description ?: $code,
            'unit'        => $unit ?: '—',
            'unit_price'  => $unitPrice,
            'status'      => 'active',
        ]);
    }
}
