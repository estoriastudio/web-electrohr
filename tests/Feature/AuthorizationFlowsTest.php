<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestChangeNote;
use App\Models\Payment;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestChangeNote;
use App\Models\StockExit;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 4: autorizaciones de Dirección (OC con sus cambios y cancelaciones, ajustes comerciales de SOLCOM,
 * nómina), cambios de SOLMAT desde la obra, validación de hitos, entrega en obra y seguimiento del evento.
 */
class AuthorizationFlowsTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $manager;

    private User $supervisor;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);

        $this->buyer = $this->userWithRole('Orden de compra');
        $this->manager = $this->userWithRole('Gerente de Proyecto');
        $this->supervisor = $this->userWithRole('Supervisor');
        $this->project = Project::create([
            'name' => 'Proyecto', 'client_name' => 'Cliente', 'status' => 'active', 'currency' => 'MXN',
            'manager_user_id' => $this->manager->id,
        ]);
        ProjectWork::create(['project_id' => $this->project->id, 'name' => 'Obra', 'status' => 'active', 'supervisor_user_id' => $this->supervisor->id]);
    }

    public function test_direction_authorizes_purchase_orders_and_records_who(): void
    {
        $order = $this->purchaseOrder(9001, 'emitida');
        $direction = $this->userWithRole('Dirección');

        $this->actingAs($this->buyer)->patch(route('purchase_orders.approve', $order))->assertForbidden();

        $this->actingAs($direction)->patch(route('purchase_orders.approve', $order))->assertRedirect();
        $order->refresh();
        $this->assertSame('autorizada', $order->status);
        $this->assertSame($direction->id, (int) $order->authorized_by);
    }

    public function test_change_of_an_authorized_order_goes_through_direction_and_returns_it_to_issued(): void
    {
        $order = $this->purchaseOrder(9001, 'autorizada');

        $this->actingAs($this->buyer)
            ->post(route('purchase_orders.change_requests.store', $order), ['type' => 'change', 'reason' => 'Cambiar el precio del concepto 2'])
            ->assertRedirect();
        $changeRequest = $order->changeRequests()->firstOrFail();
        $this->assertSame('pending', $changeRequest->status);

        // Compras no resuelve su propia solicitud.
        $this->post(route('purchase_orders.change_requests.approve', $changeRequest))->assertForbidden();

        $this->actingAs($this->userWithRole('Dirección'))
            ->post(route('purchase_orders.change_requests.approve', $changeRequest))
            ->assertRedirect(route('purchase_orders.show', $order));

        $this->assertSame('emitida', $order->fresh()->status);
        $this->assertSame('approved', $changeRequest->fresh()->status);
    }

    public function test_cancellation_of_an_authorized_order_needs_a_reason_to_reject_and_cancels_when_approved(): void
    {
        $order = $this->purchaseOrder(9001, 'autorizada');
        $this->actingAs($this->buyer)
            ->post(route('purchase_orders.change_requests.store', $order), ['type' => 'cancel', 'reason' => 'El proveedor ya no surte el material'])
            ->assertRedirect();
        $changeRequest = $order->changeRequests()->firstOrFail();

        $direction = $this->userWithRole('Dirección');
        $this->actingAs($direction)
            ->post(route('purchase_orders.change_requests.reject', $changeRequest), [])
            ->assertSessionHasErrors('decision_note');

        $this->post(route('purchase_orders.change_requests.approve', $changeRequest))->assertRedirect(route('direction.inbox'));
        $this->assertSoftDeleted('purchase_orders', ['id' => $order->id]);
    }

    public function test_commercial_solcom_adjustment_waits_for_direction(): void
    {
        $purchaseRequest = $this->purchaseRequest('sent_to_purchasing');
        $warehouse = $this->userWithRole('Almacén');

        $this->actingAs($this->buyer)
            ->post(route('purchase_requests.request_changes', $purchaseRequest), ['change_text' => 'Cambiar proveedor sugerido', 'is_commercial' => 1])
            ->assertRedirect();
        $note = PurchaseRequestChangeNote::firstOrFail();
        $this->assertTrue($note->awaitsDirection());

        $this->actingAs($warehouse)
            ->post(route('purchase_requests.change_notes.resolve', [$purchaseRequest, $note]))
            ->assertSessionHas('error');
        $this->assertNull($note->fresh()->resolved_at);
        $this->post(route('purchase_requests.change_notes.decide', [$purchaseRequest, $note]), ['decision' => 'approved'])->assertForbidden();

        $this->actingAs($this->userWithRole('Dirección'))
            ->post(route('purchase_requests.change_notes.decide', [$purchaseRequest, $note]), ['decision' => 'approved'])
            ->assertRedirect();

        $this->actingAs($warehouse)->post(route('purchase_requests.change_notes.resolve', [$purchaseRequest, $note]))->assertRedirect();
        $this->assertNotNull($note->fresh()->resolved_at);
    }

    public function test_payroll_is_paid_only_after_direction_authorizes_it(): void
    {
        $period = PayrollPeriod::create([
            'week_number' => 1, 'year' => 2026, 'start_date' => '2026-01-02', 'end_date' => '2026-01-08',
            'cutoff_date' => '2026-01-08', 'status' => 'closed',
        ]);
        $humanResources = $this->userWithRole('Recursos Humanos');

        $this->actingAs($humanResources)->post(route('human_resources.payroll-periods.mark-paid', $period))->assertRedirect();
        $this->assertSame('closed', $period->fresh()->status);
        $this->post(route('human_resources.payroll-periods.authorize', $period))->assertForbidden();

        $direction = $this->userWithRole('Dirección');
        $this->actingAs($direction)->post(route('human_resources.payroll-periods.authorize', $period))->assertRedirect();
        $this->assertSame('authorized', $period->fresh()->status);
        $this->assertSame($direction->id, (int) $period->fresh()->authorized_by);

        $this->actingAs($humanResources)->post(route('human_resources.payroll-periods.mark-paid', $period))->assertRedirect();
        $this->assertSame('paid', $period->fresh()->status);
    }

    public function test_site_change_of_a_solmat_is_authorized_by_the_project_manager(): void
    {
        $materialRequest = $this->materialRequest('sent_to_warehouse');

        $this->actingAs($this->supervisor)
            ->post(route('material_requests.site_changes.store', $materialRequest), ['change_text' => 'Cambiar la cantidad de cable a 200 m'])
            ->assertRedirect();
        $note = MaterialRequestChangeNote::where('note_type', 'site_change')->firstOrFail();
        $this->assertTrue($note->awaitsManager());

        $warehouse = $this->userWithRole('Almacén');
        $this->actingAs($warehouse)->post(route('material_requests.change_notes.resolve', [$materialRequest, $note]))->assertSessionHas('error');

        $this->actingAs($this->manager)
            ->post(route('material_requests.site_changes.decide', [$materialRequest, $note]), ['decision' => 'approved'])
            ->assertRedirect();

        $this->actingAs($warehouse)->post(route('material_requests.change_notes.resolve', [$materialRequest, $note]))->assertRedirect();
        $this->assertNotNull($note->fresh()->resolved_at);
        $this->assertSame('sent_to_warehouse', $materialRequest->fresh()->status);
    }

    public function test_milestone_validation_is_for_site_staff_and_warehouse_not_purchasing(): void
    {
        $order = $this->purchaseOrder(9001, 'autorizada');
        $milestone = $this->milestone($order);

        $this->actingAs($this->buyer)
            ->post(route('purchase_orders.milestones.confirm_delivery', [$order, $milestone]))
            ->assertForbidden();

        // El Supervisor sí valida; aquí falla solo porque no hay entregas ligadas al hito.
        $this->actingAs($this->supervisor)
            ->post(route('purchase_orders.milestones.confirm_delivery', [$order, $milestone]))
            ->assertSessionHas('error');
    }

    public function test_site_staff_confirm_what_the_warehouse_sent(): void
    {
        $exit = StockExit::forceCreate([
            'concept_id' => Concept::forceCreate(['code' => 'MAT-1', 'description' => 'Cable', 'unit' => 'M'])->id,
            'exit_type' => 'definitive', 'voucher_number' => 'V-1', 'recipient_name' => 'Pedro', 'quantity' => 10,
            'exited_at' => '2026-09-02', 'status' => 'completed', 'created_by' => $this->buyer->id,
            'project_id' => $this->project->id, 'project_work_id' => $this->project->works()->value('id'),
        ]);

        $this->actingAs($this->userWithRole('Supervisor'))
            ->post(route('site_deliveries.store', $exit), ['received' => [10]])
            ->assertForbidden();

        $this->actingAs($this->supervisor);
        $this->get(route('site_deliveries.index'))->assertOk()->assertSee('Salida #'.$exit->id);
        $this->post(route('site_deliveries.store', $exit), ['received' => [8]])->assertSessionHasErrors('notes');
        $this->post(route('site_deliveries.store', $exit), ['received' => [8], 'notes' => 'Llegaron 8 metros'])->assertRedirect();

        $this->assertSame('discrepancy', $exit->fresh()->siteDeliveryConfirmation->status);
    }

    public function test_paid_events_stay_open_until_delivery_and_invoice(): void
    {
        $order = $this->purchaseOrder(9001, 'autorizada');
        $milestone = $this->milestone($order);
        Payment::create(['milestone_id' => $milestone->id, 'folio' => 'PAY-1', 'amount' => 1000, 'payment_date' => '2026-09-15', 'status' => 'pagado']);

        $this->actingAs($this->buyer);
        $this->get(route('event_followups.index'))->assertOk()->assertSee('OC #9001');
        $this->patch(route('event_followups.update', $milestone), ['followup_note' => 'El proveedor envía la factura el lunes'])->assertRedirect();
        $this->assertSame('El proveedor envía la factura el lunes', $milestone->fresh()->followup_note);

        $this->actingAs($this->userWithRole('Pagos'));
        $this->get(route('event_followups.index'))->assertOk()->assertSee('OC #9001');
        $this->patch(route('event_followups.update', $milestone), ['followup_note' => 'x'])->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return User::factory()->create()->assignRole($role);
    }

    private function purchaseOrder(int $folio, string $status): PurchaseOrder
    {
        return PurchaseOrder::create([
            'folio' => $folio, 'type' => 'materiales_servicios',
            'supplier_id' => Supplier::create(['rfc_name' => 'Proveedor '.$folio])->id,
            'project_id' => $this->project->id, 'currency' => 'MXN', 'amount' => 1000,
            'status' => $status, 'recurrence_type' => 'unico', 'buyer_id' => $this->buyer->id,
        ]);
    }

    private function milestone(PurchaseOrder $order): PurchaseOrderMilestone
    {
        return PurchaseOrderMilestone::create([
            'purchase_order_id' => $order->id, 'type' => 'regular', 'concept' => 'Entrega',
            'payment_condition' => 'credito', 'value_type' => 'fijo', 'value' => 1000,
            'covered_amount' => 0, 'due_date' => '2026-09-15',
        ]);
    }

    private function purchaseRequest(string $status): PurchaseRequest
    {
        return PurchaseRequest::create([
            'folio' => 8001, 'project_id' => $this->project->id, 'project_work_id' => $this->project->works()->value('id'),
            'zone' => 'Zona', 'delivery_address' => 'Dirección', 'short_description' => 'Compra',
            'request_date' => '2026-08-27', 'need_date' => '2026-09-01', 'status' => $status,
            'assigned_to' => $this->buyer->id, 'requested_by' => $this->buyer->id,
        ]);
    }

    private function materialRequest(string $status): MaterialRequest
    {
        $materialRequest = MaterialRequest::create([
            'folio' => 7001, 'project_id' => $this->project->id, 'zone' => 'Zona', 'delivery_address' => 'Dirección',
            'location_type' => 'sitio', 'request_date' => '2026-08-27', 'need_date' => '2026-09-01',
            'supply_category' => 'Materiales', 'status' => $status, 'requested_by' => $this->supervisor->id,
        ]);
        $materialRequest->projectWorks()->sync([$this->project->works()->value('id')]);

        return $materialRequest;
    }
}
