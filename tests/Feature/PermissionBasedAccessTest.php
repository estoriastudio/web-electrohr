<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\User;
use App\Services\ModulePermissionSetup;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionBasedAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
        $migration = require database_path('migrations/2026_04_23_225441_create_permission_tables.php');
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function user(string $email = 'user@example.com'): User
    {
        return User::create(['name' => $email, 'email' => $email, 'password' => 'password']);
    }

    private function passesModule(User $user, string $method, string ...$modules): bool
    {
        $request = Request::create('/x', $method);
        $request->setUserResolver(fn () => $user);

        try {
            (new EnsureModuleAccess())->handle($request, fn () => response('ok'), ...$modules);

            return true;
        } catch (UnauthorizedException) {
            return false;
        }
    }

    public function test_module_middleware_splits_read_and_write(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $reader = $this->user('reader@example.com');
        $reader->givePermissionTo('payments.read');
        $writer = $this->user('writer@example.com');
        $writer->givePermissionTo('payments.update');

        $this->assertTrue($this->passesModule($reader, 'GET', 'payments'));
        $this->assertFalse($this->passesModule($reader, 'POST', 'payments'));
        $this->assertFalse($this->passesModule($writer, 'GET', 'payments'));
        $this->assertTrue($this->passesModule($writer, 'PATCH', 'payments'));
        $this->assertFalse($this->passesModule($reader, 'GET', 'invoices'));
    }

    public function test_admin_passes_module_middleware_without_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->user('admin@example.com');
        $admin->assignRole('admin');

        $this->assertTrue($this->passesModule($admin, 'GET', 'stocks'));
        $this->assertTrue($this->passesModule($admin, 'DELETE', 'stocks'));
    }

    public function test_legacy_role_name_alone_does_not_grant_module_access_when_permissions_are_removed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::findByName('Pagos');
        $role->syncPermissions([]);
        $user = $this->user();
        $user->assignRole('Pagos');

        $this->assertFalse($this->passesModule($user, 'GET', 'payments'));
        $this->assertFalse($user->can('payments.read'));
    }

    public function test_able_to_scope_returns_admins_and_permission_holders(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->user('admin@example.com');
        $admin->assignRole('admin');
        $viaRole = $this->user('role@example.com');
        $viaRole->assignRole('Orden de compra');
        $direct = $this->user('direct@example.com');
        $direct->givePermissionTo('purchase_orders.create');
        $other = $this->user('other@example.com');
        $other->givePermissionTo('payments.read');

        $ids = User::ableTo('purchase_orders.create')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$admin->id, $viaRole->id, $direct->id], $ids);
    }

    public function test_migration_grants_initial_profile_only_to_users_without_module_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $setup = app(ModulePermissionSetup::class);
        $legacy = Role::findByName('Moviles');
        $legacy->syncPermissions([]);

        $bare = $this->user('bare@example.com');
        $bare->assignRole($legacy);
        $configured = $this->user('configured@example.com');
        $configured->assignRole($legacy);
        $configured->givePermissionTo('mobile_assets.read');

        $setup->migrateAccessRolesToPermissions();

        $this->assertTrue($bare->fresh()->can('mobile_assets.delete'));
        $this->assertTrue($bare->fresh()->can('material_vouchers.read'));
        $this->assertFalse($configured->fresh()->can('mobile_assets.delete'));
    }

    public function test_migration_keeps_scope_extras_for_pagos_users(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $pagos = Role::findByName('Pagos');
        $pagos->syncPermissions([]);
        $user = $this->user();
        $user->assignRole($pagos);
        $user->givePermissionTo('payments.read');

        app(ModulePermissionSetup::class)->migrateAccessRolesToPermissions();

        $user = $user->fresh();
        $this->assertTrue($user->can('purchase_orders.view_all'));
        $this->assertTrue($user->can('payments.register_any'));
        $this->assertFalse($user->can('payments.update'));
        $this->assertTrue(Permission::where('name', 'invoices.view_all')->exists());
    }

    public function test_invoice_buyer_filter_depends_on_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $buyer = $this->user('buyer@example.com');
        $buyer->assignRole('Orden de compra');
        $payments = $this->user('payments@example.com');
        $payments->assignRole('Pagos');

        $this->assertSame($buyer->id, $buyer->invoiceBuyerFilter());
        $this->assertNull($payments->invoiceBuyerFilter());
    }

    public function test_tools_and_concepts_modules_are_independent_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $toolsReader = $this->user('tools@example.com');
        $toolsReader->givePermissionTo('tools.read');
        $conceptsWriter = $this->user('concepts@example.com');
        $conceptsWriter->givePermissionTo('concepts.update');

        $this->assertTrue($this->passesModule($toolsReader, 'GET', 'tools'));
        $this->assertFalse($this->passesModule($toolsReader, 'POST', 'tools'));
        $this->assertFalse($this->passesModule($toolsReader, 'GET', 'concepts'));
        $this->assertTrue($this->passesModule($conceptsWriter, 'PUT', 'concepts'));
        $this->assertFalse($this->passesModule($conceptsWriter, 'GET', 'tools'));
        $this->assertTrue($this->user('admin@example.com')->assignRole('admin')->can('tools.delete'));
    }

    public function test_tools_and_concepts_migration_keeps_access_of_solmat_and_moviles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Role::findByName('Solmat')->syncPermissions(['material_requests.read']);
        Role::findByName('Moviles')->syncPermissions(['mobile_assets.read']);
        $solmat = $this->user('solmat@example.com');
        $solmat->assignRole('Solmat');
        $moviles = $this->user('moviles@example.com');
        $moviles->assignRole('Moviles');

        app(ModulePermissionSetup::class)->migrateToolsAndConceptsPermissions();

        $solmat = $solmat->fresh();
        $moviles = $moviles->fresh();
        $this->assertTrue($solmat->can('tools.update'));
        $this->assertTrue($solmat->can('concepts.delete'));
        $this->assertTrue($moviles->can('tools.read'));
        $this->assertFalse($moviles->can('concepts.read'));
    }

    public function test_notification_inbox_routes_are_admin_only(): void
    {
        foreach (['notifications.index', 'notifications.inbox', 'notifications.markAllRead'] as $name) {
            $middleware = app('router')->getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('role:admin', $middleware, $name);
        }
    }
}
