<?php

namespace Tests\Feature;

use App\Models\MaterialRequest;
use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateFile;
use App\Models\ProjectWork;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EngineerProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);
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

    public function test_supervisor_only_reads_own_projects_in_the_generic_routes(): void
    {
        $engineer = $this->engineer();
        $project = $this->project('Proyecto propio', supervisor: $engineer);
        $foreign = $this->project('Proyecto ajeno', supervisor: $this->engineer());

        $this->actingAs($engineer)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Proyecto propio')
            ->assertDontSee('Proyecto ajeno')
            ->assertDontSee('Nuevo Proyecto');
        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee('Documentación del Proyecto')
            ->assertDontSee('Nueva Obra');
        $this->get(route('projects.show', $foreign))->assertForbidden();
        $this->get(route('project_works.show', $project->works()->first()))->assertOk();
        $this->get(route('project_works.show', $foreign->works()->first()))->assertForbidden();
        $this->delete(route('projects.destroy', $project))->assertForbidden();
        $this->post(route('projects.store'), [])->assertForbidden();
        $this->post(route('project_works.store'), [])->assertForbidden();
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

    /** Fase 3 (espacio de Obra): ve hitos y evidencias de la OC, pero no pagos ni montos pagados. */
    public function test_purchase_order_detail_hides_payment_sections_for_engineers(): void
    {
        $engineer = $this->engineer();
        $project = $this->project('Proyecto propio', supervisor: $engineer);
        $purchaseOrder = $this->purchaseOrder($project, 9001);

        $this->actingAs($engineer)
            ->get(route('purchase_orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('hitos-container', false)
            ->assertSee('Evidencias')
            ->assertDontSee('Resumen económico')
            ->assertDontSee('Subir factura')
            ->assertDontSee('Cubierto')
            ->assertDontSee('Generar PDF')
            ->assertDontSee('Editar OC');

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('purchase_orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('hitos-container', false);
    }

    public function test_engineer_is_read_only_on_solcom_and_purchase_orders_and_cannot_delete_solmat(): void
    {
        $engineer = $this->engineer();
        $project = $this->project('Proyecto propio', supervisor: $engineer);
        $materialRequest = $this->materialRequest($project, 7001);
        $purchaseRequest = $this->purchaseRequest($project, 8001);
        $purchaseOrder = $this->purchaseOrder($project, 9001);

        $this->actingAs($engineer);

        $this->delete(route('material_requests.destroy', $materialRequest))->assertForbidden();
        $this->patch(route('material_requests.archive', $materialRequest))->assertForbidden();
        $this->get(route('material_requests.index', ['scope' => 'all']))->assertOk()->assertSee('7001');
        $this->post(route('purchase_requests.store'), [])->assertForbidden();
        $this->put(route('purchase_requests.update', $purchaseRequest), [])->assertForbidden();
        $this->delete(route('purchase_requests.destroy', $purchaseRequest))->assertForbidden();
        $this->post(route('purchase_orders.store'), [])->assertForbidden();
        $this->put(route('purchase_orders.update', $purchaseOrder), [])->assertForbidden();
        $this->delete(route('purchase_orders.destroy', $purchaseOrder))->assertForbidden();
        $this->post(route('milestones.store'), [])->assertForbidden();
        $this->post(route('invoices.store'), [])->assertForbidden();
        // La matriz sí le da al Supervisor subir evidencias (V C); aquí solo falla la validación.
        $this->post(route('evidences.store'), [])->assertSessionHasErrors('purchase_order_id');
    }

    public function test_supervisor_only_consults_estimates_of_own_projects(): void
    {
        $engineer = $this->engineer();
        $own = $this->project('Proyecto propio', supervisor: $engineer);
        $foreign = $this->project('Proyecto ajeno', supervisor: $this->engineer());
        $estimate = $own->estimates()->create(array_merge(
            $this->estimateData(['estimate_number' => 'EST-777']),
            ['created_by' => $this->userWithRole('admin')->id]
        ));

        $this->actingAs($engineer)
            ->get(route('my-projects.show', $own))
            ->assertOk()
            ->assertSee('EST-777')
            ->assertDontSee('Registrar estimación')
            ->assertDontSee('Editar estimación');

        $this->get(route('projects.estimates.create', $own))->assertForbidden();
        $this->post(route('projects.estimates.store', $own), $this->estimateData(['estimate_number' => 'EST-002']))
            ->assertForbidden();
        $this->put(route('projects.estimates.update', $estimate), $this->estimateData(['estimate_amount' => '250.00']))
            ->assertForbidden();
        $this->post(route('projects.estimates.store', $foreign), $this->estimateData(['estimate_number' => 'EST-003']))
            ->assertForbidden();

        $this->assertSame(1, ProjectEstimate::count());
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

        $file = $estimate->files()->create([
            'file_type' => array_key_first(ProjectEstimateFile::TYPES),
            'file_path' => 'estimates/soporte.pdf',
            'original_name' => 'soporte.pdf',
        ]);

        $this->delete(route('projects.estimates.destroy', $estimate))->assertForbidden();
        $this->post(route('projects.estimates.files.store', $estimate), [
            'file' => UploadedFile::fake()->create('soporte.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->get(route('projects.estimates.files.download', $file))->assertForbidden();
        $this->delete(route('projects.estimates.files.destroy', $file))->assertForbidden();

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
        // La matriz no le da a Contratos la vista de OC.
        $this->get(route('purchase_orders.show', $this->purchaseOrder($project, 9001)))->assertForbidden();
    }

    private function engineer(): User
    {
        return $this->userWithRole('Engineer');
    }

    /** Fase 5: los roles anteriores se retiraron; cada nombre anterior se traduce a su rol de la matriz. */
    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(match ($role) {
            'Engineer' => ['Supervisor'],
            'Proyectos' => ['Contratos'],
            'Solcom' => ['Almacén'],
            'Solmat' => ['Almacén'],
            default => [$role],
        });

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
            'name' => 'Obra de '.$name,
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
        $supplier = Supplier::create(['rfc_name' => 'Proveedor '.$folio]);

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
