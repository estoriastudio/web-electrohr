<?php

namespace Database\Seeders;

use App\Services\AccessMatrix;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los permisos `{vista}.{acción}` y los roles de la matriz objetivo
 * (database/data/access_matrix.php) y asigna a cada rol sus permisos.
 *
 * Es idempotente. Conserva los permisos CRUD heredados (create, read,
 * update, delete) de los roles que ya los tenían, porque el menú y las
 * vistas actuales todavía dependen de ellos.
 */
class AccessMatrixSeeder extends Seeder
{
    public function run(AccessMatrix $matrix): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permissions = $matrix->allPermissions();

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', '%.%')
            ->whereNotIn('name', $permissions)
            ->delete();

        foreach (array_keys($matrix->roles()) as $profile) {
            // Fase 5: cada rol queda solo con los permisos de la matriz.
            Role::findOrCreate($matrix->roleName($profile), 'web')
                ->syncPermissions($matrix->permissionsFor($profile));
        }

        $registrar->forgetCachedPermissions();
    }
}
