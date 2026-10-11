<?php

namespace Tests\Feature;

use App\Models\MaterialRequest;
use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\ProjectWork;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EngineerProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Role::findOrCreate('Engineer', 'web');
    }

    public function test_index_lists_only_projects_where_the_engineer_is_supervisor_or_resident(): void
    {
        $engineer = $this->engineer();
        $asSupervisor = $this->project('Proyecto supervisor', supervisor: $engineer);
        $asResident = $this->project('Proyecto residente', resident: $engineer);
        $this->project('Proyecto ajeno', supervisor: $this->engineer());

        $this->actingAs($engineer)
            ->get(route('my-projects.index'))
            ->assertOk()
            ->assertSee($asSupervisor->name)
            ->assertSee($asResident->name)
            ->assertDontSee('Proyecto ajeno');
    }

    public function test_show_is_forbidden_for_a_project_of_another_engineer(): void
    {
        $engineer = $this->engineer();
        $foreign = $this->project('Proyecto ajeno', supervisor: $this->engineer());

        $this->actingAs($engineer)->get(route('my-projects.show', $foreign))->assertForbidden();
    }

    public function test_show_lists_project_records_in_tabs_without_other_projects_data(): void
    {
        $engineer = $this->engineer();
        $project = $this->project('Proyecto propio', supervisor: $engineer);
        $foreign = $this->project('Proyecto ajeno', supervisor: $this->engineer());
        $this->materialRequest($project, 7001);
        $this->materialRequest($foreign, 7002);
        $this->purchaseRequest($project, 8001);
        $this->purchaseOrder($project, 9001);
        $this->purchaseOrder($foreign, 9002);

        $this->actingAs($engineer)
            ->get(route('my-projects.show', $project))
            ->assertOk()
            ->assertSee('Estimaciones y Obras')
            ->assertSee('SOLMAT')
            ->assertSee('SOLCOM')
            ->assertSee('Órdenes de Compra')
            ->assertSee('7001')
            ->assertSee('8001')
            ->assertSee('9001')
            ->assertDontSee('7002')
            ->assertDontSee('9002');
    }

    public function test_engineer_cannot_use_the_generic_projects_and_obras_routes(): void
    {
        $engineer = $this->engineer();
        $project = $this->project('Proyecto propio', supervisor: $engineer);

        $this->actingAs($engineer)->get(route('projects.index'))->assertForbidden();
        $this->actingAs($engineer)->get(route('projects.show', $project))->assertForbidden();
        $this->actingAs($engineer)->delete(route('projects.destroy', $project))->assertForbidden();
        $this->actingAs($engineer)->get(route('project_works.show', $project->works()->first()))->assertForbidden();
    }

    public function test_record_details_are_scoped_to_the_engineers_projects(): void
    {
        $engineer = $this->engineer();
        $own = $this->project('Proyecto propio', supervisor: $engineer);
        $foreign = $this->project('Proyecto ajeno', supervisor: $this->engineer());

        $ownMr = $this->materialRequest($own, 7001);
        $foreignMr = $this->materialRequest($foreign, 7002);
        $ownPr = $this->purchaseRequest($own, 8001);
        $foreignPr = $this->purchaseRequest($foreign, 8002);
        $ownPo = $this->purchaseOrder($own, 9001);
        $foreignPo = $this->purchaseOrder($foreign, 9002);

        $this->actingAs($engineer);

        $this->get(route('material_requests.show', $ownMr))->assertOk();
        $this->get(route('material_requests.show', $foreignMr))->assertForbidden();
        $this->get(route('material_requests.pdf', $foreignMr))->assertForbidden();
        $this->get(route('purchase_requests.show', $ownPr))->assertOk();
        $this->get(route('purchase_requests.show', $foreignPr))->assertForbidden();
        $this->get(route('purchase_requests.pdf', $foreignPr))->assertForbidden();
        $this->get(route('purchase_orders.show', $ownPo))->assertOk();
        $this->get(route('purchase_orders.show', $foreignPo))->assertForbidden();
        $this->get(route('purchase_orders.pdf', $ownPo))->assertForbidden();
    }

    public function test_purchase_order_detail_hides_payment_sections_for_engineers(): void
    {
        $engineer = $this->engineer();
        $project = $this->project('Proyecto propio', supervisor: $engineer);
        $purchaseOrder = $this->purchaseOrder($project, 9001);

        $this->actingAs($engineer)
            ->get(route('purchase_orders.show', $purchaseOrder))
            ->assertOk()
            ->assertDontSee('hitos-container', false)
            ->assertDontSee('Facturas')
            ->assertDontSee('Evidencias')
            ->assertDontSee('Cubierto')
            ->assertDontSee('Generar PDF')
            ->assertDontSee('Editar OC');

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('purchase_orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('hitos-container', false);
    }

    public function test_engineer_is_read_only_on_solmat_solcom_and_purchase_orders(): void
    {
        $engineer = $this->engineer();
        $project = $this->project('Proyecto propio', supervisor: $engineer);
        $materialRequest = $this->materialRequest($project, 7001);
        $purchaseRequest = $this->purchaseRequest($project, 8001);
        $purchaseOrder = $this->purchaseOrder($project, 9001);

        $this->actingAs($engineer);

        $this->post(route('material_requests.store'), [])->assertForbidden();
        $this->put(route('material_requests.update', $materialRequest), [])->assertForbidden();
        $this->delete(route('material_requests.destroy', $materialRequest))->assertForbidden();
        $this->post(route('purchase_requests.store'), [])->assertForbidden();
        $this->put(route('purchase_requests.update', $purchaseRequest), [])->assertForbidden();
        $this->delete(route('purchase_requests.destroy', $purchaseRequest))->assertForbidden();
        $this->post(route('purchase_orders.store'), [])->assertForbidden();
        $this->put(route('purchase_orders.update', $purchaseOrder), [])->assertForbidden();
        $this->delete(route('purchase_orders.destroy', $purchaseOrder))->assertForbidden();
        $this->post(route('milestones.store'), [])->assertForbidden();
        $this->post(route('invoices.store'), [])->assertForbidden();
        $this->post(route('evidences.store'), [])->assertForbidden();
    }

    public function test_engineer_creates_and_updates_estimates_only_in_own_projects(): void
    {
        $engineer = $this->engineer();
        $own = $this->project('Proyecto propio', supervisor: $engineer);
        $foreign = $this->project('Proyecto ajeno', supervisor: $this->engineer());

        $this->actingAs($engineer)
            ->get(route('my-projects.estimates.create', $own))
            ->assertOk();

        $this->post(route('my-projects.estimates.store', $own), $this->estimateData())
            ->assertRedirect(route('my-projects.show', [$own, 'tab' => 'estimates']));

        $estimate = ProjectEstimate::firstOrFail();
        $this->assertSame($own->id, $estimate->project_id);
        $this->assertSame($engineer->id, $estimate->created_by);

        $this->put(route('my-projects.estimates.update', $estimate), $this->estimateData(['estimate_amount' => '250.00']))
            ->assertRedirect(route('my-projects.show', [$own, 'tab' => 'estimates']));
        $this->assertSame(250.0, (float) $estimate->fresh()->estimate_amount);

        $this->get(route('my-projects.estimates.create', $foreign))->assertForbidden();
        $this->post(route('my-projects.estimates.store', $foreign), $this->estimateData(['estimate_number' => 'EST-002']))
            ->assertForbidden();

        $foreignEstimate = $foreign->estimates()->create(array_merge(
            $this->estimateData(['estimate_number' => 'EST-900']),
            ['created_by' => $this->userWithRole('admin')->id]
        ));
        $this->put(route('my-projects.estimates.update', $foreignEstimate), $this->estimateData(['estimate_number' => 'EST-900']))
            ->assertForbidden();
    }

    public function test_engineer_cannot_delete_estimates_or_manage_their_files(): void
    {
        Storage::fake('s3');

        $engineer = $this->engineer();
        $project = $this->project('Proyecto propio', supervisor: $engineer);
        $estimate = $project->estimates()->create(array_merge($this->estimateData(), [
            'created_by' => $engineer->id,
            'invoice_pdf_path' => 'estimates/factura.pdf',
        ]));

        $this->actingAs($engineer);

        $this->delete(route('projects.estimates.destroy', $estimate))->assertForbidden();
        $this->put(route('projects.estimates.documents.update', $estimate), [
            'invoice_pdf' => UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->delete(route('projects.estimates.documents.destroy', [$estimate, 'invoice_pdf']))->assertForbidden();
        $this->get(route('projects.estimates.documents.download', [$estimate, 'invoice_pdf']))->assertForbidden();

        $this->assertDatabaseHas('project_estimates', ['id' => $estimate->id]);

        $this->get(route('my-projects.show', $project))
            ->assertOk()
            ->assertDontSee('Gestionar archivos')
            ->assertDontSee('Eliminar estimación');
    }

    public function test_project_lookup_endpoints_are_scoped_for_engineers(): void
    {
        $engineer = $this->engineer();
        $own = $this->project('Proyecto propio', supervisor: $engineer);
        $foreign = $this->project('Proyecto ajeno', supervisor: $this->engineer());

        $this->actingAs($engineer)
            ->getJson(route('projects.search', ['q' => 'Proyecto']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $own->id]);

        $this->getJson(route('projects.works_json', $foreign))->assertForbidden();
        $this->getJson(route('projects.works.search', [$foreign, 'q' => 'Obra']))->assertForbidden();
        $this->getJson(route('projects.works_json', $own))->assertOk();
    }

    public function test_project_managers_keep_full_access(): void
    {
        $project = $this->project('Proyecto cualquiera', supervisor: $this->engineer());
        $manager = $this->userWithRole('Proyectos');

        $this->actingAs($manager)->get(route('projects.index'))->assertOk()->assertSee('Proyecto cualquiera');
        $this->get(route('projects.show', $project))->assertOk();
        $this->get(route('purchase_orders.show', $this->purchaseOrder($project, 9001)))->assertOk();
    }

    private function engineer(): User
    {
        return $this->userWithRole('Engineer');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function project(string $name, ?User $supervisor = null, ?User $resident = null): Project
    {
        $project = Project::create([
            'name' => $name,
            'client_name' => 'Cliente de prueba',
            'currency' => 'MXN',
            'status' => 'active',
        ]);

        ProjectWork::create([
            'project_id' => $project->id,
            'name' => 'Obra de ' . $name,
            'status' => 'active',
            'supervisor_user_id' => $supervisor?->id,
            'resident_user_id' => $resident?->id,
        ]);

        return $project;
    }

    private function materialRequest(Project $project, int $folio): MaterialRequest
    {
        return MaterialRequest::create([
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
    }

    private function purchaseRequest(Project $project, int $folio): PurchaseRequest
    {
        return PurchaseRequest::create([
            'folio' => $folio,
            'project_id' => $project->id,
            'project_work_id' => $project->works()->value('id'),
            'zone' => 'Zona de prueba',
            'delivery_address' => 'Dirección de prueba',
            'short_description' => 'Compra de prueba',
            'request_date' => '2026-08-27',
            'need_date' => '2026-09-01',
            'status' => 'pending',
            'requested_by' => $this->userWithRole('Solcom')->id,
        ]);
    }

    private function purchaseOrder(Project $project, int $folio): PurchaseOrder
    {
        $supplier = Supplier::create(['rfc_name' => 'Proveedor ' . $folio]);

        return PurchaseOrder::create([
            'folio' => $folio,
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => 'autorizada',
            'recurrence_type' => 'unico',
        ]);
    }

    private function estimateData(array $overrides = []): array
    {
        return array_merge([
            'estimate_number' => 'EST-001',
            'estimate_date' => '2026-08-26',
            'type' => 'estimacion',
            'estimate_amount' => '100.00',
            'status' => 'pendiente',
        ], $overrides);
    }
}
