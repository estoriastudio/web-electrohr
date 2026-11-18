<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectWork;
use App\Services\AccessMatrix;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /** Quien solo ve proyectos asignados (Gerente, Supervisor) consulta obras únicamente de sus proyectos. */
    protected function ensureProjectVisible(?int $projectId): void
    {
        $user = Auth::user();

        if (! $user?->seesOnlyAssignedProjects()) {
            return;
        }

        abort_unless(
            $projectId && Project::query()->whereKey($projectId)->accessibleBy($user)->exists(),
            403
        );
    }

    /** 403 si la matriz de acceso no le da al usuario esta acción sobre el registro (permiso y alcance). */
    protected function authorizeAccess(Model $record, string $view, string $action = 'view'): void
    {
        abort_unless($record->isVisibleTo(Auth::user(), $view, $action), 403);
    }

    /**
     * Obras que el usuario puede elegir en filtros y formularios de una vista:
     * todas si la matriz no le pone alcance, o solo las obras a su cargo.
     */
    protected function visibleProjectWorks(string $view, string $action = 'view'): Builder
    {
        $user = Auth::user();
        $scopes = app(AccessMatrix::class)->scopesFor($user, $view, $action);
        $query = ProjectWork::query();

        if (in_array(AccessMatrix::ALL, $scopes, true)) {
            return $query;
        }

        return $query->whereIn('project_works.id', $scopes === [] ? [] : $user->assignedProjectWorkIds());
    }
}
