<?php

namespace App\Exports;

use App\Models\MobileAsset;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class MobileAssetExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function collection()
    {
        return MobileAsset::orderBy('name')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Folio / Movil',
            'Póliza',
            'No. Tarjeta',
            'Kilometraje',
            'Marca',
            'Modelo',
            'Año',
            'Serie / MOTOR',
            'Operador',
            'Función',
            'Tipo',
            'Placas',
            'Estatus',
            'Fecha de registro',
        ];
    }

    public function map($asset): array
    {
        $typeMap = [
            'movil'         => 'Móvil',
            'maquinaria'    => 'Maquinaria',
            'equipo_menor'  => 'Equipo menor',
        ];

        return [
            $asset->id,
            $asset->name,
            $asset->folio ?? '',
            $asset->policy ?? '',
            $asset->card_number ?? '',
            $asset->milage ?? '',
            $asset->brand ?? '',
            $asset->model ?? '',
            $asset->year ?? '',
            $asset->serial ?? '',
            $asset->operator ?? '',
            $asset->asset_function ?? '',
            $typeMap[$asset->type] ?? $asset->type ?? '',
            $asset->plates ?? '',
            $asset->status === 'active' ? 'Activo' : 'Inactivo',
            $asset->created_at?->format('d/m/Y'),
        ];
    }
}
