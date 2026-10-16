<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderClosureEvaluator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_order_with_pending_requirements_is_not_ready_to_close(): void
    {
        $order = $this->readyOrder();
        $order->milestones()->first()->update(['delivery_confirmed_at' => null]);
        $order->payments()->first()->update(['status' => 'autorizado']);

        $closure = app(PurchaseOrderClosureEvaluator::class)->evaluate($order);

        $this->assertFalse($closure['ready']);
        $this->assertContains('Checklist de todos los hitos', $closure['pending']);
        $this->assertContains('Pagos completos sin saldo', $closure['pending']);
    }

    public function test_fully_delivered_paid_and_invoiced_order_is_ready(): void
    {
        $closure = app(PurchaseOrderClosureEvaluator::class)->evaluate($this->readyOrder());

        $this->assertTrue($closure['ready'], implode(', ', $closure['pending']));
    }

    public function test_invoice_in_process_blocks_closure(): void
    {
        $order = $this->readyOrder();
        PurchaseOrderInvoice::create([
            'purchase_order_id' => $order->id,
            'folio' => 'F-PEND',
            'status' => PurchaseOrderInvoice::STATUS_EN_PROCESO,
            'amount' => 10,
            'currency' => 'MXN',
        ]);

        $closure = app(PurchaseOrderClosureEvaluator::class)->evaluate($order);

        $this->assertSame(['Facturación aceptada'], $closure['pending']);
    }

    public function test_admin_closes_a_ready_order_and_it_gets_locked(): void
    {
        $admin = $this->user('admin');
        $order = $this->readyOrder();

        $this->actingAs($admin)
            ->post(route('purchase_orders.close', $order), ['closure_note' => 'Todo conciliado'])
            ->assertRedirect(route('purchase_orders.show', $order));

        $order->refresh();
        $this->assertNotNull($order->closed_at);
        $this->assertSame($admin->id, (int) $order->closed_by);
        $this->assertSame('Todo conciliado', $order->closure_note);

        $milestone = $order->milestones()->first();
        $this->actingAs($admin)
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'amount' => 10,
                'payment_date' => '2026-10-01',
            ])
            ->assertSessionHasErrors();
        $this->assertSame(1, Payment::count());

        $this->actingAs($admin)
            ->post(route('purchase_orders.milestones.confirm_delivery', [$order, $milestone]))
            ->assertSessionHasErrors('purchase_order');
    }

    public function test_cannot_close_an_order_that_is_not_ready(): void
    {
        $admin = $this->user('admin');
        $order = $this->readyOrder();
        $order->payments()->first()->update(['status' => 'autorizado']);

        $this->actingAs($admin)
            ->post(route('purchase_orders.close', $order))
            ->assertSessionHasErrors('purchase_order');

        $this->assertNull($order->fresh()->closed_at);
    }

    public function test_only_admin_or_the_buyer_can_close(): void
    {
        $order = $this->readyOrder();
        $buyer = $this->user('Orden de compra');
        $other = $this->user('Orden de compra');
        $order->update(['buyer_id' => $buyer->id]);

        $this->actingAs($other)->post(route('purchase_orders.close', $order))->assertForbidden();
        $this->assertNull($order->fresh()->closed_at);

        $this->actingAs($buyer)->post(route('purchase_orders.close', $order))->assertRedirect();
        $this->assertNotNull($order->fresh()->closed_at);
    }

    public function test_only_admin_can_reopen_with_a_reason(): void
    {
        $order = $this->readyOrder();
        $order->update(['closed_at' => now()]);
        $buyer = $this->user('Orden de compra');
        $order->update(['buyer_id' => $buyer->id]);

        $this->actingAs($buyer)
            ->post(route('purchase_orders.reopen', $order), ['reopen_reason' => 'Corrección de factura'])
            ->assertForbidden();

        $admin = $this->user('admin');
        $this->actingAs($admin)
            ->post(route('purchase_orders.reopen', $order), ['reopen_reason' => 'corta'])
            ->assertSessionHasErrors('reopen_reason');

        $this->actingAs($admin)
            ->post(route('purchase_orders.reopen', $order), ['reopen_reason' => 'Corrección de factura'])
            ->assertRedirect(route('purchase_orders.show', $order));

        $order->refresh();
        $this->assertNull($order->closed_at);
        $this->assertNull($order->closed_by);
    }

    public function test_order_page_shows_closure_panel_and_closed_state(): void
    {
        $admin = $this->user('admin');
        $order = $this->readyOrder();

        $this->actingAs($admin)
            ->get(route('purchase_orders.show', $order))
            ->assertOk()
            ->assertSee('Cierre de la OC')
            ->assertSee('Lista para cerrar');

        $order->update(['closed_at' => now(), 'closed_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('purchase_orders.show', $order))
            ->assertOk()
            ->assertSee('Orden de compra cerrada')
            ->assertSee('Reabrir OC');
    }

    public function test_index_has_a_closed_tray(): void
    {
        $admin = $this->user('admin');
        $closed = $this->readyOrder();
        $closed->update(['closed_at' => now()]);
        $open = $this->readyOrder();

        $this->actingAs($admin)
            ->get(route('purchase_orders.index', ['tray' => 'closed']))
            ->assertOk()
            ->assertViewHas('trayCounts', fn ($counts) => $counts['closed'] === 1)
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 1 && $orders->first()->is($closed));

        $this->assertNull($open->fresh()->closed_at);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function readyOrder(): PurchaseOrder
    {
        $supplier = Supplier::create(['rfc_name' => 'Proveedor de prueba']);
        $order = PurchaseOrder::create([
            'folio' => random_int(25000, 99999),
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => 'autorizada',
            'delivery_status' => 'entregado',
            'recurrence_type' => 'unico',
        ]);
        $milestone = PurchaseOrderMilestone::create([
            'purchase_order_id' => $order->id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 1000,
            'covered_amount' => 1000,
            'delivery_confirmed_at' => now(),
        ]);
        Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-' . random_int(10000, 99999),
            'amount' => 1000,
            'payment_date' => '2026-09-01',
            'status' => 'pagado',
        ]);
        $invoice = PurchaseOrderInvoice::create([
            'purchase_order_id' => $order->id,
            'folio' => 'F-' . random_int(1000, 9999),
            'status' => PurchaseOrderInvoice::STATUS_ACEPTADA,
            'amount' => 1000,
            'currency' => 'MXN',
        ]);
        $invoice->milestones()->sync([$milestone->id]);

        return $order;
    }
}
