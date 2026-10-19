<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderMilestone;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MilestoneEventStatus;
use App\Services\PurchaseOrderEconomicSummary;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneEventStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_new_event_is_open_pending_every_dimension(): void
    {
        $status = app(MilestoneEventStatus::class)->forMilestone($this->milestone());

        $this->assertFalse($status['closed']);
        $this->assertSame('Abierto – pendiente de cumplimiento, factura y pago', $status['label']);
        $this->assertSame('pending', $status['payment']);
        $this->assertFalse($status['regularization_pending']);
    }

    public function test_paid_and_fulfilled_event_without_invoice_is_pending_regularization(): void
    {
        $milestone = $this->milestone(['delivery_confirmed_at' => now(), 'covered_amount' => 1000]);
        $this->payment($milestone, 'pagado');

        $status = app(MilestoneEventStatus::class)->forMilestone($milestone->fresh('purchaseOrder'));

        $this->assertSame('Abierto – pendiente de factura', $status['label']);
        $this->assertTrue($status['regularization_pending']);
    }

    public function test_partially_paid_event_reports_partial_payment(): void
    {
        $milestone = $this->milestone(['covered_amount' => 400]);

        $this->assertSame('partial', app(MilestoneEventStatus::class)->forMilestone($milestone->fresh('purchaseOrder'))['payment']);
    }

    public function test_event_closes_when_fulfillment_invoice_and_payment_are_complete(): void
    {
        $milestone = $this->milestone(['delivery_confirmed_at' => now(), 'covered_amount' => 1000]);
        $this->invoice($milestone, PurchaseOrderInvoice::STATUS_ACEPTADA);

        $status = app(MilestoneEventStatus::class)->forMilestone($milestone->fresh('purchaseOrder'));

        $this->assertTrue($status['closed']);
        $this->assertSame('Evento cerrado', $status['label']);
    }

    public function test_accepting_the_first_invoice_regularizes_paid_payments_without_new_payments(): void
    {
        $admin = $this->user('admin');
        $milestone = $this->milestone(['covered_amount' => 1000]);
        $paid = $this->payment($milestone, 'pagado');
        $invoice = $this->invoice($milestone, PurchaseOrderInvoice::STATUS_EN_PROCESO, link: false);

        $this->actingAs($admin)
            ->patch(route('invoices.status.update', $invoice), [
                'status' => PurchaseOrderInvoice::STATUS_ACEPTADA,
                'milestone_ids' => [$milestone->id],
            ])
            ->assertRedirect();

        $paid->refresh();
        $this->assertSame($invoice->id, (int) $paid->regularization_invoice_id);
        $this->assertNotNull($paid->regularized_at);
        $this->assertSame(1, Payment::count());

        $this->actingAs($admin)
            ->patch(route('invoices.status.update', $invoice), ['status' => PurchaseOrderInvoice::STATUS_RECHAZADA])
            ->assertRedirect();

        $paid->refresh();
        $this->assertNull($paid->regularization_invoice_id);
        $this->assertNull($paid->regularized_at);
    }

    public function test_regularization_list_shows_only_paid_payments_without_accepted_invoice(): void
    {
        $pending = $this->milestone();
        $pendingPayment = $this->payment($pending, 'pagado');
        $invoiced = $this->milestone();
        $this->payment($invoiced, 'pagado');
        $this->invoice($invoiced, PurchaseOrderInvoice::STATUS_ACEPTADA);
        $unpaid = $this->milestone();
        $this->payment($unpaid, 'por_autorizar');

        $this->actingAs($this->user('Pagos'))
            ->get(route('payments.regularization'))
            ->assertOk()
            ->assertSee('Pagos pendientes de regularización documental')
            ->assertViewHas('payments', fn ($payments) => $payments->count() === 1 && $payments->first()->is($pendingPayment));

        $this->actingAs($this->user('Solmat'))
            ->get(route('payments.regularization'))
            ->assertForbidden();
    }

    public function test_economic_summary_uses_only_accepted_invoices(): void
    {
        $order = $this->order();
        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'description' => 'Varilla',
            'unit' => 'pza',
            'quantity' => 10,
            'unit_price' => 1000,
        ]);
        $milestone = $this->milestone([], $order);
        $this->payment($milestone, 'pagado', 1000);
        PurchaseOrderInvoice::create(['purchase_order_id' => $order->id, 'folio' => 'A', 'status' => 'aceptada', 'amount' => 3000, 'currency' => 'MXN']);
        PurchaseOrderInvoice::create(['purchase_order_id' => $order->id, 'folio' => 'B', 'status' => 'en_proceso', 'amount' => 2000, 'currency' => 'MXN']);

        $order = $order->fresh();
        $summary = app(PurchaseOrderEconomicSummary::class)->forOrder($order);
        $total = round((float) $order->total_with_iva, 2);

        $this->assertSame($total, $summary['total']);
        $this->assertSame(3000.0, $summary['invoiced']);
        $this->assertSame(round($total - 3000, 2), $summary['pending_invoicing']);
        $this->assertSame(1000.0, $summary['paid']);
        $this->assertSame(round($total - 1000, 2), $summary['pending_payment']);
        $this->assertSame(2000.0, $summary['invoiced_unpaid']);
        $this->assertSame(0.0, $summary['paid_without_invoice']);
    }

    public function test_order_page_shows_event_status_and_economic_summary(): void
    {
        $order = $this->order();
        $this->milestone([], $order);

        $this->actingAs($this->user('admin'))
            ->get(route('purchase_orders.show', $order))
            ->assertOk()
            ->assertSee('Resumen económico')
            ->assertSee('Facturado sin pagar')
            ->assertSee('Estado del evento')
            ->assertSee('Abierto – pendiente de cumplimiento, factura y pago');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function order(): PurchaseOrder
    {
        $supplier = Supplier::create(['rfc_name' => 'Proveedor de prueba']);

        return PurchaseOrder::create([
            'folio' => random_int(25000, 99999),
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => 'autorizada',
            'recurrence_type' => 'unico',
        ]);
    }

    private function milestone(array $attributes = [], ?PurchaseOrder $order = null): PurchaseOrderMilestone
    {
        return PurchaseOrderMilestone::create(array_merge([
            'purchase_order_id' => ($order ?? $this->order())->id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'credit_start' => 'invoice_validation',
            'credit_days' => 30,
            'value_type' => 'fijo',
            'value' => 1000,
            'covered_amount' => 0,
            'due_date' => '2026-08-30',
        ], $attributes));
    }

    private function payment(PurchaseOrderMilestone $milestone, string $status, float $amount = 1000): Payment
    {
        return Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-' . random_int(10000, 99999),
            'amount' => $amount,
            'payment_date' => '2026-08-30',
            'status' => $status,
        ]);
    }

    private function invoice(PurchaseOrderMilestone $milestone, string $status, bool $link = true): PurchaseOrderInvoice
    {
        $invoice = PurchaseOrderInvoice::create([
            'purchase_order_id' => $milestone->purchase_order_id,
            'folio' => 'F-' . random_int(1000, 9999),
            'status' => $status,
            'amount' => 1000,
            'currency' => 'MXN',
        ]);

        if ($link) {
            $invoice->milestones()->sync([$milestone->id]);
        }

        return $invoice;
    }
}
