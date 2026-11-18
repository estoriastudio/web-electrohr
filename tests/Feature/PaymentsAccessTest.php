<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 2 (Pagos y Recepción): Facturas, Alta de Facturas, Hitos, Pagos, Recepción de OC y Evidencias
 * piden los permisos de la matriz. El usuario pidió respetar la matriz (2026-11-11): Pagos y Compras
 * solo consultan lo que la matriz no les da.
 */
class PaymentsAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $supervisor;

    private PurchaseOrder $ownOrder;

    private PurchaseOrder $otherOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);

        $this->buyer = $this->userWithRole('Orden de compra');
        $this->supervisor = $this->userWithRole('Supervisor');

        $ownProject = Project::create(['name' => 'Propio', 'client_name' => 'Cliente', 'status' => 'active', 'currency' => 'MXN']);
        ProjectWork::create(['project_id' => $ownProject->id, 'name' => 'Obra', 'status' => 'active', 'supervisor_user_id' => $this->supervisor->id]);
        $otherProject = Project::create(['name' => 'Ajeno', 'client_name' => 'Cliente', 'status' => 'active', 'currency' => 'MXN']);

        $this->ownOrder = $this->purchaseOrder(9001, $ownProject, $this->buyer);
        $this->otherOrder = $this->purchaseOrder(9002, $otherProject, $this->userWithRole('Orden de compra'));
    }

    public function test_buyer_sees_and_validates_only_invoices_of_own_orders(): void
    {
        $own = $this->invoice($this->ownOrder, 'FAC-OWN');
        $foreign = $this->invoice($this->otherOrder, 'FAC-FOREIGN');

        $this->actingAs($this->buyer);
        $this->get(route('invoices.index', ['section' => 'todas']))->assertOk()->assertSee('9001')->assertDontSee('9002');
        $this->get(route('invoices.show', $own))->assertOk()->assertSee('Validación de Compras');
        $this->get(route('invoices.show', $foreign))->assertForbidden();
        $this->patch(route('invoices.status.update', $foreign), ['status' => 'rechazada'])->assertForbidden();
        $this->get(route('payments.alta_facturas'))->assertOk();
    }

    public function test_payments_consults_invoices_but_neither_validates_nor_registers_them(): void
    {
        $invoice = $this->invoice($this->otherOrder, 'FAC-FOREIGN');

        $this->actingAs($this->userWithRole('Pagos'));
        $this->get(route('invoices.index', ['section' => 'todas']))->assertOk()->assertSee('9002');
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Valida el comprador de la OC.');
        $this->patch(route('invoices.status.update', $invoice), ['status' => 'rechazada'])->assertForbidden();
        $this->get(route('payments.alta_facturas'))->assertForbidden();
        $this->post(route('invoices.store'), [])->assertForbidden();
        $this->post(route('milestones.store'), [])->assertForbidden();
        $this->post(route('evidences.store'), [])->assertForbidden();
    }

    public function test_reception_keeps_only_invoice_intake(): void
    {
        $this->actingAs($this->userWithRole('Recepción'));

        $this->get(route('payments.alta_facturas'))->assertOk()->assertSee('Alta de facturas');
        $this->get(route('invoices.index'))->assertForbidden();
        $this->get(route('payments.index'))->assertForbidden();
        $this->get(route('purchase_orders.index'))->assertForbidden();
    }

    public function test_direction_authorizes_payments_and_payments_presents_them(): void
    {
        $this->actingAs($this->userWithRole('Pagos'));
        $this->get(route('payments.index'))->assertOk();
        $this->get(route('payments.payable'))->assertOk();
        $this->post(route('payments.authorize_multiple'), [])->assertForbidden();

        $this->actingAs($this->userWithRole('Dirección'));
        $this->get(route('payments.index'))->assertOk();
        $this->post(route('payments.authorize_multiple'), [])->assertSessionHasErrors('payment_ids');
        $this->post(route('payments.mark_multiple_paid_with_spei'), [])->assertForbidden();

        $this->actingAs($this->buyer);
        $this->get(route('payments.paid'))->assertOk();
        $this->post(route('payments.mark_multiple_paid_with_spei'), [])->assertForbidden();
        $this->post(route('payments.store'), [])->assertForbidden();
    }

    public function test_receipts_are_registered_by_warehouse_and_site_staff_only_in_their_projects(): void
    {
        $this->actingAs($this->buyer)
            ->post(route('purchase_orders.deliveries.store', $this->ownOrder), [])
            ->assertForbidden();

        $this->actingAs($this->supervisor);
        $this->get(route('purchase_orders.delivery_report', ['search' => '900']))
            ->assertOk()
            ->assertSee('9001')
            ->assertDontSee('9002');
        $this->post(route('purchase_orders.deliveries.store', $this->otherOrder), [])->assertForbidden();
        $this->post(route('purchase_orders.deliveries.store', $this->ownOrder), [])->assertSessionHasErrors('item_ids');

        $this->actingAs($this->userWithRole('Almacén'))
            ->post(route('purchase_orders.deliveries.store', $this->otherOrder), [])
            ->assertSessionHasErrors('item_ids');
    }

    public function test_navbar_shows_payment_items_by_permission(): void
    {
        $this->actingAs($this->userWithRole('Pagos'))
            ->get(route('payments.payable'))
            ->assertSee('Autorizaciones')
            ->assertSee('Por Pagar')
            ->assertSee('href="'.route('invoices.index').'"', false)
            ->assertDontSee('href="'.route('payments.alta_facturas').'"', false);

        $this->actingAs($this->userWithRole('Almacén'))
            ->get(route('purchase_orders.delivery_report'))
            ->assertSee('Buscar OCs')
            ->assertDontSee('Por Pagar');
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return User::factory()->create()->assignRole($role);
    }

    private function purchaseOrder(int $folio, Project $project, User $buyer): PurchaseOrder
    {
        return PurchaseOrder::create([
            'folio' => $folio,
            'type' => 'materiales_servicios',
            'supplier_id' => Supplier::create(['rfc_name' => 'Proveedor '.$folio])->id,
            'project_id' => $project->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => 'autorizada',
            'recurrence_type' => 'unico',
            'buyer_id' => $buyer->id,
        ]);
    }

    private function invoice(PurchaseOrder $order, string $folio): PurchaseOrderInvoice
    {
        return PurchaseOrderInvoice::create([
            'purchase_order_id' => $order->id,
            'folio' => $folio,
            'file_name' => 'factura.pdf',
            'file_path' => 'invoices/factura.pdf',
            'amount' => 1000,
            'currency' => 'MXN',
        ]);
    }
}
