<?php

namespace Tests\Feature;

use App\Models\MobileAsset;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 2 (Bienes Móviles y Herramientas): las rutas piden los permisos de la matriz.
 * Exportar y descargar documentación en ZIP es D: solo admin.
 */
class MobileAssetsAccessTest extends TestCase
{
    use RefreshDatabase;

    private MobileAsset $asset;

    private Tool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);

        $this->asset = MobileAsset::forceCreate(['name' => 'Camioneta 1', 'type' => 'parque_vehicular']);
        $category = ToolCategory::create(['name' => 'Eléctricas', 'status' => 'active']);
        $this->tool = Tool::forceCreate([
            'tool_category_id' => $category->id,
            'economic_number' => 'HER-001',
            'name' => 'Taladro',
            'description' => 'Taladro percutor',
            'status' => 'active',
        ]);
    }

    public function test_mobile_operators_manage_assets_but_do_not_export(): void
    {
        $this->actingAs($this->userWithRole('Moviles'));

        $this->get(route('mobile_assets.index'))->assertOk()->assertSee('Camioneta 1')->assertSee('Nuevo bien')->assertDontSee(route('mobile_assets.export'));
        $this->get(route('mobile_assets.edit', $this->asset))->assertOk();
        $this->get(route('mobile_assets.export'))->assertForbidden();
        $this->get(route('mobile_assets.documents.zip', $this->asset))->assertForbidden();
    }

    public function test_manager_and_direction_only_consult_mobile_assets(): void
    {
        foreach (['Gerente de Proyecto', 'Dirección'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(route('mobile_assets.index'))->assertOk()->assertSee('Camioneta 1')->assertDontSee('Nuevo bien');
            $this->get(route('mobile_assets.show', $this->asset))->assertOk()->assertDontSee('Editar bien');
            $this->get(route('mobile_assets.edit', $this->asset))->assertForbidden();
            $this->post(route('maintenance_logs.store', $this->asset), [])->assertForbidden();
        }

        $this->actingAs($this->userWithRole('Supervisor'))->get(route('mobile_assets.index'))->assertForbidden();
    }

    public function test_warehouse_manages_tools_controls_and_categories(): void
    {
        $this->actingAs($this->userWithRole('Almacén'));

        $this->get(route('tools.index'))->assertOk()->assertSee('HER-001')->assertSee('Nueva Herramienta');
        $this->get(route('tools.show', $this->tool))->assertOk()->assertSee('Nuevo registro');
        $this->get(route('tool_controls.index'))->assertOk();
        $this->get(route('tool_categories.index'))->assertOk();
        $this->post(route('tool_controls.store'), [])->assertSessionHasErrors();
    }

    public function test_site_staff_only_consult_tools_and_mobile_cannot_register_use(): void
    {
        $this->actingAs($this->userWithRole('Supervisor'));
        $this->get(route('tools.index'))->assertOk()->assertSee('HER-001')->assertDontSee('Nueva Herramienta');
        $this->get(route('tools.show', $this->tool))->assertOk()->assertDontSee('Nuevo registro');
        $this->get(route('tool_controls.index'))->assertOk();
        $this->post(route('tool_controls.store'), [])->assertForbidden();
        $this->put(route('tools.update', $this->tool), [])->assertForbidden();
        $this->get(route('tool_categories.index'))->assertForbidden();

        // Móviles edita el registro de herramientas, pero no registra el control de uso.
        $this->actingAs($this->userWithRole('Moviles'));
        $this->get(route('tools.index'))->assertOk()->assertSee('Nueva Herramienta')->assertDontSee('href="'.route('tool_controls.index').'"', false);
        $this->get(route('tool_controls.index'))->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return User::factory()->create()->assignRole($role);
    }
}
