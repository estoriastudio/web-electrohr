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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MilestoneChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_advance_milestone_has_no_requirements(): void
    {
        $milestone = $this->milestone(['is_advance' => true]);

        $checklist = app(PurchaseOrderMilestoneChecklist::class)->forMilestone($milestone);

        $this->assertTrue($checklist['complete']);
        $this->assertSame([], $checklist['pending']);
    }

    public function test_cash_milestone_only_requires_confirmed_delivery(): void
    {
        $milestone = $this->milestone(['payment_condition' => 'contado']);
        $service = app(PurchaseOrderMilestoneChecklist::class);

        $this->assertSame(['Entrega confirmada'], $service->forMilestone($milestone)['pending']);

        $milestone->update(['delivery_confirmed_at' => now()]);

        $this->assertTrue($service->forMilestone($milestone->fresh())['complete']);
    }

    public function test_credit_milestone_requires_delivery_and_accepted_invoice(): void
    {
        $milestone = $this->milestone();
        $service = app(PurchaseOrderMilestoneChecklist::class);

        $this->assertSame(['Entrega confirmada', 'Factura aceptada'], $service->forMilestone($milestone)['pending']);

        $milestone->update(['delivery_confirmed_at' => now()]);
        $invoice = $this->invoice($milestone, PurchaseOrderInvoice::STATUS_EN_PROCESO);

        $checklist = $service->forMilestone($milestone->fresh());
        $this->assertSame(['Factura aceptada'], $checklist['pending']);

        $invoice->update(['status' => PurchaseOrderInvoice::STATUS_ACEPTADA]);

        $this->assertTrue($service->forMilestone($milestone->fresh())['complete']);
    }

    public function test_ensure_complete_blocks_incomplete_milestones(): void
    {
        $milestone = $this->milestone();
        $payment = $this->payment($milestone);

        $this->expectException(ValidationException::class);

        app(PurchaseOrderMilestoneChecklist::class)->ensureComplete([$payment]);
    }

    public function test_authorize_multiple_is_blocked_until_checklist_is_complete(): void
    {
        $admin = $this->admin();
        $milestone = $this->milestone();
        $payment = $this->payment($milestone);

        $this->actingAs($admin)
            ->post(route('payments.authorize_multiple'), ['payment_ids' => [$payment->id]])
            ->assertSessionHasErrors('payment_ids');

        $this->assertSame('por_autorizar', $payment->fresh()->status);

        $this->completeChecklist($milestone);

        $this->actingAs($admin)
            ->post(route('payments.authorize_multiple'), ['payment_ids' => [$payment->id]])
            ->assertRedirect(route('payments.index'));

        $payment->refresh();
        $this->assertSame('autorizado', $payment->status);
        $this->assertNotNull($payment->checklist_verified_at);
    }

    public function test_individual_authorization_and_swipe_are_blocked(): void
    {
        $admin = $this->admin();
        $milestone = $this->milestone();
        $payment = $this->payment($milestone);

        $this->actingAs($admin)
            ->patch(route('payments.update', $payment), ['status' => 'autorizado'])
            ->assertSessionHasErrors('status');
        $this->actingAs($admin)
            ->postJson(route('payments.swipe', $payment), ['status' => 'autorizado'])
            ->assertStatus(422);

        $this->assertSame('por_autorizar', $payment->fresh()->status);

        $this->actingAs($admin)
            ->postJson(route('payments.swipe', $payment), ['status' => 'rechazado'])
            ->assertOk();
    }

    public function test_approve_with_payments_requires_checklist_except_for_advances(): void
    {
        $admin = $this->admin();
        $order = $this->purchaseOrder('emitida');
        $regular = $this->milestone([], $order);
        $regularPayment = $this->payment($regular);

        $this->actingAs($admin)
            ->patch(route('purchase_orders.approve_with_payments', $order), ['payment_ids' => [$regularPayment->id]])
            ->assertSessionHasErrors('payment_ids');
        $this->assertSame('emitida', $order->fresh()->status);

        $regular->update(['is_advance' => true]);

        $this->actingAs($admin)
            ->patch(route('purchase_orders.approve_with_payments', $order), ['payment_ids' => [$regularPayment->id]])
            ->assertRedirect(route('purchase_orders.show', $order));
        $this->assertSame('autorizada', $order->fresh()->status);
    }

    public function test_payment_tray_lists_checklist_and_filters_by_it(): void
    {
        $admin = $this->admin();
        $ready = $this->milestone();
        $this->completeChecklist($ready);
        $readyPayment = $this->payment($ready);
        $blockedPayment = $this->payment($this->milestone());

        $this->actingAs($admin)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Entrega confirmada')
            ->assertSee('1 pago(s) aún no aparecen aquí')
            ->assertViewHas('pendingChecklistCount', 1)
            ->assertViewHas('payments', fn ($payments) => $payments->count() === 1 && $payments->first()->is($readyPayment));

        $this->actingAs($admin)
            ->get(route('payments.index', ['checklist_filter' => 'all']))
            ->assertSee('Checklist pendiente')
            ->assertViewHas('payments', fn ($payments) => $payments->count() === 2);

        $this->actingAs($admin)
            ->get(route('payments.index', ['checklist_filter' => 'complete']))
            ->assertViewHas('payments', fn ($payments) => $payments->count() === 1 && $payments->first()->is($readyPayment));

        $this->actingAs($admin)
            ->get(route('payments.index', ['checklist_filter' => 'pending']))
            ->assertViewHas('payments', fn ($payments) => $payments->count() === 1 && $payments->first()->is($blockedPayment));

        $this->actingAs($admin)
            ->get(route('payments.interactive'))
            ->assertViewHas('payments', fn ($payments) => $payments->count() === 1)
            ->assertViewHas('skippedByChecklist', 1);
    }

    public function test_order_page_disables_authorization_while_checklist_is_pending(): void
    {
        $admin = $this->admin();
        $order = $this->purchaseOrder('autorizada');
        $milestone = $this->milestone([], $order);
        $this->payment($milestone);

        $this->actingAs($admin)
            ->get(route('purchase_orders.show', $order))
            ->assertOk()
            ->assertSee('Bloqueado: checklist pendiente (Entrega confirmada, Factura aceptada)', false);

        $this->actingAs($admin)
            ->get(route('purchase_orders.show', $order))
            ->assertSee('Autorizar bloqueado');

        $this->completeChecklist($milestone);

        $this->actingAs($admin)
            ->get(route('purchase_orders.show', $order))
            ->assertDontSee('Autorizar bloqueado');
    }

    public function test_selection_excludes_payments_with_pending_checklist(): void
    {
        $admin = $this->admin();
        $payment = $this->payment($this->milestone());

        $this->actingAs($admin)
            ->postJson(route('payments.authorization.selection.sync'), ['selected_ids' => [$payment->id]])
            ->assertOk()
            ->assertJson(['selected_ids' => [], 'selected_count' => 0]);
    }

    public function test_delivery_can_be_linked_to_a_milestone_and_confirmed(): void
    {
        $admin = $this->admin();
        $order = $this->purchaseOrder('autorizada');
        $milestone = $this->milestone(['payment_condition' => 'contado'], $order);
        $item = $this->item($order);

        $this->actingAs($admin)
            ->post(route('purchase_orders.deliveries.store', $order), [
                'item_ids' => [$item->id],
                'milestone_id' => $milestone->id,
                'delivery_evidence' => UploadedFile::fake()->create('remision.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('purchase_orders.show', $order));

        $this->assertSame(1, PurchaseOrderItemDelivery::where('milestone_id', $milestone->id)->count());
        $this->assertNull($milestone->fresh()->delivery_confirmed_at);

        $this->actingAs($admin)
            ->post(route('purchase_orders.milestones.confirm_delivery', [$order, $milestone]))
            ->assertRedirect(route('purchase_orders.show', $order));

        $milestone->refresh();
        $this->assertNotNull($milestone->delivery_confirmed_at);
        $this->assertSame($admin->id, (int) $milestone->delivery_confirmed_by);

        $this->actingAs($admin)
            ->get(route('purchase_orders.show', $order))
            ->assertOk()
            ->assertSee('Listo para autorizar');

        $delivery = PurchaseOrderItemDelivery::where('milestone_id', $milestone->id)->first();
        $this->actingAs($admin)
            ->delete(route('purchase_orders.deliveries.revert', [$order, $delivery]))
            ->assertRedirect();
        $this->assertNull($milestone->fresh()->delivery_confirmed_at);
    }

    public function test_advance_milestone_shows_delivery_fulfillment_button(): void
    {
        $admin = $this->admin();
        $order = $this->purchaseOrder('autorizada');
        $milestone = $this->milestone(['is_advance' => true], $order);
        $item = $this->item($order);
        PurchaseOrderItemDelivery::create([
            'purchase_order_item_id' => $item->id,
            'milestone_id' => $milestone->id,
            'quantity' => 1,
            'delivered_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('purchase_orders.show', $order))
            ->assertOk()
            ->assertSee('Confirmar entrega completa del hito');

        $this->actingAs($admin)
            ->post(route('purchase_orders.milestones.confirm_delivery', [$order, $milestone]))
            ->assertRedirect(route('purchase_orders.show', $order));

        $this->assertNotNull($milestone->fresh()->delivery_confirmed_at);
    }

    public function test_cannot_confirm_delivery_without_linked_deliveries(): void
    {
        $admin = $this->admin();
        $order = $this->purchaseOrder('autorizada');
        $milestone = $this->milestone(['payment_condition' => 'contado'], $order);

        $this->actingAs($admin)
            ->post(route('purchase_orders.milestones.confirm_delivery', [$order, $milestone]))
            ->assertSessionHas('error');

        $this->assertNull($milestone->fresh()->delivery_confirmed_at);
    }

    public function test_delivery_rejects_a_milestone_from_another_order(): void
    {
        $admin = $this->admin();
        $order = $this->purchaseOrder('autorizada');
        $otherMilestone = $this->milestone();
        $item = $this->item($order);

        $this->actingAs($admin)
            ->post(route('purchase_orders.deliveries.store', $order), [
                'item_ids' => [$item->id],
                'milestone_id' => $otherMilestone->id,
                'delivery_evidence' => UploadedFile::fake()->create('remision.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasErrors('milestone_id');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function purchaseOrder(string $status = 'autorizada'): PurchaseOrder
    {
        $supplier = Supplier::create(['rfc_name' => 'Proveedor de prueba']);

        return PurchaseOrder::create([
            'folio' => random_int(25000, 99999),
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => $status,
            'recurrence_type' => 'unico',
        ]);
    }

    private function milestone(array $attributes = [], ?PurchaseOrder $order = null): PurchaseOrderMilestone
    {
        return PurchaseOrderMilestone::create(array_merge([
            'purchase_order_id' => ($order ?? $this->purchaseOrder())->id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 1000,
            'covered_amount' => 0,
            'due_date' => '2026-08-30',
        ], $attributes));
    }

    private function payment(PurchaseOrderMilestone $milestone): Payment
    {
        return Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-' . random_int(10000, 99999),
            'amount' => 1000,
            'payment_date' => '2026-08-30',
            'status' => 'por_autorizar',
        ]);
    }

    private function invoice(PurchaseOrderMilestone $milestone, string $status): PurchaseOrderInvoice
    {
        $invoice = PurchaseOrderInvoice::create([
            'purchase_order_id' => $milestone->purchase_order_id,
            'folio' => 'F-' . random_int(1000, 9999),
            'status' => $status,
            'amount' => 1000,
            'currency' => 'MXN',
        ]);
        $invoice->milestones()->sync([$milestone->id]);

        return $invoice;
    }

    private function completeChecklist(PurchaseOrderMilestone $milestone): void
    {
        $this->invoice($milestone, PurchaseOrderInvoice::STATUS_ACEPTADA);
        $milestone->update(['delivery_confirmed_at' => now()]);
    }

    private function item(PurchaseOrder $order): PurchaseOrderItem
    {
        return PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'description' => 'Varilla',
            'unit' => 'pza',
            'quantity' => 10,
            'unit_price' => 100,
        ]);
    }
}
