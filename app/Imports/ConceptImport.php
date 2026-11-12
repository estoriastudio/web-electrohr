<?php

namespace App\Imports;

use App\Models\Concept;
use App\Models\StockEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $rawUnitPrice = $row['precio_unitario'] ?? $row['unit_price'] ?? null;
        $unitPrice    = $this->normalizeQuantity($rawUnitPrice);
        $hasUnitPrice = trim((string) $rawUnitPrice) !== '';

        $rawMinimumStock = $row['stock_minimo'] ?? $row['stock_mínimo'] ?? $row['minimum_stock'] ?? null;
        $minimumStock    = $this->normalizeQuantity($rawMinimumStock);
        $hasMinimumStock = trim((string) $rawMinimumStock) !== '';

        $rawMaximumStock = $row['stock_maximo'] ?? $row['stock_máximo'] ?? $row['maximum_stock'] ?? null;
        $maximumStock    = $this->normalizeQuantity($rawMaximumStock);
        $hasMaximumStock = trim((string) $rawMaximumStock) !== '';

        DB::transaction(function () use ($code, $description, $unit, $location, $quantity, $unitPrice, $hasUnitPrice, $minimumStock, $hasMinimumStock, $maximumStock, $hasMaximumStock) {
            $attributes = [
                'code'        => $code,
                'description' => $description ?: $code,
                'unit'        => $unit ?: '—',
                'warehouse_location' => $location,
                'status'      => 'active',
            ];

            // Solo se actualiza el precio si el archivo lo trae, para no sobrescribirlo con 0.
            if ($hasUnitPrice) {
                $attributes['unit_price'] = $unitPrice;
            }

            // Igual que el precio: solo se actualizan los stocks si el archivo los trae.
            if ($hasMinimumStock) {
                $attributes['minimum_stock'] = $minimumStock;
            }

            if ($hasMaximumStock) {
                $attributes['maximum_stock'] = $maximumStock;
            }

            $concept = Concept::updateOrCreate(['code' => $code], $attributes);

            if ($quantity > 0 && ! $concept->stockEntryItems()->exists() && ! $concept->stockExitItems()->exists()) {
                $entry = StockEntry::create([
                    'concept_id' => null,
                    'entry_type' => 'purchase',
                    'purchase_reference' => 'Importación inicial de inventario',
                    'quantity' => $quantity,
                    'received_at' => today(),
                    'observations' => 'Existencia inicial cargada desde la importación de conceptos.',
                    'created_by' => Auth::id(),
                ]);

                $entry->items()->create([
                    'concept_id' => $concept->id,
                    'line_number' => 1,
                    'quantity' => $quantity,
                ]);
            }
        });
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
