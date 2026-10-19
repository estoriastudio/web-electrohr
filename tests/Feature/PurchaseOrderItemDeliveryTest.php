<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseOrderItemDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_partial_delivery_marks_items_stores_evidence_and_sets_partial_status(): void
    {
        $admin = $this->user('admin');
        $order = $this->purchaseOrder();
        [$a, $b] = [$this->item($order), $this->item($order)];

        $this->actingAs($admin)
            ->post(route('purchase_orders.deliveries.store', $order), [
                'item_ids' => [$a->id],
                'delivery_evidence' => UploadedFile::fake()->create('remision.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('purchase_orders.show', $order));

        $this->assertNotNull($a->fresh()->delivered_at);
        $this->assertNull($b->fresh()->delivered_at);
        $this->assertSame('parcial', $order->fresh()->delivery_status);
        $this->assertSame(50, $order->fresh()->delivery_progress);
        $this->assertDatabaseCount('purchase_order_evidences', 1);
        $this->assertSame($order->evidences()->first()->id, $a->deliveries()->first()->evidence_id);
    }

    public function test_delivering_all_items_sets_status_to_delivered(): void
    {
        $admin = $this->user('admin');
        $order = $this->purchaseOrder();
        [$a, $b] = [$this->item($order), $this->item($order)];

        $this->deliver($admin, $order, [$a->id]);
        $this->deliver($admin, $order, [$b->id]);

        $order->refresh();
        $this->assertSame('entregado', $order->delivery_status);
        $this->assertTrue($order->is_delivered);
        $this->assertSame(100, $order->delivery_progress);
    }

    public function test_it_requires_evidence_and_valid_pending_items(): void
    {
        $admin = $this->user('admin');
        $order = $this->purchaseOrder();
        $other = $this->item($this->purchaseOrder());
        $item = $this->item($order);

        $this->actingAs($admin)
            ->post(route('purchase_orders.deliveries.store', $order), ['item_ids' => [$item->id]])
            ->assertSessionHasErrors('delivery_evidence');

        $this->actingAs($admin)
            ->post(route('purchase_orders.deliveries.store', $order), [
                'item_ids' => [$other->id],
                'delivery_evidence' => UploadedFile::fake()->create('remision.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasErrors('item_ids.0');

        $this->deliver($admin, $order, [$item->id]);
        $this->actingAs($admin)
            ->post(route('purchase_orders.deliveries.store', $order), [
                'item_ids' => [$item->id],
                'delivery_evidence' => UploadedFile::fake()->create('remision.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasErrors('item_ids.0');
    }

    public function test_legacy_partial_order_keeps_status_without_selected_items(): void
    {
        $admin = $this->user('admin');
        $order = $this->purchaseOrder(['delivery_status' => 'parcial']);
        $item = $this->item($order);

        $this->assertNull($item->delivered_at);

        $this->actingAs($admin)
            ->postJson(route('purchase_orders.items.store', $order), [
                'description' => 'Otro', 'unit' => 'PZA', 'quantity' => 1, 'unit_price' => 10,
            ])
            ->assertOk();

        $this->assertSame('parcial', $order->fresh()->delivery_status);

        $this->deliver($admin, $order, $order->items()->pluck('id')->all());
        $this->assertSame('entregado', $order->fresh()->delivery_status);
    }

    public function test_adding_an_item_to_a_delivered_order_reopens_delivery(): void
    {
        $admin = $this->user('admin');
        $order = $this->purchaseOrder();
        $item = $this->item($order);
        $this->deliver($admin, $order, [$item->id]);

        $this->actingAs($admin)
            ->postJson(route('purchase_orders.items.store', $order), [
                'description' => 'Otro', 'unit' => 'PZA', 'quantity' => 1, 'unit_price' => 10,
            ])
            ->assertOk();

        $this->assertSame('parcial', $order->fresh()->delivery_status);
    }

    public function test_only_admin_can_revert_a_delivery(): void
    {
        $admin = $this->user('admin');
        $solmat = $this->user('Solmat');
        $order = $this->purchaseOrder();
        $item = $this->item($order);
        $this->deliver($admin, $order, [$item->id]);
        $delivery = $item->deliveries()->firstOrFail();

        $this->actingAs($solmat)
            ->delete(route('purchase_orders.deliveries.revert', [$order, $delivery]))
            ->assertForbidden();
        $this->assertNotNull($item->fresh()->delivered_at);

        $this->actingAs($admin)
            ->delete(route('purchase_orders.deliveries.revert', [$order, $delivery]))
            ->assertRedirect();

        $item->refresh();
        $this->assertNull($item->delivered_at);
        $this->assertEquals(0, $item->delivered_quantity);
        $this->assertSame('por_entregar', $order->fresh()->delivery_status);
    }

    public function test_item_quantity_can_be_delivered_in_several_partial_deliveries(): void
    {
        $admin = $this->user('admin');
        $order = $this->purchaseOrder();
        $item = $this->item($order, 10);

        $this->deliver($admin, $order, [$item->id], [$item->id => 4]);

        $item->refresh();
        $this->assertEquals(4, $item->delivered_quantity);
        $this->assertNull($item->delivered_at);
        $this->assertSame('parcial', $order->fresh()->delivery_status);
        $this->assertSame(40, $order->fresh()->delivery_progress);

        $this->deliver($admin, $order, [$item->id], [$item->id => 6]);

        $item->refresh();
        $this->assertEquals(10, $item->delivered_quantity);
        $this->assertNotNull($item->delivered_at);
        $this->assertSame(2, $item->deliveries()->count());
        $this->assertSame('entregado', $order->fresh()->delivery_status);
    }

    public function test_quantity_defaults_to_pending_and_cannot_exceed_it(): void
    {
        $admin = $this->user('admin');
        $order = $this->purchaseOrder();
        $item = $this->item($order, 10);
        $this->deliver($admin, $order, [$item->id], [$item->id => 4]);

        $this->actingAs($admin)
            ->post(route('purchase_orders.deliveries.store', $order), [
                'item_ids' => [$item->id],
                'quantities' => [$item->id => 6.5],
                'delivery_evidence' => UploadedFile::fake()->create('remision.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasErrors('quantities.' . $item->id);
        $this->assertEquals(4, $item->fresh()->delivered_quantity);

        $this->deliver($admin, $order, [$item->id]);
        $this->assertEquals(10, $item->fresh()->delivered_quantity);
    }

    public function test_reverting_one_partial_delivery_keeps_the_others(): void
    {
        $admin = $this->user('admin');
        $order = $this->purchaseOrder();
        $item = $this->item($order, 10);
        $this->deliver($admin, $order, [$item->id], [$item->id => 4]);
        $this->deliver($admin, $order, [$item->id], [$item->id => 6]);

        $this->actingAs($admin)
            ->delete(route('purchase_orders.deliveries.revert', [$order, $item->deliveries()->get()->last()]))
            ->assertRedirect();

        $item->refresh();
        $this->assertEquals(4, $item->delivered_quantity);
        $this->assertNull($item->delivered_at);
        $this->assertSame('parcial', $order->fresh()->delivery_status);
    }

    public function test_lowering_item_quantity_completes_delivery(): void
    {
        $admin = $this->user('admin');
        $order = $this->purchaseOrder();
        $item = $this->item($order, 10);
        $this->deliver($admin, $order, [$item->id], [$item->id => 4]);

        $this->actingAs($admin)
            ->patchJson(route('purchase_orders.items.update', [$order, $item]), ['quantity' => 4])
            ->assertOk();

        $this->assertNotNull($item->fresh()->delivered_at);
        $this->assertSame('entregado', $order->fresh()->delivery_status);
    }

    public function test_show_page_renders_delivery_progress_and_modal(): void
    {
        $admin = $this->user('admin');
        $order = $this->purchaseOrder();
        [$a, $b] = [$this->item($order), $this->item($order)];
        $this->deliver($admin, $order, [$a->id]);

        $this->actingAs($admin)
            ->get(route('purchase_orders.show', $order))
            ->assertOk()
            ->assertSee('50% entregado (1/2)', false)
            ->assertSee('Registrar entrega')
            ->assertSee('Historial de entregas')
            ->assertSee('name="quantities[' . $b->id . ']"', false)
            ->assertSee('title="Entrega pendiente"', false)
            ->assertDontSee('Cambiar entrega');
    }

    public function test_inventory_and_solmat_can_search_by_folio_and_report_delivery_from_the_new_view(): void
    {
        foreach (['Inventario', 'Solmat'] as $role) {
            $user = $this->user($role);
            $order = $this->purchaseOrder();
            $item = $this->item($order);

            $this->actingAs($user)
                ->get(route('purchase_orders.delivery_report', ['search' => $order->folio]))
                ->assertOk()
                ->assertSee('#' . $order->folio)
                ->assertSee('Registrar entrega');

            $this->actingAs($user)
                ->post(route('purchase_orders.deliveries.store', $order), [
                    'return_to' => 'delivery_report',
                    'item_ids' => [$item->id],
                    'delivery_evidence' => UploadedFile::fake()->create('remision.pdf', 50, 'application/pdf'),
                ])
                ->assertRedirect(route('purchase_orders.delivery_report', ['search' => $order->folio, 'order' => $order->id]));

            $this->assertSame('entregado', $order->fresh()->delivery_status);
        }
    }

    public function test_inventory_cannot_access_the_purchase_order_listing_or_milestone_confirmation(): void
    {
        $inventory = $this->user('Inventario');

        $this->actingAs($inventory)->get(route('purchase_orders.index'))->assertForbidden();
        $this->actingAs($inventory)->get(route('purchase_orders.delivery_report'))->assertOk()
            ->assertSee('href="' . route('purchase_orders.delivery_report') . '"', false)
            ->assertDontSee('href="' . route('purchase_orders.index') . '"', false);
        $this->actingAs($this->user('Pagos'))->get(route('purchase_orders.delivery_report'))->assertForbidden();
    }

    private function deliver(User $user, PurchaseOrder $order, array $itemIds, array $quantities = []): void
    {
        $this->actingAs($user)
            ->post(route('purchase_orders.deliveries.store', $order), [
                'item_ids' => $itemIds,
                'quantities' => $quantities,
                'delivery_evidence' => UploadedFile::fake()->create('remision.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
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

    private function item(PurchaseOrder $order, float $quantity = 1): PurchaseOrderItem
    {
        return PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'description' => 'Material de prueba',
            'unit' => 'PZA',
            'quantity' => $quantity,
            'unit_price' => 100,
        ]);
    }
}
