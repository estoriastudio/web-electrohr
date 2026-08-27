<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_purchase_order_role_can_emit_a_pending_purchase_order(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Orden de compra');
        $purchaseOrder = $this->purchaseOrder('pendiente');

        $this->actingAs($user)
            ->patch(route('purchase_orders.emit', $purchaseOrder))
            ->assertRedirect(route('purchase_orders.show', $purchaseOrder));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'emitida',
        ]);
    }

    public function test_user_without_purchase_order_role_cannot_emit_a_purchase_order(): void
    {
        $user = User::factory()->create();
        $purchaseOrder = $this->purchaseOrder('pendiente');

        $this->actingAs($user)
            ->patch(route('purchase_orders.emit', $purchaseOrder))
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'pendiente',
        ]);
    }

    public function test_pending_purchase_order_cannot_be_authorized(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $purchaseOrder = $this->purchaseOrder('pendiente');

        $this->actingAs($admin)
            ->patch(route('purchase_orders.approve', $purchaseOrder))
            ->assertRedirect(route('purchase_orders.show', $purchaseOrder))
            ->assertSessionHas('error', 'La orden de compra debe estar emitida antes de autorizarse.');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'pendiente',
        ]);
    }

    public function test_admin_can_authorize_an_emitted_purchase_order(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $purchaseOrder = $this->purchaseOrder('emitida');

        $this->actingAs($admin)
            ->patch(route('purchase_orders.approve', $purchaseOrder))
            ->assertRedirect(route('purchase_orders.show', $purchaseOrder));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'autorizada',
        ]);
    }

    public function test_admin_can_authorize_an_emitted_purchase_order_with_selected_payments(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $purchaseOrder = $this->purchaseOrder('emitida');
        $milestone = PurchaseOrderMilestone::create([
            'purchase_order_id' => $purchaseOrder->id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 1000,
            'covered_amount' => 0,
        ]);
        $payment = Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-PENDING-' . random_int(10000, 99999),
            'amount' => 1000,
            'payment_date' => '2026-09-01',
            'status' => 'por_autorizar',
        ]);

        $this->actingAs($admin)
            ->patch(route('purchase_orders.approve_with_payments', $purchaseOrder), [
                'payment_ids' => [$payment->id],
            ])
            ->assertRedirect(route('purchase_orders.show', $purchaseOrder));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'autorizada',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'autorizado',
        ]);
    }

    public function test_admin_can_authorize_an_emitted_purchase_order_without_selecting_payments(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $purchaseOrder = $this->purchaseOrder('emitida');

        $this->actingAs($admin)
            ->patch(route('purchase_orders.approve_with_payments', $purchaseOrder))
            ->assertRedirect(route('purchase_orders.show', $purchaseOrder));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'autorizada',
        ]);
    }

    public function test_purchase_order_role_cannot_add_milestone_to_authorized_non_destajo_order(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Orden de compra');
        $purchaseOrder = $this->purchaseOrder('autorizada');

        $this->actingAs($user)
            ->post(route('milestones.store'), $this->milestoneData($purchaseOrder))
            ->assertRedirect(route('purchase_orders.show', $purchaseOrder))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('purchase_order_milestones', [
            'purchase_order_id' => $purchaseOrder->id,
        ]);
    }

    public function test_purchase_order_role_can_add_milestone_to_authorized_destajo_order(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Orden de compra');
        $purchaseOrder = $this->purchaseOrder('autorizada', true);

        $this->actingAs($user)
            ->post(route('milestones.store'), $this->milestoneData($purchaseOrder))
            ->assertRedirect(route('purchase_orders.show', $purchaseOrder));

        $this->assertDatabaseHas('purchase_order_milestones', [
            'purchase_order_id' => $purchaseOrder->id,
            'value_type' => 'porcentaje',
            'value' => 10,
        ]);
        $this->assertDatabaseHas('payments', [
            'status' => 'por_autorizar',
            'amount' => 100,
        ]);
    }

    public function test_purchase_order_role_can_update_pending_payments_of_an_authorized_destajo_milestone(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Orden de compra');
        $purchaseOrder = $this->purchaseOrder('autorizada', true);
        $milestone = PurchaseOrderMilestone::create([
            'purchase_order_id' => $purchaseOrder->id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 1000,
            'covered_amount' => 0,
            'due_date' => '2026-09-01',
        ]);
        $authorizedPayment = Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-AUTH-' . random_int(10000, 99999),
            'amount' => 300,
            'payment_date' => '2026-09-01',
            'status' => 'autorizado',
        ]);
        $pendingPayment = Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-PENDING-' . random_int(10000, 99999),
            'amount' => 700,
            'payment_date' => '2026-09-01',
            'status' => 'por_autorizar',
        ]);

        $this->actingAs($user)
            ->put(route('milestones.update', $milestone), [
                'payment_condition' => 'credito',
                'value_type' => 'fijo',
                'value' => 1200,
                'due_date' => '2026-09-15',
            ])
            ->assertRedirect(route('purchase_orders.show', $purchaseOrder));

        $this->assertDatabaseHas('payments', [
            'id' => $authorizedPayment->id,
            'amount' => 300,
            'payment_date' => '2026-09-01',
            'status' => 'autorizado',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $pendingPayment->id,
            'amount' => 900,
            'payment_date' => '2026-09-15',
            'status' => 'por_autorizar',
        ]);
    }

    private function purchaseOrder(string $status, bool $isDestajo = false): PurchaseOrder
    {
        $supplier = Supplier::create([
            'rfc_name' => 'Proveedor de prueba',
        ]);

        return PurchaseOrder::create([
            'folio' => random_int(25000, 99999),
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => $status,
            'is_destajo' => $isDestajo,
            'recurrence_type' => 'unico',
        ]);
    }

    private function milestoneData(PurchaseOrder $purchaseOrder): array
    {
        return [
            'purchase_order_id' => $purchaseOrder->id,
            'payment_condition' => 'credito',
            'value_type' => 'porcentaje',
            'value' => 10,
            'due_date' => '2026-09-01',
        ];
    }
}