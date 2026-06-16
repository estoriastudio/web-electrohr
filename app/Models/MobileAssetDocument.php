<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileAssetDocument extends Model
{
    protected $fillable = [
        'mobile_asset_id',
        'document_type',
        'file_path',
        'expiry_date',
        'uploaded_at',
    ];

    protected $casts = [
        'expiry_date'  => 'date',
        'uploaded_at'  => 'date',
    ];

    // Documentos que NO tienen fecha de vencimiento (solo gris/verde)
    public const NO_EXPIRY_DOCS = [
        'ficha_tecnica',
        'manual',
        'placas',
        'inspeccion_fisico_mecanica',
    ];

    // Documentos requeridos por tipo de bien
    public const DOCS_BY_TYPE = [
        'parque_vehicular' => [
            'poliza_seguro',
            'verificacion',
            'tarjeta_circulacion',
            'ficha_tecnica',
            'manual',
            'licencia_conducir',
        ],
        'maquinaria_pesada' => [
            'poliza_seguro',
            'verificacion',
            'ficha_tecnica',
            'manual',
            'licencia_conducir',
        ],
        'semiremolque' => [
            'tarjeta_circulacion',
            'placas',
            'inspeccion_fisico_mecanica',
        ],
    ];

    public static function labelsEs(): array
    {
        return [
            'poliza_seguro'           => 'Póliza de Seguro',
            'verificacion'            => 'Verificación',
            'tarjeta_circulacion'     => 'Tarjeta de Circulación',
            'ficha_tecnica'           => 'Ficha Técnica',
            'manual'                  => 'Manual',
            'licencia_conducir'       => 'Licencia de Conducir',
            'placas'                  => 'Placas',
            'inspeccion_fisico_mecanica' => 'Inspección Físico Mecánica',
        ];
    }

    /**
     * Calcula el estado de semáforo del documento.
     * gray   = no se ha subido el archivo
     * green  = vigente (o sin vencimiento y subido)
     * yellow = vence en ≤ 3 meses
     * red    = vencido
     */
    public function getStatusAttribute(): string
    {
        if (! $this->file_path) {
            return 'gray';
        }

        // Documentos sin vencimiento: solo gris o verde
        if (in_array($this->document_type, self::NO_EXPIRY_DOCS)) {
            return 'green';
        }

        if (! $this->expiry_date) {
            return 'green';
        }

        $today = Carbon::today();

        if ($this->expiry_date->lt($today)) {
            return 'red';
        }

        // Amarillo: vence en 3 meses o menos (contando desde hoy hacia el futuro)
        $monthsUntilExpiry = $today->diffInMonths($this->expiry_date);

        if ($monthsUntilExpiry < 3) {
            return 'yellow';
        }

        return 'green';
    }

    public function mobileAsset(): BelongsTo
    {
        return $this->belongsTo(MobileAsset::class);
    }
}
