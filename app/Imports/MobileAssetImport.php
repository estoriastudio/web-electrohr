<?php

namespace App\Imports;

use App\Models\MobileAsset;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MobileAssetImport implements ToModel, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    public function chunkSize(): int
    {
        return 500;
    }

    public function model(array $row): ?MobileAsset
    {
        $name = $this->value($row, ['nombre_de_maquinaria', 'nombre', 'name']);
        $folio = $this->value($row, ['movil', 'numero_economico', 'n_economico', 'folio', 'numero']);

        if ($name === null && $folio === null) {
            return null;
        }

        $type = $this->normalizeType($this->value($row, ['tipo', 'tipo_de_bien']));
        $status = $this->normalizeStatus($this->value($row, ['estatus', 'status']));

        $payload = [
            'name' => $name ?? ($folio ?: 'Sin nombre'),
            'folio' => $folio,
            'policy' => $this->value($row, ['poliza', 'policy']),
            'card_number' => $this->value($row, ['notarjeta', 'no_tarjeta', 'card_number']),
            'milage' => $this->value($row, ['km', 'kilometraje', 'milage']),
            'brand' => $this->value($row, ['marca', 'brand']),
            'model' => $this->value($row, ['modelo', 'model']),
            'year' => $this->normalizeYear($this->value($row, ['anio', 'ano', 'year'])),
            // En el layout de flota, MOTOR se usa como identificador único (VIN/serie).
            'serial' => $this->value($row, ['serie_niv', 'serie', 'serial', 'niv', 'motor']),
            'color' => $this->value($row, ['color']),
            'operator' => $this->value($row, ['operador', 'operator', 'nombreoperador']),
            'asset_function' => $this->value($row, ['funcion', 'funcion_del_bien', 'asset_function']),
            'type' => $type,
            'plates' => $this->value($row, ['placas', 'plates']),
            'status' => $status,
        ];

        if ($type !== 'parque_vehicular') {
            $payload['plates'] = null;
        }

        $asset = null;

        if (! empty($payload['serial'])) {
            $asset = MobileAsset::where('serial', $payload['serial'])->first();
        }

        if (! $asset && ! empty($folio)) {
            $asset = MobileAsset::where('folio', $folio)->first();
        }

        if (! $asset && ! empty($payload['plates']) && $type === 'parque_vehicular') {
            $asset = MobileAsset::where('plates', $payload['plates'])
                ->where('type', 'parque_vehicular')
                ->first();
        }

        if ($asset) {
            $asset->fill($payload);
            $asset->save();
            return null;
        }

        return new MobileAsset($payload);
    }

    /**
     * Busca el primer encabezado disponible y devuelve su valor limpio.
     */
    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = is_string($row[$key]) ? trim($row[$key]) : $row[$key];

            if ($value === null || $value === '') {
                continue;
            }

            return (string) $value;
        }

        return null;
    }

    private function normalizeType(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $value = mb_strtolower(trim($raw));

        return match ($value) {
            'parque_vehicular', 'parque vehicular', 'vehiculo', 'vehiculo ligero', 'vehiculos' => 'parque_vehicular',
            'maquinaria_pesada', 'maquinaria pesada' => 'maquinaria_pesada',
            'semiremolque', 'semi remolque', 'semi-remolque', 'semirremolque' => 'semiremolque',
            default => null,
        };
    }

    private function normalizeStatus(?string $raw): string
    {
        if (! $raw) {
            return 'activo';
        }

        $value = mb_strtolower(trim($raw));

        return match ($value) {
            'activo', 'active' => 'activo',
            'vendido', 'sold' => 'vendido',
            'obsoleto', 'obsolete' => 'obsoleto',
            'reparacion', 'en reparacion', 'en reparación', 'repair' => 'reparacion',
            default => 'activo',
        };
    }

    private function normalizeYear(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $trimmed = trim($raw);

        if ($trimmed === '0' || $trimmed === '0000') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $trimmed) === 1) {
            return $trimmed;
        }

        return $trimmed;
    }
}
