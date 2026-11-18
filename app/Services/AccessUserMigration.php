<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calcula y aplica los roles de la matriz objetivo para los usuarios actuales,
 * según `legacy_roles` de database/data/access_matrix.php.
 *
 * Es aditivo: asigna los roles nuevos y no quita ninguno de los anteriores.
 * Los roles heredados se retiran en la fase de limpieza.
 */
class AccessUserMigration
{
    public function __construct(private readonly AccessMatrix $matrix) {}

    /**
     * @return Collection<int, array{user: User, current: list<string>, profiles: list<string>, missing: list<string>, flags: list<string>}>
     */
    public function plan(): Collection
    {
        $managers = DB::table('projects')
            ->whereNotNull('manager_user_id')
            ->pluck('manager_user_id')
            ->flip();

        $workResponsibles = DB::table('project_works')->whereNotNull('supervisor_user_id')->pluck('supervisor_user_id')
            ->merge(DB::table('project_works')->whereNotNull('resident_user_id')->pluck('resident_user_id'))
            ->flip();

        return User::query()
            ->with('roles')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => $this->planFor(
                $user,
                isManager: $managers->has($user->id),
                isWorkResponsible: $workResponsibles->has($user->id),
            ));
    }

    /**
     * @return array{user: User, current: list<string>, profiles: list<string>, missing: list<string>, flags: list<string>}
     */
    public function planFor(User $user, bool $isManager, bool $isWorkResponsible): array
    {
        $current = $user->roles->pluck('name')->sort()->values()->all();
        $legacy = $this->matrix->legacyRoles();
        $profiles = [];
        $flags = [];

        foreach ($current as $role) {
            if ($profile = $this->matrix->profileForRole($role)) {
                $profiles[] = $profile;

                continue;
            }

            if (! isset($legacy[$role])) {
                $flags[] = "Rol sin mapeo en la matriz: {$role}";

                continue;
            }

            $rule = $legacy[$role];

            if (array_intersect($rule['skip_if_has'] ?? [], $current) !== []) {
                continue;
            }

            $targets = $rule['becomes'];

            if ($isManager) {
                array_push($targets, ...$rule['if_manager'] ?? []);
            }

            if ($isWorkResponsible) {
                array_push($targets, ...$rule['if_work_responsible'] ?? []);
            }

            if (! $isManager && ! $isWorkResponsible && isset($rule['if_unassigned'])) {
                array_push($targets, ...$rule['if_unassigned']);
                $flags[] = "{$role} sin proyecto asignado";
            }

            if ($isManager && $isWorkResponsible && isset($rule['if_manager'], $rule['if_work_responsible'])
                && $rule['if_manager'] !== $rule['if_work_responsible']) {
                $flags[] = "{$role} es gerente y supervisor: revisar";
            }

            if ($targets === []) {
                $flags[] = "Pierde {$role} sin reemplazo";
            }

            array_push($profiles, ...$targets);
        }

        if ($current === []) {
            $flags[] = 'Sin rol';
        }

        $profiles = array_values(array_unique($profiles));
        $missing = array_values(array_filter(
            $profiles,
            fn (string $profile) => ! in_array($this->matrix->roleName($profile), $current, true),
        ));

        return compact('user', 'current', 'profiles', 'missing', 'flags');
    }

    /**
     * Asigna los roles faltantes. Devuelve cuántas asignaciones hizo.
     *
     * @param  Collection<int, array{user: User, missing: list<string>}>  $plan
     */
    public function apply(Collection $plan): int
    {
        $assigned = 0;

        DB::transaction(function () use ($plan, &$assigned) {
            foreach ($plan as $row) {
                foreach ($row['missing'] as $profile) {
                    $row['user']->assignRole($this->matrix->roleName($profile));
                    $assigned++;
                }
            }
        });

        return $assigned;
    }
}
