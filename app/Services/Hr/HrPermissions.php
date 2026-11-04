<?php

namespace App\Services\Hr;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Permisos del proceso de asistencia y nómina (HR-005). Clase de servicio simple:
 * no es una Policy ni un Gate. Dirección equivale a admin, que pasa todos.
 */
class HrPermissions
{
    public const ROLE_SUPERVISOR = 'Supervisor';

    public const ROLE_MANAGER = 'Gerente de proyecto';

    public const ROLE_HR_CHIEF = 'RH jefe';

    public const ROLE_HR_ASSISTANT = 'RH ayudante';

    public const ROLE_REVIEWER = 'Revisor de nómina';

    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_SUPERVISOR,
        self::ROLE_MANAGER,
        self::ROLE_HR_CHIEF,
        self::ROLE_HR_ASSISTANT,
        self::ROLE_REVIEWER,
    ];

    /** Roles que pasan cada permiso, además de admin. */
    private const ABILITIES = [
        'seeAmounts' => [self::ROLE_HR_CHIEF],
        'seeIncentives' => [self::ROLE_SUPERVISOR, self::ROLE_MANAGER, self::ROLE_HR_CHIEF, self::ROLE_REVIEWER],
        'hrInbox' => [self::ROLE_HR_CHIEF, self::ROLE_HR_ASSISTANT],
        'payrollReview' => [self::ROLE_REVIEWER],
        'payrollHR' => [self::ROLE_HR_CHIEF],
        'editPayout' => [self::ROLE_HR_CHIEF],
        'editLoans' => [self::ROLE_HR_CHIEF],
        'config' => [self::ROLE_HR_CHIEF],
        'viewLoans' => [self::ROLE_HR_CHIEF],
        'backups' => [self::ROLE_HR_CHIEF],
        'authorize' => [],
        'grantUnlock' => [self::ROLE_HR_CHIEF],
        'audit' => [self::ROLE_HR_CHIEF, self::ROLE_HR_ASSISTANT, self::ROLE_REVIEWER],
    ];

    public static function can(string $ability, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole(self::ROLE_ADMIN)) {
            return true;
        }

        $roles = self::ABILITIES[$ability] ?? [];

        return $roles !== [] && $user->hasAnyRole($roles);
    }

    /** Rol con el que actúa el usuario, por orden de jerarquía. */
    public static function roleOf(?User $user = null): string
    {
        $user ??= Auth::user();

        if (! $user) {
            return 'Sistema';
        }

        foreach ([self::ROLE_ADMIN, self::ROLE_HR_CHIEF, self::ROLE_HR_ASSISTANT, self::ROLE_REVIEWER, self::ROLE_MANAGER, self::ROLE_SUPERVISOR] as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return (string) ($user->getRoleNames()->first() ?? 'Sin rol');
    }
}
