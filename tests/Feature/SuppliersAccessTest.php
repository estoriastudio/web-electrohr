<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 2 (Proveedores): Listado y Estado de Cuenta piden los permisos de la matriz.
 * Compras da de alta y edita; Dirección, Almacén y Pagos consultan; exportar es solo admin (decisión 2026-11-11).
 */
class SuppliersAccessTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);

        $this->supplier = Supplier::create(['rfc_name' => 'Proveedor Uno']);
    }

    public function test_purchasing_creates_and_edits_suppliers_but_does_not_export(): void
    {
        $this->actingAs($this->userWithRole('Orden de compra'));

        $this->get(route('suppliers.index'))->assertOk()->assertSee('Proveedor Uno')->assertSee('Importar')->assertDontSee(route('suppliers.export'));
        $this->get(route('suppliers.edit', $this->supplier))->assertOk();
        $this->post(route('suppliers.store'), [])->assertSessionHasErrors();
        $this->get(route('suppliers.export'))->assertForbidden();
        $this->get(route('suppliers.account_statement.index'))->assertOk()->assertDontSee('Exportar Excel');
        $this->get(route('suppliers.account_statement.export'))->assertForbidden();
    }

    public function test_view_only_roles_consult_suppliers_without_editing(): void
    {
        foreach (['Pagos', 'Almacén', 'Dirección'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(route('suppliers.index'))->assertOk()->assertSee('Proveedor Uno')->assertDontSee('Importar');
            $this->get(route('suppliers.show', $this->supplier))->assertOk();
            $this->get(route('suppliers.edit', $this->supplier))->assertForbidden();
            $this->delete(route('suppliers.destroy', $this->supplier))->assertForbidden();
            $this->post(route('suppliers.portal_access.enable', $this->supplier))->assertForbidden();
        }

        $this->actingAs($this->userWithRole('Almacén'))
            ->get(route('suppliers.account_statement.index'))
            ->assertForbidden();
    }

    public function test_only_admin_exports(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $this->get(route('suppliers.index'))->assertOk()->assertSee(route('suppliers.export'));
        $this->get(route('suppliers.account_statement.index'))->assertOk()->assertSee('Exportar Excel');
    }

    public function test_navbar_shows_supplier_items_by_permission(): void
    {
        $this->actingAs($this->userWithRole('Almacén'))
            ->get(route('suppliers.index'))
            ->assertSee('href="'.route('suppliers.index').'"', false)
            ->assertDontSee('href="'.route('suppliers.account_statement.index').'"', false);

        $this->actingAs($this->userWithRole('Supervisor'))
            ->get(route('my-projects.index'))
            ->assertDontSee('href="'.route('suppliers.index').'"', false);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return User::factory()->create()->assignRole($role);
    }
}
