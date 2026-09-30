<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use App\Models\Supplier;
use App\Services\ModulePermissionSetup;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ModulePermissionsTest extends TestCase
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

    public function test_permissions_do_not_leak_between_modules(): void
    {
        $buyer = Role::create(['name' => 'Comprador', 'guard_name' => 'web']);
        $assistant = Role::create(['name' => 'Ayudante de pagos', 'guard_name' => 'web']);
        foreach (['purchase_orders.read', 'purchase_orders.create', 'purchase_orders.update', 'payments.read', 'update'] as $name) {
            Permission::create(['name' => $name, 'guard_name' => 'web']);
        }
        $buyer->syncPermissions(['purchase_orders.read', 'purchase_orders.create', 'purchase_orders.update', 'update']);
        $assistant->syncPermissions(['payments.read']);
        $user = User::create(['name' => 'Comprador', 'email' => 'buyer@example.com', 'password' => 'password']);
        $user->syncRoles([$buyer, $assistant]);
        $this->actingAs($user);

        $this->assertTrue($user->can('purchase_orders.update'));
        $this->assertTrue($user->can('payments.read'));
        $this->assertFalse($user->can('payments.update'));
        $this->assertFalse($user->can('invoices.approve'));
        $this->assertSame('compras', trim(Blade::render("@can('purchase_orders.update') compras @endcan @can('payments.update') pagos @endcan")));
    }

    public function test_admin_has_all_catalog_permissions_without_assignment(): void
    {
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $user->assignRole($admin);
        $this->assertTrue($user->can('payments.update'));
        $this->assertTrue($user->can('invoices.approve'));
    }

    public function test_migration_preserves_legacy_access_and_seeders_preserve_customizations(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::create(['name' => 'Legacy', 'email' => 'legacy@example.com', 'password' => 'password']);
        $user->assignRole('Pagos');
        app(ModulePermissionSetup::class)->migrateLegacyAssignments();
        $this->assertTrue($user->fresh()->can('payments.update'));
        $this->assertFalse($user->fresh()->can('purchase_orders.update'));

        $profile = Role::findByName('Pagos - permisos iniciales');
        $profile->syncPermissions(['payments.read']);
        Role::findByName('Pagos')->syncPermissions(['read']);
        $this->seed(RolesAndPermissionsSeeder::class);
        app(ModulePermissionSetup::class)->migrateLegacyAssignments();
        $this->assertFalse($user->fresh()->can('payments.update'));
        $this->assertTrue($user->fresh()->can('payments.read'));
        $this->assertSame(['read'], Role::findByName('Pagos')->permissions->pluck('name')->all());
    }

    public function test_predefined_profiles_support_buyer_and_read_only_payments(): void
    {
        app(ModulePermissionSetup::class)->initialize();
        $user = User::create(['name' => 'Mixed', 'email' => 'mixed@example.com', 'password' => 'password']);
        $user->syncRoles(['Comprador', 'Ayudante de pagos']);
        $this->assertTrue($user->can('purchase_orders.create'));
        $this->assertTrue($user->can('purchase_orders.update'));
        $this->assertTrue($user->can('payments.read'));
        $this->assertFalse($user->can('payments.create'));
        $this->assertFalse($user->can('payments.update'));
        $this->assertFalse($user->can('invoices.approve'));
    }

    public function test_admin_can_edit_a_profile_and_clear_its_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $admin->assignRole('admin');
        $role = Role::findByName('Administrador de pagos');
        $this->actingAs($admin)->put(route('roles.update', $role), ['permissions' => ['payments.read']])
            ->assertRedirect(route('usuarios.index', ['tab' => 'roles']));
        $this->assertSame(['payments.read'], $role->fresh()->permissions->pluck('name')->all());
        $this->put(route('roles.update', $role), [])->assertRedirect();
        $this->assertCount(0, $role->fresh()->permissions);
        $this->put(route('roles.update', Role::findByName('admin')), [])->assertForbidden();
    }

    public function test_role_editor_rejects_unknown_permissions_and_non_admin_users(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => 'password']);
        $role = Role::findByName('Ayudante de pagos');
        $this->actingAs($user)->put(route('roles.update', $role), ['permissions' => ['payments.update']])->assertForbidden();
        $user->assignRole('admin');
        $this->put(route('roles.update', $role), ['permissions' => ['unknown.permission']])->assertSessionHasErrors('permissions.0');
        $this->assertEqualsCanonicalizing(['payments.read', 'invoices.read'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_role_editor_rejects_permissions_from_another_guard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Permission::create(['name' => 'api.custom', 'guard_name' => 'api']);
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $user->assignRole('admin');
        $this->actingAs($user)->put(route('roles.update', Role::findByName('Ayudante de pagos')), ['permissions' => ['api.custom']])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_predefined_profile_changes_are_not_overwritten(): void
    {
        $setup = app(ModulePermissionSetup::class);
        $setup->initialize();
        Role::findByName('Administrador de pagos')->syncPermissions(['payments.read']);
        $setup->initialize();
        $this->assertSame(['payments.read'], Role::findByName('Administrador de pagos')->permissions->pluck('name')->all());
    }

    public function test_payment_actions_are_hidden_in_the_actual_table_for_read_only_profile(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::create(['name' => 'Mixed', 'email' => 'mixed@example.com', 'password' => 'password']);
        $user->syncRoles(['Orden de compra', 'Pagos', 'Comprador', 'Ayudante de pagos']);
        $this->actingAs($user);
        $supplier = new Supplier(['rfc_name' => 'Proveedor']);
        $supplier->id = 1;
        $order = new PurchaseOrder(['folio' => '100', 'currency' => 'MXN']);
        $order->id = 1;
        $order->setRelation('supplier', $supplier)->setRelation('milestones', collect())
            ->setRelation('projectRelation', null)->setRelation('workRelation', null);
        $milestone = new PurchaseOrderMilestone();
        $milestone->setRelation('purchaseOrder', $order);
        $payment = new Payment(['amount' => 100, 'status' => 'autorizado', 'payment_date' => now()]);
        $payment->id = 1;
        $payment->setRelation('milestone', $milestone);
        $data = ['payments' => collect([$payment]), 'urgentDate' => now()->addDays(7)];
        $this->assertStringNotContainsString('<form', view('payments.utilities.table', $data)->render());
        $user->assignRole('Administrador de pagos');
        $this->assertStringContainsString('<form', view('payments.utilities.table', $data)->render());
    }

    public function test_invoice_approval_form_is_hidden_but_files_remain_visible(): void
    {
        app(ModulePermissionSetup::class)->initialize();
        $user = User::create(['name' => 'Reader', 'email' => 'reader@example.com', 'password' => 'password']);
        $user->assignRole('Ayudante de pagos');
        $this->actingAs($user);
        $invoice = new PurchaseOrderInvoice(['folio' => 'F-100', 'status' => 'en_proceso', 'amount' => 100, 'currency' => 'MXN']);
        $invoice->id = 1;
        $invoice->setRelation('purchaseOrder', null)->setRelation('milestones', collect());
        View::share('errors', new ViewErrorBag());
        $template = str_replace("@extends('layouts.app')", '', file_get_contents(resource_path('views/invoices/show.blade.php')));
        $template .= "\n@yield('content')";
        $html = Blade::render($template, compact('invoice'));
        $this->assertStringNotContainsString('id="invoiceApprovalForm"', $html);
        $this->assertStringContainsString('Archivos', $html);
        $user->assignRole('Administrador de pagos');
        $this->assertStringContainsString('id="invoiceApprovalForm"', Blade::render($template, compact('invoice')));
    }
}