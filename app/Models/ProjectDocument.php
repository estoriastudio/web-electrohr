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

    public const TYPES = [
        'licitacion'        => 'Licitación',
        'contratacion'      => 'Contratación',
        'entrega_recepcion' => 'Formato Entrega-Recepción',
    ];

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
