<?php

namespace App\Services;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ModulePermissionSetup
{
    public function catalog(): array
    {
        $catalog = [];
        foreach (config('module_permissions.modules') as $module => $label) {
            foreach (config('module_permissions.actions') as $action => $actionLabel) {
                $catalog[$label]["{$module}.{$action}"] = $actionLabel;
            }
        }
        foreach (config('module_permissions.extra') as $permission => $label) {
            $module = explode('.', $permission)[0];
            $catalog[config("module_permissions.modules.{$module}")][$permission] = $label;
        }

        return $catalog;
    }

    public function initialize(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->catalog() as $permissions) {
            foreach (array_keys($permissions) as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }
        }
        $this->createProfile('Administrador de pagos', [
            'payments.read', 'payments.create', 'payments.update', 'payments.delete',
            'invoices.read', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.approve',
            ...config('module_permissions.access_role_extras.Pagos', []),
        ]);
        $this->createProfile('Ayudante de pagos', ['payments.read', 'invoices.read']);
        $this->createProfile('Comprador', [
            'purchase_orders.read', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.delete',
            'purchase_requests.read', 'purchase_requests.create', 'purchase_requests.update', 'purchase_requests.delete',
            'suppliers.read',
        ]);
    }

    /**
     * Crea los roles heredados que falten con los permisos de su plantilla. Los roles que ya
     * existen no se modifican para respetar los ajustes hechos desde Usuarios.
     */
    public function ensureAccessRoleTemplates(): void
    {
        foreach (config('module_permissions.access_roles') as $roleName => $modules) {
            $this->createProfile($roleName, [
                ...$this->scopeActions($modules, ['create', 'read', 'update', 'delete']),
                ...config("module_permissions.access_role_extras.{$roleName}", []),
            ]);
        }
    }

    /**
     * El acceso a los modulos ya no depende del nombre del rol sino de los permisos.
     * Conserva el acceso de quienes solo tenian el rol heredado y los permisos de alcance
     * (ver todo) que antes daba el nombre del rol.
     */
    public function migrateAccessRolesToPermissions(): void
    {
        $this->initialize();
        Role::where('name', 'Administrador de pagos')->where('guard_name', 'web')->first()
            ?->givePermissionTo(config('module_permissions.access_role_extras.Pagos', []));
        foreach (config('module_permissions.access_roles') as $roleName => $modules) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) {
                continue;
            }
            $extras = config("module_permissions.access_role_extras.{$roleName}", []);
            $profile = $this->createProfile("{$roleName} - permisos iniciales", [
                ...$this->scopeActions($modules, ['create', 'read', 'update', 'delete']),
                ...$extras,
            ]);
            if ($extras !== []) {
                $profile->givePermissionTo($extras);
            }
            $readPermissions = array_map(fn ($module) => "{$module}.read", array_keys($modules));

            User::role($role)->chunkById(100, function ($users) use ($profile, $readPermissions, $extras) {
                foreach ($users as $user) {
                    $granted = $user->getAllPermissions()->pluck('name')->all();
                    if (array_intersect($readPermissions, $granted) === []) {
                        $user->assignRole($profile);
                    } elseif ($extras !== []) {
                        $missing = array_values(array_diff($extras, $granted));
                        if ($missing !== []) {
                            $user->givePermissionTo($missing);
                        }
                    }
                }
            });
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Herramientas y Conceptos pasaron de rol (admin|Solmat) a permisos. Se agregan a los roles
     * heredados y a sus perfiles iniciales para conservar el acceso de quienes ya los usaban.
     */
    public function migrateToolsAndConceptsPermissions(): void
    {
        $this->initialize();
        foreach (config('module_permissions.access_roles') as $roleName => $modules) {
            $permissions = $this->scopeActions(
                array_intersect_key($modules, array_flip(['tools', 'concepts'])),
                ['create', 'read', 'update', 'delete'],
            );
            if ($permissions === []) {
                continue;
            }
            Role::whereIn('name', [$roleName, "{$roleName} - permisos iniciales"])
                ->where('guard_name', 'web')
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permissions));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function migrateLegacyAssignments(): void
    {
        $this->initialize();
        foreach (config('module_permissions.access_roles') as $roleName => $modules) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) {
                continue;
            }
            $legacyActions = $role->permissions->pluck('name')->all();
            $names = $this->scopeActions($modules, $legacyActions);
            $profile = $this->createProfile("{$roleName} - permisos iniciales", $names);
            User::role($role)->chunkById(100, function ($users) use ($profile, $modules) {
                foreach ($users as $user) {
                    $user->assignRole($profile);
                    $direct = $this->scopeActions($modules, $user->permissions->pluck('name')->all());
                    if ($direct !== []) {
                        $user->givePermissionTo($direct);
                    }
                }
            });
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function scopeActions(array $modules, array $actions): array
    {
        $names = [];
        foreach ($modules as $module => $allowedActions) {
            foreach ($allowedActions as $action) {
                $legacyAction = $action === 'approve' ? 'update' : $action;
                if (in_array($legacyAction, $actions, true)) {
                    $names[] = "{$module}.{$action}";
                }
            }
        }

        return $names;
    }

    private function createProfile(string $name, array $permissions): Role
    {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        if ($role->wasRecentlyCreated) {
            $role->syncPermissions($permissions);
        }

        return $role;
    }
}