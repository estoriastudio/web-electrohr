<?php

namespace App\Models;

use App\Models\Concerns\HasAccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasAccessScope;

    protected $fillable = ['name', 'client_name', 'city', 'state', 'status', 'currency', 'current_agreement_value', 'manager_user_id'];

    public function works(): HasMany
    {
        return $this->hasMany(ProjectWork::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function isManagedBy(User $user): bool
    {
        return $this->manager_user_id !== null && (int) $this->manager_user_id === (int) $user->id;
    }

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $scope) => $scope
            ->where('projects.manager_user_id', $user->id)
            ->orWhereHas('works', fn (Builder $works) => $works
                ->where(fn (Builder $responsible) => $responsible
                    ->where('supervisor_user_id', $user->id)
                    ->orWhere('resident_user_id', $user->id))));
    }

    /** Proyectos que el usuario puede elegir en búsquedas y formularios: los suyos si solo ve asignados. */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $user->seesOnlyAssignedProjects() ? $query->assignedTo($user) : $query;
    }

    public function applyAccessScope(Builder $query, string $scope, User $user): void
    {
        match ($scope) {
            'assigned_projects' => $query->assignedTo($user),
            default => $this->unsupportedAccessScope($scope),
        };
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
        return collect($this->documentCategoryProgress())
            ->every(fn (array $progress) => $progress['uploaded'] === $progress['applicable']);
    }

    /**
     * Avance documental por categoría, excluyendo los documentos "No aplica".
     * Retorna [ category_key => ['uploaded', 'applicable', 'not_applicable', 'total'] ].
     */
    public function documentCategoryProgress(): array
    {
        $progress = [];

        foreach (ProjectDocument::CATEGORIES as $catKey => $category) {
            $catDocs = $this->documents->whereIn('document_type', array_keys($category['docs']));
            $notApplicable = $catDocs->where('not_applicable', true)->count();
            $total = count($category['docs']);

            $progress[$catKey] = [
                'uploaded' => $catDocs->where('not_applicable', false)->whereNotNull('file_path')->count(),
                'applicable' => $total - $notApplicable,
                'not_applicable' => $notApplicable,
                'total' => $total,
            ];
        }

        return $progress;
    }

    /**
     * Estado documental global: 'complete' | 'incomplete'.
     */
    public function getDocumentStatusAttribute(): string
    {
        return $this->hasAllDocuments() ? 'complete' : 'incomplete';
    }
}
