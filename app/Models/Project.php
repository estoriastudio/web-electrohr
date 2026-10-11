<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name', 'client_name', 'city', 'state', 'status', 'currency', 'current_agreement_value'];

    public function works(): HasMany
    {
        return $this->hasMany(ProjectWork::class);
    }

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('works', fn (Builder $works) => $works
            ->where('supervisor_user_id', $user->id)
            ->orWhere('resident_user_id', $user->id));
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $user->isRestrictedEngineer() ? $query->assignedTo($user) : $query;
    }

    public function userCanAccess(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->accessibleBy($user)->exists();
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
