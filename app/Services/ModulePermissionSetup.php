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
        ]);
        $this->createProfile('Ayudante de pagos', ['payments.read', 'invoices.read']);
        $this->createProfile('Comprador', [
            'purchase_orders.read', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.delete',
            'purchase_requests.read', 'purchase_requests.create', 'purchase_requests.update', 'purchase_requests.delete',
            'suppliers.read',
        ]);
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