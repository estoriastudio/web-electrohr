<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccessSimulation;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Selector de la barra superior para que el admin vea el sistema como otro rol o usuario. */
class AccessSimulationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);

        config(['access_simulation.enabled' => true, 'access_simulation.admin_email' => 'admin@electrohr.test']);

        $this->admin = User::factory()->create(['email' => 'admin@electrohr.test'])->assignRole('admin');
        $this->supervisor = User::factory()->create(['name' => 'Sofía Supervisora', 'remember_token' => 'token-sup'])->assignRole('Supervisor');
    }

    /**
     * En el servidor cada petición carga el usuario de cero; en los tests el guard y el usuario
     * se reutilizan entre peticiones, así que cada una parte de un admin recién leído.
     */
    private function asAdmin(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->admin->fresh());
    }

    public function test_disabled_simulation_hides_selector_and_routes(): void
    {
        config(['access_simulation.enabled' => false]);

        $this->asAdmin()->get(route('dashboard'))->assertOk()->assertDontSee('Simular acceso');
        $this->asAdmin()->post(route('access_simulation.start'), ['role' => 'Supervisor'])->assertNotFound();
    }

    public function test_only_the_configured_admin_can_simulate(): void
    {
        $otherAdmin = User::factory()->create(['email' => 'otro@electrohr.test'])->assignRole('admin');

        $this->actingAs($otherAdmin)->get(route('dashboard'))->assertOk()->assertDontSee('Simular acceso');
        $this->actingAs($otherAdmin)->post(route('access_simulation.start'), ['role' => 'Supervisor'])->assertForbidden();
        $this->actingAs($this->supervisor)->post(route('access_simulation.start'), ['role' => 'Dirección'])->assertForbidden();

        $this->asAdmin()->get(route('dashboard'))->assertOk()->assertSee('Simular acceso');
    }

    public function test_role_simulation_applies_the_role_permissions_without_touching_the_database(): void
    {
        $this->asAdmin()->get(route('direction.inbox'))->assertOk();

        $this->asAdmin()
            ->post(route('access_simulation.start'), ['role' => 'Supervisor'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(AccessSimulation::SESSION_ROLE, 'Supervisor');

        $this->asAdmin()->get(route('dashboard'))->assertOk()->assertSee('Estás viendo el sistema como', false);
        $this->asAdmin()->get(route('direction.inbox'))->assertForbidden();
        $this->asAdmin()->get(route('projects.index'))->assertOk();

        $this->assertSame(['admin'], $this->admin->fresh()->getRoleNames()->all());

        $this->asAdmin()->delete(route('access_simulation.stop'))->assertRedirect(route('dashboard'));
        $this->asAdmin()->get(route('direction.inbox'))->assertOk();
    }

    public function test_user_simulation_serves_requests_as_that_user(): void
    {
        $this->asAdmin()
            ->post(route('access_simulation.start'), ['role' => 'Supervisor', 'user_id' => $this->supervisor->id])
            ->assertSessionHas(AccessSimulation::SESSION_USER, $this->supervisor->id);

        $this->asAdmin()->get(route('dashboard'))->assertOk()->assertSee('Estás navegando como', false)->assertSee('Sofía Supervisora');
        $this->asAdmin()->get(route('direction.inbox'))->assertForbidden();

        // Cerrar sesión se atiende como el admin: no toca el remember token del usuario simulado.
        $this->asAdmin()->post(route('logout'));
        $this->assertSame('token-sup', $this->supervisor->fresh()->remember_token);
        $this->assertGuest();
    }

    public function test_rejects_users_and_roles_outside_the_matrix(): void
    {
        $userWithoutRole = User::factory()->create();

        $this->asAdmin()
            ->post(route('access_simulation.start'), ['role' => 'Supervisor', 'user_id' => $userWithoutRole->id])
            ->assertSessionHasErrors('user_id');
        $this->asAdmin()
            ->post(route('access_simulation.start'), ['role' => 'admin'])
            ->assertSessionHasErrors('role');
    }

    public function test_supplier_portal_needs_a_supplier_user(): void
    {
        Role::findOrCreate('supplier_portal_access', 'web');
        $supplierUser = User::factory()->create()->assignRole('supplier_portal_access');

        $this->asAdmin()
            ->post(route('access_simulation.start'), ['role' => 'supplier_portal_access'])
            ->assertSessionHasErrors('user_id');

        $this->asAdmin()
            ->post(route('access_simulation.start'), ['role' => 'supplier_portal_access', 'user_id' => $supplierUser->id])
            ->assertSessionHas(AccessSimulation::SESSION_USER, $supplierUser->id);
    }
}
