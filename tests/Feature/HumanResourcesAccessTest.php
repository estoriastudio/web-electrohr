<?php

namespace Tests\Feature;

use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerGroup;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 2 (RRHH): las rutas piden los permisos de la matriz y los controladores
 * filtran por obra a Gerente y Supervisor.
 */
class HumanResourcesAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $supervisor;

    private ProjectWork $ownWork;

    private ProjectWork $otherWork;

    private WorkerGroup $ownGroup;

    private WorkerGroup $otherGroup;

    private Worker $ownWorker;

    private Worker $otherWorker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);

        $this->manager = $this->userWithRole('Gerente de Proyecto');
        $this->supervisor = $this->userWithRole('Supervisor');

        $ownProject = Project::create(['name' => 'Propio', 'client_name' => 'Cliente', 'status' => 'active', 'manager_user_id' => $this->manager->id]);
        $otherProject = Project::create(['name' => 'Ajeno', 'client_name' => 'Cliente', 'status' => 'active']);
        $this->ownWork = ProjectWork::create(['project_id' => $ownProject->id, 'name' => 'Obra propia', 'status' => 'active', 'supervisor_user_id' => $this->supervisor->id]);
        $this->otherWork = ProjectWork::create(['project_id' => $otherProject->id, 'name' => 'Obra ajena', 'status' => 'active']);

        $this->ownGroup = WorkerGroup::create(['name' => 'Cuadrilla propia', 'project_work_id' => $this->ownWork->id, 'status' => 'active']);
        $this->otherGroup = WorkerGroup::create(['name' => 'Cuadrilla ajena', 'project_work_id' => $this->otherWork->id, 'status' => 'active']);

        $this->ownWorker = $this->workerIn($this->ownGroup, 'Propio');
        $this->otherWorker = $this->workerIn($this->otherGroup, 'Ajeno');
    }

    public function test_supervisor_only_sees_workers_and_groups_of_their_works(): void
    {
        $this->actingAs($this->supervisor);

        $this->get(route('human_resources.workers.show', $this->ownWorker))->assertOk();
        $this->get(route('human_resources.workers.show', $this->otherWorker))->assertForbidden();

        $this->get(route('human_resources.workers.index'))
            ->assertOk()
            ->assertSee('Propio')
            ->assertDontSee('Ajeno');

        $this->get(route('human_resources.worker-groups.index'))
            ->assertOk()
            ->assertSee('Cuadrilla propia')
            ->assertDontSee('Cuadrilla ajena');

        $this->get(route('human_resources.worker-groups.show', $this->ownGroup))->assertOk();
        $this->get(route('human_resources.worker-groups.show', $this->otherGroup))->assertForbidden();
        $this->get(route('human_resources.worker-groups.attendance', $this->otherGroup))->assertForbidden();
    }

    public function test_supervisor_does_not_see_salary_nor_payroll_receipts(): void
    {
        $line = $this->paidPayrollLineFor($this->ownWorker);

        $this->actingAs($this->supervisor);

        $this->get(route('human_resources.workers.show', $this->ownWorker))
            ->assertOk()
            ->assertDontSee('Sueldo semanal')
            ->assertDontSee('Recibos de nómina');

        $this->get(route('human_resources.workers.payroll-receipt.download', [$this->ownWorker, $line]))->assertForbidden();
        $this->get(route('human_resources.workers.export'))->assertForbidden();
    }

    public function test_supervisor_records_attendance_only_in_their_works(): void
    {
        $this->actingAs($this->supervisor);
        $payload = ['date' => today()->toDateString(), 'attended' => 1];

        $this->postJson(route('human_resources.worker-groups.attendance.mark', [$this->ownGroup, $this->ownWorker]), $payload)->assertSuccessful();
        $this->postJson(route('human_resources.worker-groups.attendance.mark', [$this->otherGroup, $this->otherWorker]), $payload)->assertForbidden();
    }

    public function test_supervisor_cannot_manage_groups_or_open_rh_only_screens(): void
    {
        $this->actingAs($this->supervisor);

        $this->post(route('human_resources.worker-groups.store'), $this->groupPayload($this->ownWork))->assertForbidden();
        $this->get(route('human_resources.payroll-periods.index'))->assertForbidden();
        $this->get(route('human_resources.position-categories.index'))->assertForbidden();
        $this->get(route('human_resources.worker-terminations.index'))->assertForbidden();

        $this->get(route('human_resources.worker-groups.index'))
            ->assertSee('Asistencias')
            ->assertDontSee('Nómina')
            ->assertDontSee('Nueva cuadrilla');
    }

    public function test_manager_sees_workers_of_managed_projects_and_creates_groups_only_there(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('human_resources.workers.show', $this->ownWorker))->assertOk();
        $this->get(route('human_resources.workers.show', $this->otherWorker))->assertForbidden();

        $this->post(route('human_resources.worker-groups.store'), $this->groupPayload($this->ownWork, 'Nueva propia'))
            ->assertRedirect(route('human_resources.worker-groups.index'));
        $this->assertDatabaseHas('worker_groups', ['name' => 'Nueva propia', 'project_work_id' => $this->ownWork->id]);

        $this->post(route('human_resources.worker-groups.store'), $this->groupPayload($this->otherWork, 'Nueva ajena'))
            ->assertSessionHasErrors('project_work_id');
        $this->assertDatabaseMissing('worker_groups', ['name' => 'Nueva ajena']);

        $this->get(route('human_resources.worker-groups.edit', $this->otherGroup))->assertForbidden();
    }

    public function test_direction_sees_everything_including_salary_but_changes_nothing(): void
    {
        $this->actingAs($this->userWithRole('Dirección'));

        $this->get(route('human_resources.workers.index'))->assertOk()->assertSee('Propio')->assertSee('Ajeno');
        $this->get(route('human_resources.workers.show', $this->otherWorker))->assertOk()->assertSee('Sueldo semanal');
        $this->get(route('human_resources.payroll-periods.index'))->assertOk()->assertDontSee('Crear periodo');
        $this->get(route('human_resources.position-categories.index'))->assertOk()->assertDontSee('Nueva categoría');

        $this->post(route('human_resources.workers.store'), [])->assertForbidden();
        $this->post(route('human_resources.worker-groups.store'), $this->groupPayload($this->ownWork))->assertForbidden();
        $this->post(route('human_resources.payroll-periods.store'), [])->assertForbidden();
        $this->postJson(route('human_resources.worker-groups.attendance.mark', [$this->ownGroup, $this->ownWorker]), ['date' => today()->toDateString(), 'attended' => 1])->assertForbidden();
    }

    public function test_human_resources_and_admin_keep_full_access(): void
    {
        foreach (['Recursos Humanos', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(route('human_resources.workers.index'))->assertOk()->assertSee('Propio')->assertSee('Ajeno');
            $this->get(route('human_resources.workers.show', $this->otherWorker))->assertOk()->assertSee('Sueldo semanal');
            $this->get(route('human_resources.worker-groups.show', $this->otherGroup))->assertOk();
            $this->get(route('human_resources.payroll-periods.index'))->assertOk()->assertSee('Crear periodo');
            $this->get(route('human_resources.piecework-entries.index'))->assertOk();
            $this->post(route('human_resources.worker-groups.store'), $this->groupPayload($this->otherWork, "Cuadrilla {$role}"))
                ->assertRedirect(route('human_resources.worker-groups.index'));
        }
    }

    public function test_roles_without_matrix_permissions_are_rejected(): void
    {
        // Engineer sin migrar: se queda sin RRHH hasta correr access:migrate-users.
        foreach (['Engineer', 'Pagos'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(route('human_resources.worker-groups.index'))->assertForbidden();
            $this->get(route('human_resources.workers.show', $this->ownWorker))->assertForbidden();
        }
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return User::factory()->create()->assignRole($role);
    }

    private function workerIn(WorkerGroup $group, string $lastName): Worker
    {
        $worker = Worker::create([
            'first_name' => 'Trabajador',
            'last_name' => $lastName,
            'weekly_salary' => 2500,
            'status' => 'active',
        ]);
        $group->members()->attach($worker->id, ['joined_at' => today()->subMonth()->toDateString()]);

        return $worker;
    }

    private function paidPayrollLineFor(Worker $worker): PayrollLine
    {
        $period = PayrollPeriod::create([
            'week_number' => 1,
            'year' => 2026,
            'start_date' => '2026-01-02',
            'end_date' => '2026-01-08',
            'cutoff_date' => '2026-01-08',
            'status' => 'paid',
        ]);

        return PayrollLine::forceCreate([
            'payroll_period_id' => $period->id,
            'worker_id' => $worker->id,
            'base_salary' => 2500,
        ]);
    }

    /** @return array<string, mixed> */
    private function groupPayload(ProjectWork $work, string $name = 'Cuadrilla nueva'): array
    {
        return [
            'name' => $name,
            'project_id' => $work->project_id,
            'project_work_id' => $work->id,
            'status' => 'active',
        ];
    }
}
