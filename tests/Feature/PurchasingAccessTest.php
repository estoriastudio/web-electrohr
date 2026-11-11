<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 2 (Compras): SOLCOM, Pila SOLCOM, Carga de Trabajo y OC piden los permisos de la matriz.
 * Decisiones 2026-11-10: cada comprador ve y opera solo sus OC; Almacén edita la SOLCOM hasta
 * enviarla a Compras; el PDF de la OC es solo para Compras (sus OC) y admin.
 */
class PurchasingAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $otherBuyer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);

        $this->buyer = $this->userWithRole('Orden de compra');
        $this->otherBuyer = $this->userWithRole('Orden de compra');
        $this->project = Project::create(['name' => 'Proyecto', 'client_name' => 'Cliente', 'status' => 'active', 'currency' => 'MXN']);
        ProjectWork::create(['project_id' => $this->project->id, 'name' => 'Obra', 'status' => 'active']);
    }

    public function test_buyer_sees_and_operates_only_own_purchase_orders(): void
    {
        $own = $this->purchaseOrder(9001, $this->buyer);
        $foreign = $this->purchaseOrder(9002, $this->otherBuyer);

        $this->actingAs($this->buyer);
        $this->get(route('purchase_orders.index', ['scope' => 'all']))
            ->assertOk()
            ->assertSee('9001')
            ->assertDontSee('9002')
            ->assertDontSee('Listado completo');
        $this->get(route('purchase_orders.show', $own))->assertOk();
        $this->get(route('purchase_orders.show', $foreign))->assertForbidden();
        $this->get(route('purchase_orders.edit', $foreign))->assertForbidden();
        $this->patch(route('purchase_orders.archive', $foreign))->assertForbidden();
        $this->get(route('purchase_orders.pdf', $own))->assertOk();
        $this->get(route('purchase_orders.pdf', $foreign))->assertForbidden();

        $this->patch(route('purchase_orders.archive', $own))->assertRedirect();
        $this->assertNotNull($own->fresh()->archived_at);
    }

    public function test_view_only_roles_see_all_orders_without_pdf_or_edit(): void
    {
        $order = $this->purchaseOrder(9001, $this->buyer);

        foreach (['Pagos', 'Almacén', 'Dirección'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(route('purchase_orders.index'))->assertOk()->assertSee('9001')->assertDontSee('Nueva orden de compra');
            $this->get(route('purchase_orders.show', $order))->assertOk();
            $this->get(route('purchase_orders.pdf', $order))->assertForbidden();
            $this->get(route('purchase_orders.edit', $order))->assertForbidden();
            $this->post(route('purchase_orders.store'), [])->assertForbidden();
        }
    }

    public function test_warehouse_edits_solcom_only_until_sent_to_purchasing(): void
    {
        $warehouse = $this->userWithRole('Almacén');
        $pending = $this->purchaseRequest(8001, 'pending');
        $sent = $this->purchaseRequest(8002, 'sent_to_purchasing', $this->buyer);

        $this->actingAs($warehouse);
        $this->get(route('purchase_requests.show', $pending))->assertOk()->assertSee('Enviar a Compras')->assertSee('Agregar concepto');
        $this->get(route('purchase_requests.edit', $pending))->assertOk();
        $this->get(route('purchase_requests.show', $sent))->assertOk()->assertDontSee('Agregar concepto');
        $this->get(route('purchase_requests.edit', $sent))->assertForbidden();
        $this->post(route('purchase_requests.notes.store', $sent), ['text' => 'Nota'])->assertForbidden();

        $this->post(route('purchase_requests.send_to_purchasing', $pending), ['assigned_to' => $this->buyer->id])->assertRedirect();
        $this->assertSame('sent_to_purchasing', $pending->fresh()->status);
        $this->get(route('purchase_requests.edit', $pending))->assertForbidden();
    }

    public function test_buyer_edits_sent_solcom_but_does_not_create_or_assign_it(): void
    {
        $sent = $this->purchaseRequest(8002, 'sent_to_purchasing', $this->buyer);

        $this->actingAs($this->buyer);
        $this->get(route('purchase_requests.edit', $sent))->assertOk();
        $this->post(route('purchase_requests.request_changes', $sent), ['change_text' => 'Ajustar'])->assertRedirect();
        $this->post(route('purchase_requests.store'), [])->assertForbidden();
        $this->post(route('purchase_requests.reassign_purchasing', $sent), ['assigned_to' => $this->otherBuyer->id])->assertForbidden();
        $this->get(route('purchasing.solcom_pile'))->assertOk();
    }

    public function test_workload_is_own_for_buyers_and_full_for_direction(): void
    {
        $this->actingAs($this->buyer)
            ->get(route('purchasing.workload'))
            ->assertOk()
            ->assertSee($this->buyer->name)
            ->assertDontSee($this->otherBuyer->name);

        $this->actingAs($this->userWithRole('Dirección'))
            ->get(route('purchasing.workload'))
            ->assertOk()
            ->assertSee($this->buyer->name)
            ->assertSee($this->otherBuyer->name);

        $this->actingAs($this->userWithRole('Almacén'))->get(route('purchasing.workload'))->assertForbidden();
    }

    public function test_navbar_shows_purchasing_items_by_permission(): void
    {
        $this->actingAs($this->buyer)
            ->get(route('purchasing.solcom_pile'))
            ->assertSee('href="'.route('purchase_orders.index').'"', false)
            ->assertSee('Carga de Trabajo')
            ->assertSee('href="'.route('purchase_requests.index').'"', false);

        $this->actingAs($this->userWithRole('Pagos'))
            ->get(route('purchase_orders.index'))
            ->assertSee('href="'.route('purchase_orders.index').'"', false)
            ->assertDontSee('Pila SOLCOM')
            ->assertDontSee('href="'.route('purchase_requests.index').'"', false);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return User::factory()->create()->assignRole($role);
    }

    private function purchaseOrder(int $folio, User $buyer): PurchaseOrder
    {
        return PurchaseOrder::create([
            'folio' => $folio,
            'type' => 'materiales_servicios',
            'supplier_id' => Supplier::create(['rfc_name' => 'Proveedor '.$folio])->id,
            'project_id' => $this->project->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => 'emitida',
            'recurrence_type' => 'unico',
            'buyer_id' => $buyer->id,
        ]);
    }

    private function purchaseRequest(int $folio, string $status, ?User $assignedTo = null): PurchaseRequest
    {
        return PurchaseRequest::create([
            'folio' => $folio,
            'project_id' => $this->project->id,
            'project_work_id' => $this->project->works()->value('id'),
            'zone' => 'Zona de prueba',
            'delivery_address' => 'Dirección de prueba',
            'short_description' => 'Compra de prueba',
            'request_date' => '2026-08-27',
            'need_date' => '2026-09-01',
            'status' => $status,
            'assigned_to' => $assignedTo?->id,
            'requested_by' => $this->buyer->id,
        ]);
    }
}
