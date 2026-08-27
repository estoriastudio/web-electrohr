<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name', 'client_name', 'city', 'state', 'status', 'currency', 'current_agreement_value'];

    public function works(): HasMany
    {
        return $this->hasMany(ProjectWork::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(ProjectAgreement::class);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(ProjectEstimate::class);
    }

    /**
     * ¿Tiene todos los documentos subidos?
     * true = completo, false = faltan documentos.
     */
    public function hasAllDocuments(): bool
    {
        $uploaded = $this->documents->whereNotNull('file_path')->pluck('document_type')->toArray();
        $required = array_keys(ProjectDocument::allDocTypes());

        return count(array_diff($required, $uploaded)) === 0;
    }

    /**
     * Estado documental global: 'complete' | 'incomplete'.
     */
    public function getDocumentStatusAttribute(): string
    {
        return $this->hasAllDocuments() ? 'complete' : 'incomplete';
    }
}
