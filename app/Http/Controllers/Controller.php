<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /** Un Engineer restringido solo consulta registros de proyectos donde es supervisor o residente. */
    protected function ensureProjectVisible(?int $projectId): void
    {
        $user = Auth::user();

        if (! $user?->isRestrictedEngineer()) {
            return;
        }

        abort_unless(
            $projectId && Project::query()->whereKey($projectId)->accessibleBy($user)->exists(),
            403
        );
    }
}
