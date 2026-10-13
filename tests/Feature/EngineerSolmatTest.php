<?php

namespace Tests\Feature;

use App\Models\MaterialRequest;
use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EngineerSolmatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Role::findOrCreate('Engineer', 'web');
    }

    public function test_admin_assigns_an_engineer_as_project_manager_and_rejects_other_users(): void
    {
        $admin = $this->userWithRole('admin');
        $engineer = $this->userWithRole('Engineer');
        $project = $this->project('Proyecto');

        $this->actingAs($admin)
            ->put(route('projects.update', $project), $this->projectData(['manager_user_id' => $engineer->id]))
            ->assertRedirect(route('projects.show', $project));
        $this->assertSame($engineer->id, $project->fresh()->manager_user_id);

        $this->put(route('projects.update', $project), $this->projectData(['manager_user_id' => $this->userWithRole('Solmat')->id]))
            ->assertSessionHasErrors('manager_user_id');
        $this->assertSame($engineer->id, $project->fresh()->manager_user_id);

        $this->put(route('projects.update', $project), $this->projectData(['manager_user_id' => '']))
            ->assertRedirect(route('projects.show', $project));
        $this->assertNull($project->fresh()->manager_user_id);
    }

    public function test_project_manager_can_open_the_project_without_being_supervisor_or_resident(): void
    {
        $manager = $this->userWithRole('Engineer');
        $project = $this->project('Proyecto gestionado', manager: $manager);
        $this->project('Proyecto ajeno');

        $this->actingAs($manager)
            ->get(route('my-projects.index'))
            ->assertOk()
            ->assertSee('Proyecto gestionado')
            ->assertDontSee('Proyecto ajeno');
        $this->get(route('my-projects.show', $project))->assertOk()->assertSee('Gerente: '.$manager->name);
    }

    public function test_engineer_creates_a_solmat_in_own_project_only(): void
    {
        $engineer = $this->userWithRole('Engineer');
        $own = $this->project('Proyecto propio', supervisor: $engineer);
        $foreign = $this->project('Proyecto ajeno', supervisor: $this->userWithRole('Engineer'));

        $this->actingAs($engineer)
            ->get(route('my-projects.show', $own))
            ->assertOk()
            ->assertSee('Nueva SOLMAT');

        $this->post(route('material_requests.store'), $this->solmatData($own))
            ->assertRedirect();

        $materialRequest = MaterialRequest::firstOrFail();
        $this->assertSame($own->id, $materialRequest->project_id);
        $this->assertSame($engineer->id, $materialRequest->requested_by);
        $this->assertSame('pending', $materialRequest->status);
        $this->assertSame([$own->works()->value('id')], $materialRequest->projectWorks()->pluck('project_works.id')->all());

        $this->post(route('material_requests.store'), $this->solmatData($foreign))->assertForbidden();

        $mixedWorks = $this->solmatData($own, ['project_work_ids' => [$foreign->works()->value('id')]]);
        $this->post(route('material_requests.store'), $mixedWorks)->assertSessionHasErrors('project_work_ids');
        $this->assertDatabaseCount('material_requests', 1);
    }

    public function test_my_projects_lists_only_the_solmat_of_that_project(): void
    {
        $engineer = $this->userWithRole('Engineer');
        $own = $this->project('Proyecto propio', supervisor: $engineer);
        $foreign = $this->project('Proyecto ajeno', supervisor: $this->userWithRole('Engineer'));
        $this->materialRequest($own, 7001);
        $this->materialRequest($foreign, 7002);

        $this->actingAs($engineer)
            ->get(route('my-projects.show', $own))
            ->assertOk()
            ->assertSee('7001')
            ->assertDontSee('7002');
    }

    public function test_solmat_tab_uses_the_original_table_with_traceability_links(): void
    {
        $engineer = $this->userWithRole('Engineer');
        $project = $this->project('Proyecto propio', supervisor: $engineer);
        $materialRequest = $this->materialRequest($project, 7001);
        $purchaseRequest = PurchaseRequest::create([
            'folio' => 8001,
            'material_request_id' => $materialRequest->id,
            'project_id' => $project->id,
            'project_work_id' => $project->works()->value('id'),
            'zone' => 'Zona de prueba',
            'delivery_address' => 'Dirección de prueba',
            'short_description' => 'Compra de prueba',
            'request_date' => '2026-08-27',
            'need_date' => '2026-09-01',
            'status' => 'linked',
            'requested_by' => $this->userWithRole('Solcom')->id,
        ]);
        $supplier = Supplier::create(['rfc_name' => 'Proveedor']);
        $purchaseOrder = PurchaseOrder::create([
            'folio' => 9001,
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'purchase_request_id' => $purchaseRequest->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => 'autorizada',
            'recurrence_type' => 'unico',
        ]);

        $this->actingAs($engineer)
            ->get(route('my-projects.show', $project))
            ->assertOk()
            ->assertSee('po-trace-map', false)
            ->assertSee('Trazabilidad')
            ->assertSee(route('purchase_requests.show', $purchaseRequest), false)
            ->assertSee(route('purchase_orders.show', $purchaseOrder), false)
            ->assertSee(route('material_requests.show', $materialRequest), false)
            ->assertSee('Proyecto / Obra')
            ->assertSee('Vencimientos')
            ->assertDontSee(route('purchase_orders.pdf', $purchaseOrder), false)
            ->assertDontSee(route('suppliers.show', $supplier), false)
            ->assertDontSee('Saldo cubierto')
            ->assertDontSee('Pago:')
            ->assertDontSee('Archivar');

        $this->get(route('purchase_requests.show', $purchaseRequest))->assertOk();
        $this->get(route('purchase_orders.show', $purchaseOrder))->assertOk();
    }

    public function test_engineer_edits_solmat_header_items_and_notes_while_editable(): void
    {
        $engineer = $this->userWithRole('Engineer');
        $own = $this->project('Proyecto propio', supervisor: $engineer);
        $foreign = $this->project('Proyecto ajeno', supervisor: $this->userWithRole('Engineer'));
        $materialRequest = $this->materialRequest($own, 7001);
        $foreignRequest = $this->materialRequest($foreign, 7002);
        $workId = $own->works()->value('id');

        $this->actingAs($engineer);

        $this->get(route('material_requests.edit', $materialRequest))->assertOk();
        $this->get(route('material_requests.edit', $foreignRequest))->assertForbidden();

        $this->put(route('material_requests.update', $materialRequest), $this->solmatData($own, ['zone' => 'Zona nueva']))
            ->assertRedirect(route('material_requests.show', $materialRequest));
        $this->assertSame('Zona nueva', $materialRequest->fresh()->zone);
        $this->put(route('material_requests.update', $foreignRequest), $this->solmatData($foreign))->assertForbidden();

        $this->postJson(route('material_requests.items.store', $materialRequest), [
            'code' => 'MAT-1',
            'description' => 'Cable',
            'unit' => 'M',
            'work_quantities' => [['work_id' => $workId, 'quantity' => 5]],
        ])->assertOk();
        $this->assertDatabaseHas('material_request_items', ['material_request_id' => $materialRequest->id, 'code' => 'MAT-1']);
        $this->postJson(route('material_requests.items.store', $foreignRequest), [
            'code' => 'MAT-1',
            'description' => 'Cable',
            'unit' => 'M',
            'work_quantities' => [['work_id' => $foreign->works()->value('id'), 'quantity' => 5]],
        ])->assertForbidden();

        $this->post(route('material_requests.notes.store', $materialRequest), ['text' => 'Nota'])->assertRedirect();
        $this->post(route('material_requests.notes.store', $foreignRequest), ['text' => 'Nota'])->assertForbidden();

        $materialRequest->update(['status' => 'sent_to_warehouse']);
        $this->get(route('material_requests.edit', $materialRequest))->assertForbidden();
        $this->put(route('material_requests.update', $materialRequest), $this->solmatData($own))->assertForbidden();
    }

    public function test_only_admin_solmat_or_the_project_manager_can_send_to_warehouse(): void
    {
        $supervisor = $this->userWithRole('Engineer');
        $manager = $this->userWithRole('Engineer');
        $project = $this->project('Proyecto propio', supervisor: $supervisor, manager: $manager);
        $otherProject = $this->project('Otro proyecto', supervisor: $this->userWithRole('Engineer'), manager: $this->userWithRole('Engineer'));
        $unmanaged = $this->project('Sin gerente', supervisor: $supervisor);

        $materialRequest = $this->materialRequest($project, 7001);
        $this->actingAs($supervisor)
            ->get(route('my-projects.show', $project))
            ->assertDontSee('Aprobar y Enviar a Almacén');
        $this->post(route('material_requests.send_to_warehouse', $materialRequest))->assertForbidden();
        $this->assertSame('pending', $materialRequest->fresh()->status);

        $this->actingAs($otherProject->manager)
            ->post(route('material_requests.send_to_warehouse', $materialRequest))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('my-projects.show', $project))
            ->assertSee('Aprobar y Enviar a Almacén');
        $this->get(route('material_requests.show', $materialRequest))->assertSee('Aprobar y Enviar a Almacén');
        $this->post(route('material_requests.send_to_warehouse', $materialRequest))
            ->assertRedirect(route('material_requests.show', $materialRequest));
        $this->assertSame('sent_to_warehouse', $materialRequest->fresh()->status);

        $adminRequest = $this->materialRequest($project, 7002);
        $this->actingAs($this->userWithRole('admin'))
            ->post(route('material_requests.send_to_warehouse', $adminRequest))
            ->assertRedirect();
        $this->assertSame('sent_to_warehouse', $adminRequest->fresh()->status);

        $solmatRequest = $this->materialRequest($unmanaged, 7003);
        $this->actingAs($this->userWithRole('Solmat'))
            ->post(route('material_requests.send_to_warehouse', $solmatRequest))
            ->assertRedirect();
        $this->assertSame('sent_to_warehouse', $solmatRequest->fresh()->status);

        $noManager = $this->materialRequest($unmanaged, 7004);
        $this->actingAs($supervisor)
            ->post(route('material_requests.send_to_warehouse', $noManager))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function project(string $name, ?User $supervisor = null, ?User $manager = null): Project
    {
        $project = Project::create([
            'name' => $name,
            'client_name' => 'Cliente de prueba',
            'currency' => 'MXN',
            'status' => 'active',
            'manager_user_id' => $manager?->id,
        ]);

        ProjectWork::create([
            'project_id' => $project->id,
            'name' => 'Obra de '.$name,
            'status' => 'active',
            'supervisor_user_id' => $supervisor?->id,
        ]);

        return $project;
    }

    private function projectData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Proyecto',
            'client_name' => 'Cliente de prueba',
            'status' => 'active',
            'currency' => 'MXN',
        ], $overrides);
    }

    private function solmatData(Project $project, array $overrides = []): array
    {
        return array_merge([
            'project_id' => $project->id,
            'project_work_ids' => [$project->works()->value('id')],
            'zone' => 'Zona de prueba',
            'delivery_address' => 'Dirección de prueba',
            'location_type' => 'sitio',
            'request_date' => '2026-08-27',
            'need_date' => '2026-09-01',
            'supply_category' => 'Materiales',
        ], $overrides);
    }

    private function materialRequest(Project $project, int $folio): MaterialRequest
    {
        $materialRequest = MaterialRequest::create([
            'folio' => $folio,
            'project_id' => $project->id,
            'zone' => 'Zona de prueba',
            'delivery_address' => 'Dirección de prueba',
            'location_type' => 'sitio',
            'request_date' => '2026-08-27',
            'need_date' => '2026-09-01',
            'supply_category' => 'Materiales',
            'status' => 'pending',
            'requested_by' => $this->userWithRole('Solmat')->id,
        ]);
        $materialRequest->projectWorks()->sync([$project->works()->value('id')]);

        return $materialRequest;
    }
}
