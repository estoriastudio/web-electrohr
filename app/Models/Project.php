<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name', 'client_name', 'city', 'state', 'status'];

    public function works(): HasMany
    {
        return $this->hasMany(ProjectWork::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    /**
     * ¿Tiene todos los documentos subidos?
     * true = completo, false = faltan documentos.
     */
    public function hasAllDocuments(): bool
    {
        $uploaded = $this->documents->whereNotNull('file_path')->pluck('document_type')->toArray();

        return count(array_diff(array_keys(ProjectDocument::TYPES), $uploaded)) === 0;
    }

    /**
     * Estado documental global: 'complete' | 'incomplete'.
     */
    public function getDocumentStatusAttribute(): string
    {
        return $this->hasAllDocuments() ? 'complete' : 'incomplete';
    }
}
