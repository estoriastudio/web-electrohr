<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 2 (Proyectos): las rutas piden los permisos `projects.*` de la matriz.
 * Gerente y Supervisor solo ven sus proyectos; documentos y convenios son de quien edita.
 */
class ProjectsAccessTest extends TestCase
{
    use RefreshDatabase;

    private Project $ownProject;

    private Project $otherProject;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);

        $this->supervisor = $this->userWithRole('Supervisor');
        $this->ownProject = Project::create(['name' => 'Proyecto propio', 'client_name' => 'Cliente', 'status' => 'active', 'currency' => 'MXN']);
        $this->otherProject = Project::create(['name' => 'Proyecto ajeno', 'client_name' => 'Cliente', 'status' => 'active', 'currency' => 'MXN']);
        ProjectWork::create(['project_id' => $this->ownProject->id, 'name' => 'Obra propia', 'status' => 'active', 'supervisor_user_id' => $this->supervisor->id]);
        ProjectWork::create(['project_id' => $this->otherProject->id, 'name' => 'Obra ajena', 'status' => 'active']);
    }

    public function test_view_only_roles_see_every_project_without_documents_or_edit_controls(): void
    {
        foreach (['Almacén', 'Orden de compra', 'Pagos', 'Recursos Humanos', 'Moviles'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(route('projects.index'))
                ->assertOk()
                ->assertSee('Proyecto propio')
                ->assertSee('Proyecto ajeno')
                ->assertDontSee('Nuevo Proyecto');

            $this->get(route('projects.show', $this->otherProject))
                ->assertOk()
                ->assertSee('Estimaciones')
                ->assertDontSee('Documentación del Proyecto')
                ->assertDontSee('Registrar estimación')
                ->assertDontSee('Nueva Obra');

            $this->post(route('projects.store'), $this->projectData('Nuevo'))->assertForbidden();
            $this->put(route('projects.update', $this->otherProject), $this->projectData('Cambio'))->assertForbidden();
            $this->delete(route('projects.destroy', $this->otherProject))->assertForbidden();
            $this->post(route('projects.estimates.store', $this->otherProject), [])->assertForbidden();
            $this->patch(route('projects.document.applicability', [$this->otherProject, 'contrato']), [])->assertForbidden();
        }

        $this->assertDatabaseHas('projects', ['id' => $this->otherProject->id, 'name' => 'Proyecto ajeno']);
    }

    public function test_contracts_and_direction_create_projects_and_manage_documents(): void
    {
        foreach (['Contratos', 'Dirección'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(route('projects.index'))->assertOk()->assertSee('Nuevo Proyecto');
            $this->get(route('projects.show', $this->otherProject))
                ->assertOk()
                ->assertSee('Documentación del Proyecto')
                ->assertSee('Registrar estimación')
                ->assertSee('Nueva Obra');

            $this->post(route('projects.store'), $this->projectData("Proyecto {$role}"))->assertRedirect();
            $this->assertDatabaseHas('projects', ['name' => "Proyecto {$role}"]);
        }
    }

    public function test_project_staff_can_be_chosen_as_manager_or_work_responsible(): void
    {
        $this->actingAs($this->userWithRole('Contratos'));
        $manager = $this->userWithRole('Gerente de Proyecto');

        $this->put(route('projects.update', $this->otherProject), $this->projectData('Proyecto ajeno', ['manager_user_id' => $manager->id]))
            ->assertRedirect(route('projects.show', $this->otherProject));
        $this->assertSame($manager->id, $this->otherProject->fresh()->manager_user_id);

        $this->put(route('projects.update', $this->otherProject), $this->projectData('Proyecto ajeno', ['manager_user_id' => $this->userWithRole('Pagos')->id]))
            ->assertSessionHasErrors('manager_user_id');

        $this->post(route('project_works.store'), [
            'project_id' => $this->otherProject->id,
            'name' => 'Obra nueva',
            'supervisor_user_id' => $this->supervisor->id,
        ])->assertRedirect(route('projects.show', $this->otherProject));
        $this->assertDatabaseHas('project_works', ['name' => 'Obra nueva', 'supervisor_user_id' => $this->supervisor->id]);
    }

    public function test_navbar_shows_projects_or_my_projects_by_scope(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('my-projects.index'))
            ->assertOk()
            ->assertSee('Proyecto propio')
            ->assertDontSee('Proyecto ajeno')
            ->assertSee('Mis Proyectos')
            ->assertDontSee('href="'.route('projects.index').'"', false);

        $warehouse = $this->userWithRole('Almacén');
        $this->actingAs($warehouse)
            ->get(route('projects.index'))
            ->assertSee('href="'.route('projects.index').'"', false)
            ->assertDontSee('href="'.route('my-projects.index').'"', false);

        // Almacén que además supervisa una obra también ve Mis Proyectos.
        $warehouse->assignRole('Supervisor');
        ProjectWork::create(['project_id' => $this->otherProject->id, 'name' => 'Obra de almacén', 'status' => 'active', 'supervisor_user_id' => $warehouse->id]);
        $this->actingAs($warehouse->fresh())
            ->get(route('projects.index'))
            ->assertSee('href="'.route('my-projects.index').'"', false);
    }

    public function test_users_without_matrix_roles_are_rejected(): void
    {
        $this->actingAs($this->userWithRole('Engineer'));

        $this->get(route('my-projects.index'))->assertForbidden();
        $this->get(route('projects.index'))->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return User::factory()->create()->assignRole($role);
    }

    /** @return array<string, mixed> */
    private function projectData(string $name, array $overrides = []): array
    {
        return array_merge([
            'name' => $name,
            'client_name' => 'Cliente',
            'status' => 'active',
            'currency' => 'MXN',
        ], $overrides);
    }
}
