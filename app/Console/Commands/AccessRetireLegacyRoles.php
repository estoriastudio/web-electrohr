<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccessMatrix;
use App\Services\AccessUserMigration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 5: retira los roles anteriores (Engineer, Solmat, Solcom, Inventario, suministros, Proyectos,
 * Proveedor, Ayudante Movil) y los permisos genéricos create/read/update/delete.
 *
 * Por defecto solo muestra el plan. Con --apply los quita de los usuarios y los borra. No aplica si a
 * algún usuario todavía le faltan los roles de la matriz: primero hay que correr access:migrate-users --apply.
 */
class AccessRetireLegacyRoles extends Command
{
    /** Permisos del esquema anterior, ya sin uso en el código. */
    public const LEGACY_PERMISSIONS = ['create', 'read', 'update', 'delete'];

    protected $signature = 'access:retire-legacy-roles
        {--apply : Quita y borra los roles y permisos anteriores (sin esta opción solo muestra el plan)}';

    protected $description = 'Retira los roles anteriores a la matriz y los permisos genéricos create/read/update/delete';

    public function handle(AccessMatrix $matrix, AccessUserMigration $migration): int
    {
        $legacyNames = array_keys($matrix->legacyRoles());
        $legacyRoles = Role::query()->where('guard_name', 'web')->whereIn('name', $legacyNames)->get();
        $legacyPermissions = Permission::query()->where('guard_name', 'web')->whereIn('name', self::LEGACY_PERMISSIONS)->get();

        $pending = $migration->plan()->filter(fn (array $row) => $row['missing'] !== []);
        $affectedUsers = User::query()
            ->whereHas('roles', fn ($roles) => $roles->whereIn('name', $legacyNames))
            ->with('roles')
            ->orderBy('name')
            ->get();

        $this->table(
            ['id', 'Usuario', 'Roles que se quitan', 'Roles que conserva'],
            $affectedUsers->map(function (User $user) use ($legacyNames) {
                [$legacy, $kept] = $user->roles->pluck('name')->partition(fn ($name) => in_array($name, $legacyNames, true));

                return [$user->id, $user->name, $legacy->implode(', '), $kept->implode(', ') ?: 'NINGUNO'];
            })->all(),
        );

        $withoutAccess = $affectedUsers->filter(fn (User $user) => $user->roles->pluck('name')->diff($legacyNames)->isEmpty());

        $this->newLine();
        $this->line('Roles anteriores: '.($legacyRoles->pluck('name')->implode(', ') ?: 'ninguno'));
        $this->line('Permisos genéricos: '.($legacyPermissions->pluck('name')->implode(', ') ?: 'ninguno'));
        $this->line("Usuarios afectados: {$affectedUsers->count()} · se quedarían sin rol: {$withoutAccess->count()}");

        if ($pending->isNotEmpty()) {
            $this->warn("A {$pending->count()} usuario(s) todavía les faltan roles de la matriz. Corre primero: php artisan access:migrate-users --apply");
        }

        if (! $this->option('apply')) {
            $this->info('Solo plan. Usa --apply para retirar los roles y permisos anteriores.');

            return self::SUCCESS;
        }

        if ($pending->isNotEmpty()) {
            $this->error('No se aplicó: faltan roles de la matriz por asignar.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($legacyRoles, $legacyPermissions) {
            foreach ($legacyRoles as $role) {
                $role->users()->detach();
                $role->permissions()->detach();
                $role->delete();
            }

            foreach ($legacyPermissions as $permission) {
                $permission->roles()->detach();
                $permission->users()->detach();
                $permission->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info("Retirados {$legacyRoles->count()} roles y {$legacyPermissions->count()} permisos anteriores.");

        return self::SUCCESS;
    }
}
