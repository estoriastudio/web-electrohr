<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\MaterialRequest;
use App\Models\MaterialVoucher;
use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 2 (Almacén): SOLMAT, Pila SOLMAT, Inventario y Vales piden los permisos de la matriz.
 * Decisiones 2026-11-10: Gerente, Supervisor y Almacén crean SOLMAT; solo el gerente del proyecto
 * (y admin) la envía a Almacén; corregir inventario es solo de admin.
 */
class WarehouseAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $supervisor;

    private Project $ownProject;

    private Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);

        $this->manager = $this->userWithRole('Gerente de Proyecto');
        $this->supervisor = $this->userWithRole('Supervisor');
        $this->ownProject = $this->project('Proyecto propio', $this->manager, $this->supervisor);
        $this->otherProject = $this->project('Proyecto ajeno');
    }

    public function test_supervisor_creates_solmat_only_in_own_projects_and_cannot_send_it(): void
    {
        $this->actingAs($this->supervisor);

        $this->post(route('material_requests.store'), $this->solmatData($this->ownProject))->assertRedirect();
        $this->post(route('material_requests.store'), $this->solmatData($this->otherProject))->assertForbidden();

        $own = MaterialRequest::firstOrFail();
        $this->assertSame($this->ownProject->id, $own->project_id);
        $foreign = $this->materialRequest($this->otherProject, 7002);

        $this->get(route('material_requests.index', ['scope' => 'all']))
            ->assertOk()
            ->assertSee((string) $own->folio)
            ->assertDontSee('7002');
        $this->get(route('material_requests.show', $foreign))->assertForbidden();
        $this->post(route('material_requests.send_to_warehouse', $own))->assertForbidden();
        $this->patch(route('material_requests.archive', $own))->assertForbidden();

        $own->update(['status' => 'sent_to_warehouse']);
        $this->get(route('material_requests.edit', $own))->assertForbidden();
    }

    public function test_only_the_project_manager_sends_solmat_to_warehouse(): void
    {
        $own = $this->materialRequest($this->ownProject, 7001);
        $other = $this->materialRequest($this->otherProject, 7002);

        $this->actingAs($this->userWithRole('Almacén'))
            ->post(route('material_requests.send_to_warehouse', $own))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->post(route('material_requests.send_to_warehouse', $other))
            ->assertForbidden();

        $this->post(route('material_requests.send_to_warehouse', $own))
            ->assertRedirect(route('material_requests.show', $own));
        $this->assertSame('sent_to_warehouse', $own->fresh()->status);
    }

    public function test_warehouse_creates_edits_and_archives_any_solmat(): void
    {
        $warehouse = $this->userWithRole('Almacén');
        $sent = $this->materialRequest($this->otherProject, 7002, 'sent_to_warehouse');

        $this->actingAs($warehouse)
            ->get(route('material_requests.index', ['scope' => 'all']))
            ->assertOk()
            ->assertSee('7002')
            ->assertSee('Nueva SOLMAT');

        $this->post(route('material_requests.store'), $this->solmatData($this->otherProject))->assertRedirect();
        $this->get(route('material_requests.edit', $sent))->assertOk();
        $this->post(route('material_requests.request_changes', $sent), ['change_text' => 'Ajustar cantidades'])->assertRedirect();
        $this->patch(route('material_requests.archive', $sent))->assertRedirect();
        $this->assertNotNull($sent->fresh()->archived_at);

        $this->get(route('warehouse.solmat_pile'))->assertOk();
    }

    public function test_purchasing_and_direction_only_consult_solmat(): void
    {
        $solmat = $this->materialRequest($this->otherProject, 7002, 'sent_to_warehouse');

        $this->actingAs($this->userWithRole('Orden de compra'));
        $this->get(route('material_requests.show', $solmat))->assertOk();
        $this->get(route('warehouse.solmat_pile'))->assertOk()->assertDontSee('Crear SOLCOM consolidada');
        $this->get(route('purchase_requests.create_from_solmat', $solmat))->assertForbidden();
        $this->post(route('material_requests.store'), $this->solmatData($this->otherProject))->assertForbidden();
        $this->get(route('material_request_changes.index'))->assertForbidden();

        $this->actingAs($this->userWithRole('Dirección'));
        $this->get(route('material_requests.index', ['scope' => 'all']))->assertOk()->assertDontSee('Nueva SOLMAT');
        $this->post(route('material_requests.request_changes', $solmat), ['change_text' => 'x'])->assertForbidden();
    }

    public function test_inventory_corrections_are_admin_only(): void
    {
        $this->actingAs($this->userWithRole('Almacén'));
        $this->get(route('stocks.index'))->assertOk()->assertSee('Entradas');
        $this->get(route('stocks.entries.index'))->assertOk()->assertSee('Nueva entrada');
        $this->get(route('stocks.exits.index'))->assertOk()->assertSee('Nueva salida');
        $concept = Concept::forceCreate(['code' => 'MAT-1', 'description' => 'Cable', 'unit' => 'M']);
        $entry = StockEntry::forceCreate(['entry_type' => 'purchase', 'quantity' => 5, 'received_at' => '2026-09-01', 'created_by' => $this->manager->id]);
        $exit = StockExit::forceCreate(['exit_type' => 'definitive', 'voucher_number' => 'V-1', 'recipient_name' => 'Pedro', 'quantity' => 1, 'exited_at' => '2026-09-02', 'status' => 'completed', 'created_by' => $this->manager->id]);
        $this->put(route('stocks.entries.update', $entry), [])->assertForbidden();
        $this->delete(route('stocks.entries.destroy', $entry))->assertForbidden();
        $this->delete(route('stocks.exits.destroy', $exit))->assertForbidden();
        $this->post(route('stocks.adjust', $concept), [])->assertForbidden();

        // V-08: el Gerente consulta existencias; el Supervisor no.
        $this->actingAs($this->manager);
        $this->get(route('stocks.index'))->assertOk()->assertDontSee('Nueva entrada');
        $this->get(route('stocks.entries.index'))->assertForbidden();

        $this->actingAs($this->supervisor);
        $this->get(route('stocks.index'))->assertForbidden();
    }

    public function test_only_warehouse_authorizer_authorizes_material_vouchers(): void
    {
        $voucher = $this->voucher();
        $signature = ['signature_name' => 'Encargado', 'signature_data' => 'data:image/png;base64,iVBORw0KGgo='];

        $this->actingAs($this->userWithRole('Almacén'));
        $this->get(route('material_vouchers.show', $voucher))->assertOk()->assertDontSee('Autorizar vale');
        $this->post(route('material_vouchers.authorize', $voucher), $signature)->assertForbidden();
        $this->patch(route('material_vouchers.status.update', $voucher), ['status' => 'autorizado'])->assertForbidden();

        $this->actingAs($this->userWithRole('Almacén Autoriza'));
        $this->get(route('material_vouchers.show', $voucher))->assertOk()->assertSee('Autorizar vale');
        $this->post(route('material_vouchers.authorize', $voucher), $signature)->assertRedirect();
        $this->assertSame('autorizado', $voucher->fresh()->status);

        $this->actingAs($this->userWithRole('Dirección'));
        $this->get(route('material_vouchers.index'))->assertOk();
        $this->get(route('material_vouchers.pdf', $voucher))->assertForbidden();
        $this->post(route('material_vouchers.store'), [])->assertForbidden();

        $this->actingAs($this->userWithRole('Pagos'));
        $this->get(route('material_vouchers.index'))->assertForbidden();
    }

    public function test_navbar_shows_warehouse_items_by_permission(): void
    {
        $this->actingAs($this->userWithRole('Almacén'))
            ->get(route('stocks.index'))
            ->assertSee('href="'.route('material_requests.index').'"', false)
            ->assertSee('Pila SOLMAT')
            ->assertSee('Cambios SOLMAT')
            ->assertSee('href="'.route('stocks.entries.index').'"', false);

        $this->actingAs($this->supervisor)
            ->get(route('my-projects.index'))
            ->assertDontSee('href="'.route('material_requests.index').'"', false)
            ->assertDontSee('Pila SOLMAT')
            ->assertDontSee('href="'.route('stocks.index').'"', false);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return User::factory()->create()->assignRole($role);
    }

    private function project(string $name, ?User $manager = null, ?User $supervisor = null): Project
    {
        $project = Project::create([
            'name' => $name,
            'client_name' => 'Cliente',
            'status' => 'active',
            'currency' => 'MXN',
            'manager_user_id' => $manager?->id,
        ]);
        ProjectWork::create([
            'project_id' => $project->id,
            'name' => "Obra de {$name}",
            'status' => 'active',
            'supervisor_user_id' => $supervisor?->id,
        ]);

        return $project;
    }

    /** @return array<string, mixed> */
    private function solmatData(Project $project): array
    {
        return [
            'project_id' => $project->id,
            'project_work_ids' => [$project->works()->value('id')],
            'zone' => 'Zona de prueba',
            'delivery_address' => 'Dirección de prueba',
            'location_type' => 'sitio',
            'request_date' => '2026-08-27',
            'need_date' => '2026-09-01',
            'supply_category' => 'Materiales',
        ];
    }

    private function materialRequest(Project $project, int $folio, string $status = 'pending'): MaterialRequest
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
            'status' => $status,
            'requested_by' => $this->supervisor->id,
        ]);
        $materialRequest->projectWorks()->sync([$project->works()->value('id')]);

        return $materialRequest;
    }

    private function voucher(): MaterialVoucher
    {
        return MaterialVoucher::forceCreate([
            'folio' => 'VM-0001',
            'folio_prefix' => 'VM',
            'folio_number' => 1,
            'supplier_id' => Supplier::create(['rfc_name' => 'Proveedor de vales'])->id,
            'project_id' => $this->otherProject->id,
            'voucher_date' => today()->toDateString(),
            'requester' => 'Solicitante',
            'status' => 'emitido',
            'created_by' => $this->manager->id,
        ]);
    }
}
