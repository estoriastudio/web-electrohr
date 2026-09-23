<?php

namespace App\Imports;

use App\Models\Concept;
use App\Models\StockEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ConceptImport implements ToCollection, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->importRow($row->all());
        }
    }

    private function importRow(array $row): void
    {
        $code = strtoupper(trim($row['codigo'] ?? $row['code'] ?? ''));

        if (empty($code)) {
            return;
        }

        $description = trim($row['descripcion'] ?? $row['description'] ?? $row['descripción'] ?? '');
        $unit        = trim($row['unidad']      ?? $row['unit']        ?? '');
        $location    = trim($row['ubicacion'] ?? $row['ubicación'] ?? $row['warehouse_location'] ?? '');
        $quantity    = $this->normalizeQuantity($row['cantidad'] ?? $row['quantity'] ?? null);

        $concept = Concept::updateOrCreate(['code' => $code], [
            'code'        => $code,
            'description' => $description ?: $code,
            'unit'        => $unit ?: '—',
            'warehouse_location' => $location,
            'status'      => 'active',
        ]);

        if ($quantity > 0 && ! $concept->stockEntries()->exists() && ! $concept->stockExits()->exists()) {
            StockEntry::create([
                'concept_id' => $concept->id,
                'entry_type' => 'purchase',
                'purchase_reference' => 'Importación inicial de inventario',
                'quantity' => $quantity,
                'received_at' => today(),
                'observations' => 'Existencia inicial cargada desde la importación de conceptos.',
                'created_by' => Auth::id(),
            ]);
        }
    }

    private function normalizeQuantity(mixed $quantity): float
    {
        if (is_numeric($quantity)) {
            return (float) $quantity;
        }

        $quantity = trim((string) $quantity);
        if ($quantity === '') {
            return 0.0;
        }

        $normalizedQuantity = str_contains($quantity, ',') && str_contains($quantity, '.')
            ? str_replace(['.', ','], ['', '.'], $quantity)
            : str_replace(',', '.', $quantity);

        return is_numeric($normalizedQuantity) ? (float) $normalizedQuantity : 0.0;
    }
}
