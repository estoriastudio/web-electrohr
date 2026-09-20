<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderMilestone;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryDueDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_it_stores_an_item_delivery_due_date(): void
    {
        $admin = $this->admin();
        $purchaseOrder = $this->purchaseOrder();
        $deliveryDueDate = today()->addDay()->toDateString();

        $this->actingAs($admin)
            ->postJson(route('purchase_orders.items.store', $purchaseOrder), [
                'description' => 'Material de prueba',
                'unit' => 'PZA',
                'quantity' => 2,
                'unit_price' => 150,
                'delivery_due_date' => $deliveryDueDate,
            ])
            ->assertOk()
            ->assertJsonPath('delivery_due_date', $deliveryDueDate);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'delivery_due_date' => $deliveryDueDate,
        ]);
    }

    public function test_it_rejects_free_text_and_past_delivery_dates(): void
    {
        $admin = $this->admin();
        $purchaseOrder = $this->purchaseOrder();

        $this->actingAs($admin)
            ->postJson(route('purchase_orders.items.store', $purchaseOrder), $this->itemPayload([
                'delivery_due_date' => '2 SEMANAS',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_due_date');

        $this->actingAs($admin)
            ->postJson(route('purchase_orders.items.store', $purchaseOrder), $this->itemPayload([
                'delivery_due_date' => today()->subDay()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_due_date');
    }

    public function test_it_updates_an_item_delivery_due_date(): void
    {
        $admin = $this->admin();
        $purchaseOrder = $this->purchaseOrder();
        $item = $this->item($purchaseOrder, today()->addDay()->toDateString());
        $deliveryDueDate = today()->addDays(5)->toDateString();

        $this->actingAs($admin)
            ->patchJson(route('purchase_orders.items.update', [$purchaseOrder, $item]), [
                'delivery_due_date' => $deliveryDueDate,
            ])
            ->assertOk()
            ->assertJsonPath('delivery_due_date', $deliveryDueDate);

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $item->id,
            'delivery_due_date' => $deliveryDueDate,
        ]);
    }

    public function test_delivery_indicators_only_include_pending_authorized_orders_with_real_due_dates(): void
    {
        $admin = $this->admin();
        $overdueOrder = $this->purchaseOrder(['folio' => 10001]);
        $futureOrder = $this->purchaseOrder(['folio' => 10002]);
        $deliveredOrder = $this->purchaseOrder(['folio' => 10003, 'delivery_status' => 'entregado']);
        $unauthorizedOrder = $this->purchaseOrder(['folio' => 10004, 'status' => 'emitida']);

        $this->item($overdueOrder, today()->subDay()->toDateString());
        $this->item($futureOrder, today()->addDay()->toDateString());
        $this->item($deliveredOrder, today()->subDay()->toDateString());
        $this->item($unauthorizedOrder, today()->subDay()->toDateString());

        $dashboardResponse = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();

        $pendingDeliveries = $dashboardResponse->viewData('ocsPendientesEntregarSitio');
        $overdueDeliveries = $dashboardResponse->viewData('ocsVencidasEntrega');

        $this->assertTrue($pendingDeliveries->contains('id', $overdueOrder->id));
        $this->assertTrue($pendingDeliveries->contains('id', $futureOrder->id));
        $this->assertFalse($pendingDeliveries->contains('id', $deliveredOrder->id));
        $this->assertFalse($pendingDeliveries->contains('id', $unauthorizedOrder->id));
        $this->assertTrue($overdueDeliveries->contains('id', $overdueOrder->id));
        $this->assertFalse($overdueDeliveries->contains('id', $futureOrder->id));

        $this->actingAs($admin)
            ->get(route('purchase_orders.overdue_deliveries'))
            ->assertOk()
            ->assertSee('#10001')
            ->assertDontSee('#10002')
            ->assertDontSee('#10003')
            ->assertDontSee('#10004');
    }

    public function test_listing_excludes_completed_orders_and_sorts_by_payment_or_delivery_due_date(): void
    {
        $admin = $this->admin();
        $paymentFirst = $this->purchaseOrder(['folio' => 20001]);
        $deliveryFirst = $this->purchaseOrder(['folio' => 20002]);
        $completed = $this->purchaseOrder([
            'folio' => 20003,
            'delivery_status' => 'entregado',
        ]);

        $this->milestone($paymentFirst, today()->addDay()->toDateString());
        $this->milestone($deliveryFirst, today()->addDays(10)->toDateString());
        $completedMilestone = $this->milestone($completed, today()->addDays(2)->toDateString());
        Payment::create([
            'milestone_id' => $completedMilestone->id,
            'folio' => 'PAY-COMPLETE-20003',
            'amount' => 1000,
            'payment_date' => today()->toDateString(),
            'status' => 'pagado',
        ]);

        $this->item($paymentFirst, today()->addDays(10)->toDateString());
        $this->item($deliveryFirst, today()->addDay()->toDateString());
        $this->item($completed, today()->addDays(2)->toDateString());

        $paymentSorted = $this->actingAs($admin)
            ->get(route('purchase_orders.index', ['sort_due' => 'payment_asc']))
            ->assertOk()
            ->viewData('orders');
        $deliverySorted = $this->actingAs($admin)
            ->get(route('purchase_orders.index', ['sort_due' => 'delivery_asc']))
            ->assertOk()
            ->viewData('orders');

        $paymentIds = collect($paymentSorted->items())->pluck('id')->all();
        $deliveryIds = collect($deliverySorted->items())->pluck('id')->all();

        $this->assertSame([$paymentFirst->id, $deliveryFirst->id], $paymentIds);
        $this->assertSame([$deliveryFirst->id, $paymentFirst->id], $deliveryIds);
        $this->assertNotContains($completed->id, $paymentIds);
        $this->assertNotContains($completed->id, $deliveryIds);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function purchaseOrder(array $attributes = []): PurchaseOrder
    {
        $supplier = Supplier::create(['rfc_name' => 'Proveedor de prueba']);

        return PurchaseOrder::create(array_merge([
            'folio' => random_int(20000, 99999),
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => 'autorizada',
            'delivery_status' => 'por_entregar',
            'recurrence_type' => 'unico',
        ], $attributes));
    }

    private function item(PurchaseOrder $purchaseOrder, string $deliveryDueDate): PurchaseOrderItem
    {
        return PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'description' => 'Material de prueba',
            'unit' => 'PZA',
            'quantity' => 1,
            'unit_price' => 100,
            'delivery_due_date' => $deliveryDueDate,
        ]);
    }

    private function milestone(PurchaseOrder $purchaseOrder, string $dueDate): PurchaseOrderMilestone
    {
        return PurchaseOrderMilestone::create([
            'purchase_order_id' => $purchaseOrder->id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 1000,
            'covered_amount' => 0,
            'due_date' => $dueDate,
        ]);
    }

    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Material de prueba',
            'unit' => 'PZA',
            'quantity' => 1,
            'unit_price' => 100,
        ], $overrides);
    }
}