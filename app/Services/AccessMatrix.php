<?php

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;

/**
 * Lee la matriz objetivo de roles × vistas (database/data/access_matrix.php).
 *
 * Los permisos se nombran `{vista}.{acción}`, p. ej. `purchase_orders.view`.
 * El seeder AccessMatrixSeeder los crea y asigna a cada rol a partir de aquí.
 */
class AccessMatrix
{
    public const ALL = 'all';

    private ?array $data = null;

    public function __construct(private readonly string $path) {}

    public static function permission(string $view, string $action): string
    {
        return "{$view}.{$action}";
    }

    public function data(): array
    {
        return $this->data ??= require $this->path;
    }

    /** @return array<string, array{name: string, status: string, description: string}> */
    public function roles(): array
    {
        return $this->data()['roles'];
    }

    /** @return array<string, array<string, mixed>> */
    public function views(): array
    {
        return $this->data()['views'];
    }

    /** @return array<string, array<string, mixed>> */
    public function legacyRoles(): array
    {
        return $this->data()['legacy_roles'];
    }

    public function roleName(string $profile): string
    {
        return $this->roles()[$profile]['name']
            ?? throw new InvalidArgumentException("Perfil desconocido: {$profile}");
    }

    public function profileForRole(string $roleName): ?string
    {
        foreach ($this->roles() as $profile => $role) {
            if ($role['name'] === $roleName) {
                return $profile;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function permissionsFor(string $profile): array
    {
        $permissions = [];

        foreach ($this->views() as $view => $definition) {
            foreach ($definition['access'][$profile]['actions'] ?? [] as $action) {
                $permissions[] = self::permission($view, $action);
            }
        }

        return $permissions;
    }

    /** @return list<string> */
    public function allPermissions(): array
    {
        $permissions = [];

        foreach (array_keys($this->roles()) as $profile) {
            array_push($permissions, ...$this->permissionsFor($profile));
        }

        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        return $permissions;
    }

    /** @return list<string> Perfiles de la matriz que tiene el usuario por sus roles. */
    public function profilesOf(User $user): array
    {
        return $user->getRoleNames()
            ->map(fn (string $role) => $this->profileForRole($role))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Alcance con el que el usuario puede ejecutar una acción sobre una vista.
     *
     * Devuelve [] si ningún perfil del usuario lo permite, [AccessMatrix::ALL]
     * si al menos uno lo permite sin restricción, o la lista de alcances
     * (claves de `scopes`) que hay que unir.
     *
     * @return list<string>
     */
    public function scopesFor(User $user, string $view, string $action = 'view'): array
    {
        if ($user->hasRole($this->roleName('ADM'))) {
            return [self::ALL];
        }

        $scopes = [];

        foreach ($this->grantingCells($user, $view, $action) as $cell) {
            $scope = $cell['action_scopes'][$action] ?? $cell['scope'] ?? null;

            if ($scope === null) {
                return [self::ALL];
            }

            $scopes[] = $scope;
        }

        return array_values(array_unique($scopes));
    }

    /** El usuario puede ejecutar la acción sobre todos los registros de la vista, sin alcance. */
    public function hasFullScope(User $user, string $view, string $action = 'view'): bool
    {
        return $this->scopesFor($user, $view, $action) === [self::ALL];
    }

    public function hidesField(User $user, string $view, string $field): bool
    {
        return in_array($field, $this->hiddenFieldsFor($user, $view), true);
    }

    /**
     * Campos que el usuario no debe ver en una vista. Un campo solo se oculta
     * si todos los perfiles del usuario que le dan acceso lo ocultan.
     *
     * @return list<string>
     */
    public function hiddenFieldsFor(User $user, string $view): array
    {
        if ($user->hasRole($this->roleName('ADM'))) {
            return [];
        }

        $hidden = null;

        foreach ($this->grantingCells($user, $view, 'view') as $cell) {
            $fields = $cell['hidden'] ?? [];
            $hidden = $hidden === null ? $fields : array_intersect($hidden, $fields);
        }

        return array_values($hidden ?? []);
    }

    /** @return list<array<string, mixed>> */
    private function grantingCells(User $user, string $view, string $action): array
    {
        $access = $this->views()[$view]['access']
            ?? throw new InvalidArgumentException("Vista desconocida: {$view}");

        $cells = [];

        foreach ($this->profilesOf($user) as $profile) {
            if (in_array($action, $access[$profile]['actions'] ?? [], true)) {
                $cells[] = $access[$profile];
            }
        }

        return $cells;
    }
}
