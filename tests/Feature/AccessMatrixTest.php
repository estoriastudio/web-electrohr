<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\User;
use App\Services\AccessMatrix;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private AccessMatrix $matrix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);
        $this->matrix = app(AccessMatrix::class);
    }

    public function test_seeder_gives_every_role_exactly_its_matrix_permissions_plus_legacy_crud(): void
    {
        foreach (array_keys($this->matrix->roles()) as $profile) {
            $role = Role::findByName($this->matrix->roleName($profile), 'web');
            $dotted = $role->permissions->pluck('name')->filter(fn ($name) => str_contains($name, '.'))->sort()->values()->all();
            $expected = collect($this->matrix->permissionsFor($profile))->sort()->values()->all();

            $this->assertSame($expected, $dotted, $profile);
        }

        $this->assertTrue(Role::findByName('Pagos', 'web')->hasPermissionTo('read'));
        $this->assertFalse(Role::findByName('Dirección', 'web')->hasPermissionTo('read'));
    }

    public function test_seeder_is_idempotent_and_removes_permissions_dropped_from_the_matrix(): void
    {
        Permission::findOrCreate('vista_retirada.view', 'web');

        $this->seed(AccessMatrixSeeder::class);

        $this->assertSame(
            count($this->matrix->allPermissions()),
            Permission::where('name', 'like', '%.%')->count(),
        );
        $this->assertTrue(Permission::where('name', 'read')->exists());
    }

    public function test_admin_passes_every_permission_check(): void
    {
        $admin = $this->userWithRoles('admin');

        $this->assertTrue($admin->can('purchase_orders.authorize'));
        $this->assertTrue($admin->can('permiso_inexistente'));
        $this->assertSame([AccessMatrix::ALL], $this->matrix->scopesFor($admin, 'hr_workers'));
    }

    public function test_new_roles_grant_matrix_permissions(): void
    {
        $director = $this->userWithRoles('Dirección');

        $this->assertTrue($director->can('payment_authorizations.authorize'));
        $this->assertFalse($director->can('users.view'));
        $this->assertFalse($director->can('purchase_orders.authorize'));
    }

    public function test_scopes_and_hidden_fields_combine_the_user_profiles(): void
    {
        $supervisor = $this->userWithRoles('Supervisor');
        $this->assertSame(['assigned_workers'], $this->matrix->scopesFor($supervisor, 'hr_workers'));
        $this->assertSame(['salary'], $this->matrix->hiddenFieldsFor($supervisor, 'hr_workers'));
        $this->assertSame([], $this->matrix->scopesFor($supervisor, 'stocks'));

        $warehouseSupervisor = $this->userWithRoles('Almacén', 'Supervisor');
        $this->assertSame([AccessMatrix::ALL], $this->matrix->scopesFor($warehouseSupervisor, 'material_requests'));

        $hr = $this->userWithRoles('Recursos Humanos', 'Supervisor');
        $this->assertSame([], $this->matrix->hiddenFieldsFor($hr, 'hr_workers'));
    }

    public function test_migration_plan_maps_legacy_roles_to_profiles(): void
    {
        $manager = $this->userWithRoles('Engineer');
        $supervisor = $this->userWithRoles('Engineer');
        $unassigned = $this->userWithRoles('Engineer');
        $warehouse = $this->userWithRoles('Solmat', 'Inventario');
        $fieldWarehouse = $this->userWithRoles('Solmat');
        $engineerSolmat = $this->userWithRoles('Engineer', 'Solmat');
        $projects = $this->userWithRoles('Proyectos');
        $helper = $this->userWithRoles('Ayudante Movil');
        $buyer = $this->userWithRoles('Orden de compra');

        $project = Project::create([
            'name' => 'Proyecto',
            'client_name' => 'Cliente',
            'currency' => 'MXN',
            'status' => 'active',
            'manager_user_id' => $manager->id,
        ]);
        ProjectWork::create([
            'project_id' => $project->id,
            'name' => 'Obra',
            'status' => 'active',
            'supervisor_user_id' => $supervisor->id,
            'resident_user_id' => $fieldWarehouse->id,
        ]);

        $this->artisan('access:migrate-users', ['--apply' => true])->assertSuccessful();

        $this->assertRoles($manager, ['Engineer', 'Gerente de Proyecto']);
        $this->assertRoles($supervisor, ['Engineer', 'Supervisor']);
        $this->assertRoles($unassigned, ['Engineer', 'Supervisor']);
        $this->assertRoles($warehouse, ['Almacén', 'Inventario', 'Solmat']);
        $this->assertRoles($fieldWarehouse, ['Almacén', 'Solmat', 'Supervisor']);
        $this->assertRoles($engineerSolmat, ['Engineer', 'Solmat', 'Supervisor']);
        $this->assertRoles($projects, ['Contratos', 'Proyectos']);
        $this->assertRoles($helper, ['Ayudante Movil']);
        $this->assertRoles($buyer, ['Orden de compra']);
    }

    public function test_migration_without_apply_only_shows_the_plan(): void
    {
        $engineer = $this->userWithRoles('Engineer');

        $this->artisan('access:migrate-users')
            ->expectsOutputToContain('Simulación')
            ->assertSuccessful();

        $this->assertRoles($engineer, ['Engineer']);
    }

    public function test_migration_fails_when_the_matrix_roles_are_not_seeded(): void
    {
        Role::findByName('Contratos', 'web')->delete();

        $this->artisan('access:migrate-users')->assertFailed();
    }

    private function userWithRoles(string ...$roles): User
    {
        $user = User::factory()->create();

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
            $user->assignRole($role);
        }

        return $user;
    }

    private function assertRoles(User $user, array $expected): void
    {
        $this->assertSame($expected, $user->fresh()->getRoleNames()->sort()->values()->all());
    }
}
