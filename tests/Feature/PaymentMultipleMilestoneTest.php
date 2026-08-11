<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMultipleMilestoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_payments_user_can_split_the_initial_payment_into_two_payments(): void
    {
        $user = $this->paymentsUser();
        $milestone = $this->milestoneWithInitialPayment();

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'existing_payment_amount' => 600,
                'amount' => 400,
                'payment_date' => '2026-08-30',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder));

        $this->assertDatabaseHas('payments', [
            'id' => $milestone->payments()->first()->id,
            'amount' => 600,
            'status' => 'por_autorizar',
        ]);
        $this->assertDatabaseHas('payments', [
            'milestone_id' => $milestone->id,
            'amount' => 400,
            'payment_date' => '2026-08-30',
            'status' => 'por_autorizar',
        ]);
        $this->assertSame(2, $milestone->payments()->count());
    }

    public function test_initial_payment_split_can_leave_available_balance_for_later_payments(): void
    {
        $milestone = $this->milestoneWithInitialPayment();

        $this->actingAs($this->paymentsUser())
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'existing_payment_amount' => 400,
                'amount' => 300,
                'payment_date' => '2026-08-30',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder));

        $this->assertSame(2, $milestone->payments()->count());
        $this->assertDatabaseHas('payments', [
            'milestone_id' => $milestone->id,
            'amount' => 400,
        ]);

        $this->actingAs($this->paymentsUser())
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'amount' => 300,
                'payment_date' => '2026-09-15',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder));

        $this->assertSame(3, $milestone->payments()->count());
        $this->assertDatabaseHas('payments', [
            'milestone_id' => $milestone->id,
            'amount' => 300,
            'payment_date' => '2026-09-15',
        ]);
    }

    public function test_initial_payment_split_cannot_exceed_the_milestone_total(): void
    {
        $milestone = $this->milestoneWithInitialPayment();

        $this->actingAs($this->paymentsUser())
            ->from(route('purchase_orders.show', $milestone->purchaseOrder))
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'existing_payment_amount' => 700,
                'amount' => 400,
                'payment_date' => '2026-08-30',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder))
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, $milestone->payments()->count());
        $this->assertDatabaseHas('payments', [
            'milestone_id' => $milestone->id,
            'amount' => 1000,
        ]);
    }

    public function test_rejected_payments_do_not_consume_available_milestone_balance(): void
    {
        $milestone = $this->milestoneWithInitialPayment();
        $milestone->payments()->first()->update(['status' => 'rechazado']);

        $this->actingAs($this->paymentsUser())
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'amount' => 1000,
                'payment_date' => '2026-08-30',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder));

        $this->assertSame(2, $milestone->payments()->count());
        $this->assertDatabaseHas('payments', [
            'milestone_id' => $milestone->id,
            'amount' => 1000,
            'status' => 'por_autorizar',
        ]);
    }

    public function test_cannot_add_payment_when_the_initial_payment_is_already_authorized(): void
    {
        $milestone = $this->milestoneWithInitialPayment();
        $milestone->payments()->first()->update(['status' => 'autorizado']);

        $this->actingAs($this->paymentsUser())
            ->from(route('purchase_orders.show', $milestone->purchaseOrder))
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'amount' => 100,
                'payment_date' => '2026-08-30',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder))
            ->assertSessionHasErrors('milestone_id');

        $this->assertSame(1, $milestone->payments()->count());
    }

    private function paymentsUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Pagos');

        return $user;
    }

    private function milestoneWithInitialPayment(): PurchaseOrderMilestone
    {
        $supplier = Supplier::create(['rfc_name' => 'Proveedor de prueba']);
        $purchaseOrder = PurchaseOrder::create([
            'folio' => random_int(25000, 99999),
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => 'autorizada',
            'recurrence_type' => 'unico',
        ]);
        $milestone = PurchaseOrderMilestone::create([
            'purchase_order_id' => $purchaseOrder->id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 1000,
            'covered_amount' => 0,
            'due_date' => '2026-08-30',
        ]);

        Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-' . random_int(10000, 99999),
            'amount' => 1000,
            'payment_date' => '2026-08-30',
            'status' => 'por_autorizar',
        ]);

        return $milestone->fresh('purchaseOrder');
    }
}