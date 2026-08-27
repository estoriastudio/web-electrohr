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
        $supplier->street         = trim($row['domicilio'] ?? '') ?: null;
        $supplier->colony         = trim($row['colonia'] ?? '') ?: null;
        $supplier->postal_code    = trim($row['codigo'] ?? '') ?: null;
        $supplier->city           = trim($row['ciudad'] ?? '') ?: null;
        $supplier->state          = trim($row['estado'] ?? '') ?: null;
        $supplier->bank_name      = trim($row['banco']    ?? '') ?: null;
        $supplier->bank_account   = trim($row['cuenta']   ?? '') ?: null;
        $supplier->bank_clabe     = trim($row['clabe']    ?? '') ?: null;
        $supplier->swift_code     = trim($row['swift']    ?? '') ?: null;
        $supplier->currency       = strtoupper(trim($row['moneda'] ?? '')) ?: null;
        $supplier->status         = $supplier->status ?? 'active';

        $supplier->save();

        $email = trim($row['correo'] ?? '') ?: null;
        $phone = trim($row['telefono'] ?? '') ?: null;
        if ($email || $phone) {
            $contact = $supplier->contacts()->firstOrNew(['is_primary' => true]);
            $contact->name = $contact->name ?: 'Contacto principal';
            $contact->email = $email;
            $contact->phone = $phone;
            $contact->save();
        }

        if (array_filter([
            $supplier->bank_name,
            $supplier->bank_account,
            $supplier->bank_clabe,
            $supplier->currency,
        ])) {
            $location = $supplier->locations()->firstOrNew([], ['name' => 'Cuenta Principal']);
            $location->name = $location->name ?: 'Cuenta Principal';
            $location->bank_name = $supplier->bank_name;
            $location->bank_account = $supplier->bank_account;
            $location->bank_clabe = $supplier->bank_clabe;
            $location->currency = $supplier->currency;
            $location->save();
        }

        // Retornamos null para evitar que el paquete intente insertar de nuevo
        return null;
    }
}
