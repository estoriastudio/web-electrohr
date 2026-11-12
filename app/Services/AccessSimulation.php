<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Simulación de accesos para el admin configurado: "ver como rol" cambia sus roles solo en memoria
 * durante la petición; "entrar como usuario" atiende la petición como ese usuario. La sesión sigue
 * siendo del admin, así que nada se escribe en roles ni en la sesión de los demás.
 */
class AccessSimulation
{
    public const SESSION_ROLE = 'access_simulation.role';

    public const SESSION_USER = 'access_simulation.user_id';

    /** Admin que inició la petición, antes de aplicar la simulación. Null si no puede simular. */
    private ?User $realUser = null;

    private ?string $roleName = null;

    private ?User $simulatedUser = null;

    public function __construct(private readonly AccessMatrix $matrix) {}

    public function enabled(): bool
    {
        return (bool) config('access_simulation.enabled');
    }

    /** Se decide con los roles reales: llamar antes de aplicar cualquier simulación. */
    public function canUse(?User $user): bool
    {
        return $this->enabled()
            && $user !== null
            && strcasecmp($user->email, (string) config('access_simulation.admin_email')) === 0
            // Contra la base y no contra la relación cargada, que la simulación cambia en memoria.
            && $user->roles()->where('name', $this->matrix->roleName('ADM'))->exists();
    }

    /** Limpia el estado de una petición anterior (el contenedor puede reutilizar la instancia). */
    public function reset(): void
    {
        $this->realUser = null;
        $this->roleName = null;
        $this->simulatedUser = null;
    }

    public function setRealUser(User $user): void
    {
        $this->realUser = $user;
    }

    public function realUser(): ?User
    {
        return $this->realUser;
    }

    public function isActive(): bool
    {
        return $this->roleName !== null || $this->simulatedUser !== null;
    }

    public function roleName(): ?string
    {
        return $this->roleName;
    }

    public function simulatedUser(): ?User
    {
        return $this->simulatedUser;
    }

    /** Deja al admin solo con el rol elegido (en memoria). Devuelve false si el rol ya no existe. */
    public function applyRole(User $user, string $roleName): bool
    {
        $role = $this->availableRoles()->firstWhere('name', $roleName);

        if (! $role) {
            return false;
        }

        $user->setRelation('roles', new EloquentCollection([$role]));
        $user->setRelation('permissions', new EloquentCollection);
        $this->roleName = $roleName;

        return true;
    }

    public function applyUser(User $user): void
    {
        $this->simulatedUser = $user;
        $this->roleName = $user->getRoleNames()->implode(', ') ?: null;
    }

    /** El portal de proveedores depende del proveedor ligado al usuario: simular solo el rol no sirve. */
    public function roleRequiresUser(string $roleName): bool
    {
        return $roleName === $this->matrix->roleName('PRV');
    }

    /** Roles de la matriz que existen en la base, sin admin. */
    public function availableRoles(): EloquentCollection
    {
        $names = collect($this->matrix->roles())
            ->except('ADM')
            ->pluck('name')
            ->all();

        return Role::query()
            ->with('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->get()
            ->sortBy(fn (Role $role) => array_search($role->name, $names, true))
            ->values();
    }

    /**
     * Usuarios con al menos un rol de la matriz, para entrar como ellos.
     *
     * @return Collection<int, array{id: int, name: string, email: string, roles: list<string>}>
     */
    public function availableUsers(): Collection
    {
        $roleNames = $this->availableRoles()->pluck('name')->all();

        return User::query()
            ->with('roles')
            ->whereHas('roles', fn ($roles) => $roles->whereIn('name', $roleNames))
            ->when($this->realUser, fn ($users) => $users->whereKeyNot($this->realUser->id))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->intersect($roleNames)->values()->all(),
            ]);
    }
}
