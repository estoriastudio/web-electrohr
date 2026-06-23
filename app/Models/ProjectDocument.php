<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDocument extends Model
{
    protected $fillable = [
        'project_id',
        'document_type',
        'file_path',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'date',
    ];

    /**
     * Categorías jerárquicas de documentos del proyecto.
     * Estructura: [ category_key => ['label' => ..., 'docs' => [doc_key => doc_label]] ]
     */
    public const CATEGORIES = [
        'licitacion' => [
            'label' => 'Licitación',
            'docs'  => [
                'licitacion_anexos_economicos' => 'Anexos Económicos',
                'licitacion_fallo'             => 'Fallo',
            ],
        ],
        'contratacion' => [
            'label' => 'Contratación',
            'docs'  => [
                'contratacion_contrato'           => 'Contrato',
                'contratacion_fianza_anticipo'     => 'Fianza Anticipo',
                'contratacion_fianza_cumplimiento' => 'Fianza Cumplimiento',
                'contratacion_fianza_calidad'      => 'Fianza Calidad',
                'contratacion_seguros'             => 'Seguros',
                'contratacion_siroc'               => 'Siroc',
                'contratacion_nombramientos'       => 'Nombramientos',
                'contratacion_sindicato'           => 'Sindicato',
            ],
        ],
        'entrega_recepcion' => [
            'label' => 'Entrega-Recepción',
            'docs'  => [
                'entrega_notificacion_terminacion' => 'Notificación Terminación',
                'entrega_liberacion_sindicato'     => 'Liberación Sindicato',
                'entrega_liberacion_imss'          => 'Liberación IMSS',
                'entrega_verificacion_obra'        => 'Verificación Obra',
                'entrega_acta_entrega_recepcion'   => 'Acta Entrega-Recepción',
                'entrega_finiquito'                => 'Finiquito',
                'entrega_fianza_vicios_ocultos'    => 'Fianza Vicios Ocultos',
            ],
        ],
    ];

    /**
     * Retorna todos los doc_key => doc_label aplanados (para validación, etc.).
     */
    public static function allDocTypes(): array
    {
        $all = [];
        foreach (self::CATEGORIES as $cat) {
            $all = array_merge($all, $cat['docs']);
        }
        return $all;
    }

    /**
     * Semáforo: gray = sin archivo, green = subido.
     */
    public function getStatusAttribute(): string
    {
        return $this->file_path ? 'green' : 'gray';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
