<?php

namespace App\Services\Hr;

use App\Models\ProjectWork;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Alcance de campo (HR-005): Supervisor = Obras donde es supervisor o residente;
 * Gerente de proyecto = Obras de los Proyectos que dirige. RH y admin no se acotan.
 */
class HrFieldScope
{
    public const NOT_ASSIGNED_MESSAGE = 'La persona no está asignada a una de tus Obras.';

    public static function isUnrestricted(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user !== null && $user->hasAnyRole([
            HrPermissions::ROLE_ADMIN,
            HrPermissions::ROLE_HR_CHIEF,
            HrPermissions::ROLE_HR_ASSISTANT,
        ]);
    }

    /** @return Collection<int, int> ids de ProjectWork del usuario de campo. */
    public static function workIds(?User $user = null): Collection
    {
        $user ??= Auth::user();

        $isSupervisor = $user?->hasRole(HrPermissions::ROLE_SUPERVISOR) ?? false;
        $isManager = $user?->hasRole(HrPermissions::ROLE_MANAGER) ?? false;

        if (! $isSupervisor && ! $isManager) {
            return collect();
        }

        return ProjectWork::query()
            ->where(function ($query) use ($user, $isSupervisor, $isManager) {
                if ($isSupervisor) {
                    $query->orWhere('supervisor_user_id', $user->id)
                        ->orWhere('resident_user_id', $user->id);
                }

                if ($isManager) {
                    $query->orWhereHas('project', fn ($projectQuery) => $projectQuery->where('manager_user_id', $user->id));
                }
            })
            ->pluck('id');
    }

    /** Obras que el usuario puede operar: todas para RH y admin; las de su alcance para el campo. */
    public static function works(?User $user = null): Builder
    {
        $user ??= Auth::user();

        return ProjectWork::query()->when(
            ! self::isUnrestricted($user),
            fn (Builder $query) => $query->whereIn('id', self::workIds($user))
        );
    }

    public static function canOperateWork(int $projectWorkId, ?User $user = null): bool
    {
        if (self::isUnrestricted($user)) {
            return true;
        }

        return self::workIds($user)->contains($projectWorkId);
    }
}
