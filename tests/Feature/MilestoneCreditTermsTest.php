<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemDelivery;
use App\Models\PurchaseOrderMilestone;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderMilestoneChecklist;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneCreditTermsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_credit_starting_at_fulfillment_does_not_require_an_invoice_to_authorize(): void
    {
        $milestone = $this->milestone(['credit_start' => 'fulfillment_validated', 'credit_days' => 30]);
        $service = app(PurchaseOrderMilestoneChecklist::class);

        $this->assertSame(['Entrega confirmada'], $service->forMilestone($milestone)['pending']);

        $milestone->update(['delivery_confirmed_at' => now()]);
        $checklist = $service->forMilestone($milestone->fresh());

        $this->assertTrue($checklist['complete']);
        $this->assertFalse($checklist['requirements'][1]['required']);
    }

    public function test_credit_starting_at_invoice_or_legacy_still_requires_the_invoice(): void
    {
        $service = app(PurchaseOrderMilestoneChecklist::class);

        foreach (['invoice_validation', null] as $start) {
            $milestone = $this->milestone(['credit_start' => $start, 'delivery_confirmed_at' => now()]);
            $this->assertSame(['Factura aceptada'], $service->forMilestone($milestone)['pending']);
        }
    }

    public function test_confirming_delivery_recalculates_due_date_and_pending_payments(): void
    {
        $admin = $this->admin();
        $order = $this->order();
        $milestone = $this->milestone(['credit_start' => 'fulfillment_validated', 'credit_days' => 30], $order);
        $payment = $this->payment($milestone);
        $this->linkDelivery($order, $milestone);

        $this->actingAs($admin)
            ->post(route('purchase_orders.milestones.confirm_delivery', [$order, $milestone]))
            ->assertRedirect(route('purchase_orders.show', $order));

        $expected = today()->addDays(30)->toDateString();
        $this->assertSame($expected, $milestone->fresh()->due_date->toDateString());
        $this->assertSame($expected, $payment->fresh()->payment_date->toDateString());
    }

    public function test_accepting_an_invoice_starts_the_credit_term(): void
    {
        $admin = $this->admin();
        $order = $this->order();
        $milestone = $this->milestone(['credit_start' => 'invoice_validation', 'credit_days' => 15], $order);
        $payment = $this->payment($milestone);
        $invoice = PurchaseOrderInvoice::create([
            'purchase_order_id' => $order->id,
            'folio' => 'F-1',
            'status' => PurchaseOrderInvoice::STATUS_EN_PROCESO,
            'amount' => 1000,
            'currency' => 'MXN',
        ]);

        $this->actingAs($admin)
            ->patch(route('invoices.status.update', $invoice), [
                'status' => PurchaseOrderInvoice::STATUS_ACEPTADA,
                'milestone_ids' => [$milestone->id],
            ])
            ->assertRedirect();

        $this->assertNotNull($invoice->fresh()->accepted_at);
        $expected = today()->addDays(15)->toDateString();
        $this->assertSame($expected, $milestone->fresh()->due_date->toDateString());
        $this->assertSame($expected, $payment->fresh()->payment_date->toDateString());
    }

    public function test_due_date_is_not_changed_when_it_breaks_the_date_sequence(): void
    {
        $admin = $this->admin();
        $order = $this->order();
        $first = $this->milestone(['due_date' => '2026-12-01'], $order);
        $this->payment($first, '2026-12-01');
        $second = $this->milestone(['credit_start' => 'fulfillment_validated', 'credit_days' => 0, 'due_date' => '2026-12-15'], $order);
        $this->payment($second, '2026-12-15');
        $this->linkDelivery($order, $second);

        $this->actingAs($admin)
            ->post(route('purchase_orders.milestones.confirm_delivery', [$order, $second]))
            ->assertSessionHas('warning');

        $this->assertSame('2026-12-15', $second->fresh()->due_date->toDateString());
    }

    public function test_credit_milestone_requires_terms_but_cash_and_advances_do_not(): void
    {
        $admin = $this->admin();
        $order = $this->order('emitida');
        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'description' => 'Varilla',
            'unit' => 'pza',
            'quantity' => 10,
            'unit_price' => 1000,
        ]);
        $base = [
            'purchase_order_id' => $order->id,
            'value_type' => 'fijo',
            'value' => 100,
            'due_date' => '2099-01-01',
        ];

        $this->actingAs($admin)
            ->post(route('milestones.store'), $base + ['payment_condition' => 'credito'])
            ->assertSessionHasErrors(['credit_start', 'credit_days']);

        $this->actingAs($admin)
            ->post(route('milestones.store'), $base + ['payment_condition' => 'credito', 'credit_start' => 'fulfillment_validated', 'credit_days' => 45])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->post(route('milestones.store'), $base + ['payment_condition' => 'contado', 'credit_start' => 'invoice_validation', 'credit_days' => 10])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->post(route('milestones.store'), $base + ['payment_condition' => 'credito', 'is_advance' => 1])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('purchase_order_milestones', ['purchase_order_id' => $order->id, 'credit_start' => 'fulfillment_validated', 'credit_days' => 45]);
        $this->assertSame(2, PurchaseOrderMilestone::where('purchase_order_id', $order->id)->whereNull('credit_days')->count());
    }

    public function test_order_page_shows_the_credit_term(): void
    {
        $order = $this->order();
        $this->milestone(['credit_start' => 'fulfillment_validated', 'credit_days' => 30], $order);

        $this->actingAs($this->admin())
            ->get(route('purchase_orders.show', $order))
            ->assertOk()
            ->assertSee('30 días desde')
            ->assertSee('cumplimiento validado')
            ->assertSee('Inicio del plazo de crédito');
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function order(string $status = 'autorizada'): PurchaseOrder
    {
        $supplier = Supplier::create(['rfc_name' => 'Proveedor de prueba']);

        return PurchaseOrder::create([
            'folio' => random_int(25000, 99999),
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'amount' => 100000,
            'status' => $status,
            'recurrence_type' => 'unico',
        ]);
    }

    private function milestone(array $attributes = [], ?PurchaseOrder $order = null): PurchaseOrderMilestone
    {
        return PurchaseOrderMilestone::create(array_merge([
            'purchase_order_id' => ($order ?? $this->order())->id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 1000,
            'covered_amount' => 0,
            'due_date' => '2026-08-30',
        ], $attributes));
    }

    private function payment(PurchaseOrderMilestone $milestone, string $date = '2026-08-30'): Payment
    {
        return Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-' . random_int(10000, 99999),
            'amount' => 1000,
            'payment_date' => $date,
            'status' => 'por_autorizar',
        ]);
    }

    private function linkDelivery(PurchaseOrder $order, PurchaseOrderMilestone $milestone): void
    {
        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'description' => 'Varilla',
            'unit' => 'pza',
            'quantity' => 10,
            'unit_price' => 100,
        ]);
        PurchaseOrderItemDelivery::create([
            'purchase_order_item_id' => $item->id,
            'milestone_id' => $milestone->id,
            'quantity' => 1,
            'delivered_at' => now(),
        ]);
    }
}
