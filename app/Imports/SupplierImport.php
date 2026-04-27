<?php

namespace App\Imports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class SupplierImport implements ToModel, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    public function chunkSize(): int
    {
        return 500;
    }

    public function model(array $row): ?Supplier
    {
        $rfcName = trim($row['razon'] ?? '');
        $rfcNum  = strtoupper(trim($row['rfc'] ?? ''));

        // Si no hay razón social, se omite la fila
        if (empty($rfcName)) {
            return null;
        }

        // Construir dirección completa a partir de los campos separados
        $addressParts = array_filter([
            trim($row['domicilio'] ?? ''),
            trim($row['colonia']   ?? ''),
            trim($row['codigo']    ?? ''),
            trim($row['ciudad']    ?? ''),
            trim($row['estado']    ?? ''),
        ]);
        $address = implode(', ', $addressParts) ?: null;

        // Buscar registro existente por RFC o por razón social (OR)
        $supplier = Supplier::where(function ($q) use ($rfcNum, $rfcName) {
                if ($rfcNum) {
                    $q->where('rfc_num', $rfcNum);
                }
                if ($rfcName) {
                    $q->orWhere('rfc_name', $rfcName);
                }
            })
            ->first() ?? new Supplier();

        $supplier->rfc_name       = $rfcName;
        $supplier->commercial_name = $supplier->commercial_name ?? $rfcName;
        $supplier->rfc_num        = $rfcNum ?: null;
        $supplier->email          = trim($row['correo']   ?? '') ?: null;
        $supplier->phone          = trim($row['telefono'] ?? '') ?: null;
        $supplier->attended_by    = trim($row['atencion'] ?? '') ?: null;
        $supplier->address        = $address;
        $supplier->bank_name      = trim($row['banco']    ?? '') ?: null;
        $supplier->bank_account   = trim($row['cuenta']   ?? '') ?: null;
        $supplier->bank_clabe     = trim($row['clabe']    ?? '') ?: null;
        $supplier->swift_code     = trim($row['swift']    ?? '') ?: null;
        $supplier->currency       = strtoupper(trim($row['moneda'] ?? '')) ?: null;
        $supplier->status         = $supplier->status ?? 'active';

        $supplier->save();

        // Retornamos null para evitar que el paquete intente insertar de nuevo
        return null;
    }
}
