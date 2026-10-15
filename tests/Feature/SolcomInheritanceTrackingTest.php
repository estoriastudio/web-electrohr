<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolcomInheritanceTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->buyer = User::factory()->create();
        $this->buyer->assignRole('admin');
    }

    public function test_solcom_with_partial_inheritance_stays_in_entrada_and_full_goes_to_salida(): void
    {
        $partial = $this->solcom(2);
        $full = $this->solcom(2);
        $untouched = $this->solcom(1);

        $this->inherit($partial->items[0], 'pendiente');
        $order = $this->inherit($full->items[0], 'pendiente');
        $this->inherit($full->items[1], 'pendiente', $order);

        $entrada = PurchaseRequest::pendingInheritance()->pluck('id')->all();
        $salida = PurchaseRequest::fullyInherited()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$partial->id, $untouched->id], $entrada);
        $this->assertSame([$full->id], $salida);

        $this->actingAs($this->buyer)
            ->get(route('purchasing.solcom_pile', ['section' => 'entrada']))
            ->assertOk()
            ->assertSee('#' . $partial->folio)
            ->assertDontSee('#' . $full->folio);

        $this->actingAs($this->buyer)
            ->get(route('purchasing.solcom_pile', ['section' => 'salida']))
            ->assertOk()
            ->assertSee('#' . $full->folio)
            ->assertDontSee('#' . $partial->folio);
    }

    public function test_deleting_the_order_releases_its_items(): void
    {
        $solcom = $this->solcom(1);
        $order = $this->inherit($solcom->items[0], 'pendiente');

        $this->assertTrue(PurchaseRequest::fullyInherited()->whereKey($solcom->id)->exists());

        $order->delete();

        $this->assertTrue(PurchaseRequest::pendingInheritance()->whereKey($solcom->id)->exists());
        $this->assertFalse(PurchaseRequest::fullyInherited()->whereKey($solcom->id)->exists());
    }

    public function test_removing_the_item_from_the_order_releases_it(): void
    {
        $solcom = $this->solcom(1);
        $order = $this->inherit($solcom->items[0], 'pendiente');

        $order->items()->delete();

        $this->assertTrue(PurchaseRequest::pendingInheritance()->whereKey($solcom->id)->exists());
    }

    public function test_store_rejects_an_item_already_inherited_by_another_order(): void
    {
        $solcom = $this->solcom(2);
        $this->inherit($solcom->items[0], 'pendiente');

        $response = $this->actingAs($this->buyer)
            ->from(route('purchase_orders.create_from_solcom', $solcom))
            ->post(route('purchase_orders.store'), $this->storePayload($solcom, [$solcom->items[0]->id]));

        $response->assertSessionHasErrors('selected_item_ids');
        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_store_inherits_only_pending_items_and_keeps_solcom_in_entrada(): void
    {
        $solcom = $this->solcom(2);
        $this->inherit($solcom->items[0], 'pendiente');

        $this->actingAs($this->buyer)
            ->post(route('purchase_orders.store'), $this->storePayload($solcom, [$solcom->items[1]->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, PurchaseOrder::count());
        $this->assertTrue(PurchaseRequest::fullyInherited()->whereKey($solcom->id)->exists());
    }

    public function test_create_form_marks_inherited_items_and_redirects_when_all_are_inherited(): void
    {
        $solcom = $this->solcom(2);
        $order = $this->inherit($solcom->items[0], 'pendiente');

        $this->actingAs($this->buyer)
            ->get(route('purchase_orders.create_from_solcom', $solcom))
            ->assertOk()
            ->assertSee('OC #' . $order->folio)
            ->assertSee('Heredado por completo');

        $this->inherit($solcom->items[1], 'pendiente');

        $this->actingAs($this->buyer)
            ->get(route('purchase_orders.create_from_solcom', $solcom))
            ->assertRedirect(route('purchasing.solcom_pile', ['section' => 'salida']));
    }

    public function test_item_is_locked_for_emitida_and_autorizada_orders_only(): void
    {
        $solcom = $this->solcom(3);
        $this->inherit($solcom->items[0], 'pendiente');
        $this->inherit($solcom->items[1], 'emitida');
        $this->inherit($solcom->items[2], 'autorizada');

        $items = PurchaseRequestItem::whereIn('id', $solcom->items->pluck('id'))->orderBy('id')->get();

        $this->assertFalse($items[0]->is_locked);
        $this->assertTrue($items[1]->is_locked);
        $this->assertTrue($items[2]->is_locked);
    }

    public function test_locked_item_quantity_cannot_be_updated_or_deleted(): void
    {
        $solcom = $this->solcom(1);
        $item = $solcom->items[0];
        $order = $this->inherit($item, 'emitida');

        $this->actingAs($this->buyer)
            ->patchJson(route('purchase_requests.items.update', [$solcom, $item]), ['purchase_quantity' => 99])
            ->assertStatus(422);

        $this->actingAs($this->buyer)
            ->deleteJson(route('purchase_requests.items.destroy', [$solcom, $item]))
            ->assertStatus(422);

        $this->assertSame('5.0000', (string) $item->fresh()->purchase_quantity);

        $this->actingAs($this->buyer)
            ->get(route('purchase_requests.show', $solcom))
            ->assertOk()
            ->assertSee('OC #' . $order->folio)
            ->assertSee('Heredado por completo');
    }

    public function test_item_can_be_updated_when_order_is_pending_or_was_deleted(): void
    {
        $solcom = $this->solcom(2);
        $this->inherit($solcom->items[0], 'pendiente');
        $deleted = $this->inherit($solcom->items[1], 'emitida');
        $deleted->delete();

        foreach ($solcom->items as $item) {
            $this->actingAs($this->buyer)
                ->patchJson(route('purchase_requests.items.update', [$solcom, $item]), ['purchase_quantity' => 7])
                ->assertOk();

            $this->assertSame('7.0000', (string) $item->fresh()->purchase_quantity);
        }
    }

    public function test_workload_counts_partially_inherited_solcom_as_pending(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Orden de compra');

        $solcom = $this->solcom(2, ['assigned_to' => $user->id, 'status' => 'sent_to_purchasing']);
        $this->inherit($solcom->items[0], 'pendiente');

        $this->actingAs($this->buyer)
            ->get(route('purchasing.workload'))
            ->assertOk()
            ->assertViewHas('unlinkedRequestCounts', fn ($counts) => (int) $counts->get($user->id, 0) === 1);
    }

    public function test_partial_quantity_keeps_the_item_pending_until_fully_inherited(): void
    {
        $solcom = $this->solcom(1);
        $item = $solcom->items[0];

        $this->actingAs($this->buyer)
            ->post(route('purchase_orders.store'), $this->storePayload($solcom, [$item->id], [$item->id => 2]))
            ->assertSessionHasNoErrors();

        $item = $item->fresh();
        $this->assertSame(2.0, $item->inherited_quantity);
        $this->assertSame(3.0, $item->remaining_quantity);
        $this->assertFalse($item->is_inherited);
        $this->assertTrue($item->is_partially_inherited);
        $this->assertTrue(PurchaseRequest::pendingInheritance()->whereKey($solcom->id)->exists());
        $this->assertSame('2.0000', (string) PurchaseOrderItem::where('purchase_request_item_id', $item->id)->value('quantity'));

        $this->actingAs($this->buyer)
            ->get(route('purchase_orders.create_from_solcom', $solcom))
            ->assertOk()
            ->assertSee('max="3"', false);

        $this->actingAs($this->buyer)
            ->post(route('purchase_orders.store'), $this->storePayload($solcom, [$item->id], [$item->id => 3]))
            ->assertSessionHasNoErrors();

        $item = $item->fresh();
        $this->assertTrue($item->is_inherited);
        $this->assertTrue(PurchaseRequest::fullyInherited()->whereKey($solcom->id)->exists());

        $this->actingAs($this->buyer)
            ->post(route('purchase_orders.store'), $this->storePayload($solcom, [$item->id], [$item->id => 1]))
            ->assertSessionHasErrors('selected_item_ids');
        $this->assertSame(2, PurchaseOrder::count());
    }

    public function test_store_rejects_a_quantity_above_the_remaining_quantity(): void
    {
        $solcom = $this->solcom(1);
        $item = $solcom->items[0];
        $this->inherit($item, 'pendiente', null, 4);

        $this->actingAs($this->buyer)
            ->post(route('purchase_orders.store'), $this->storePayload($solcom, [$item->id], [$item->id => 2]))
            ->assertSessionHasErrors('selected_item_ids');

        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_quantity_edits_cannot_break_the_inherited_total(): void
    {
        $solcom = $this->solcom(1);
        $item = $solcom->items[0];
        $order = $this->inherit($item, 'pendiente', null, 3);
        $orderItem = $order->items()->first();

        $this->actingAs($this->buyer)
            ->patchJson(route('purchase_requests.items.update', [$solcom, $item]), ['purchase_quantity' => 2])
            ->assertStatus(422);

        $this->actingAs($this->buyer)
            ->patchJson(route('purchase_orders.items.update', [$order, $orderItem]), ['quantity' => 6])
            ->assertStatus(422);

        $this->actingAs($this->buyer)
            ->patchJson(route('purchase_orders.items.update', [$order, $orderItem]), ['quantity' => 5])
            ->assertOk();
    }

    private function solcom(int $itemCount, array $attributes = []): PurchaseRequest
    {
        static $folio = 1000;
        $folio++;

        $project = Project::create([
            'name' => 'Proyecto ' . $folio,
            'client_name' => 'Cliente',
            'currency' => 'MXN',
            'status' => 'active',
        ]);
        $work = ProjectWork::create([
            'project_id' => $project->id,
            'name' => 'Obra ' . $folio,
            'status' => 'active',
        ]);

        $solcom = PurchaseRequest::create(array_merge([
            'folio' => $folio,
            'project_id' => $project->id,
            'project_work_id' => $work->id,
            'zone' => 'Zona',
            'delivery_address' => 'Dirección',
            'short_description' => 'Descripción',
            'request_date' => now()->toDateString(),
            'need_date' => now()->addWeek()->toDateString(),
            'requested_by' => $this->buyer->id,
            'assigned_to' => $this->buyer->id,
            'status' => 'sent_to_purchasing',
        ], $attributes));
        $solcom->projectWorks()->sync([$work->id]);

        for ($i = 1; $i <= $itemCount; $i++) {
            PurchaseRequestItem::create([
                'purchase_request_id' => $solcom->id,
                'code' => 'C' . $i,
                'description' => "Concepto {$folio}-{$i}",
                'unit' => 'pza',
                'requested_quantity' => 5,
                'purchase_quantity' => 5,
            ]);
        }

        return $solcom->load('items');
    }

    private function inherit(PurchaseRequestItem $item, string $status, ?PurchaseOrder $order = null, ?float $quantity = null): PurchaseOrder
    {
        $order ??= PurchaseOrder::create([
            'folio' => random_int(25000, 99999999),
            'type' => 'materiales_servicios',
            'supplier_id' => Supplier::create(['rfc_name' => 'Proveedor'])->id,
            'purchase_request_id' => $item->purchase_request_id,
            'currency' => 'MXN',
            'amount' => 0,
            'status' => $status,
            'recurrence_type' => 'unico',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'purchase_request_item_id' => $item->id,
            'description' => $item->description,
            'unit' => $item->unit,
            'quantity' => $quantity ?? $item->purchase_quantity,
            'unit_price' => 10,
        ]);

        return $order;
    }

    private function storePayload(PurchaseRequest $solcom, array $itemIds, array $quantities = []): array
    {
        $supplier = Supplier::create([
            'rfc_name' => 'Proveedor de Prueba S.A. de C.V.',
            'commercial_name' => 'Proveedor de Prueba',
            'rfc_num' => 'PPR010101AAA',
            'status' => 'active',
            'street' => 'Calle Principal 123',
            'postal_code' => '01000',
            'colony' => 'Centro',
            'city' => 'Ciudad de México',
            'state' => 'Ciudad de México',
        ]);
        $supplier->contacts()->create([
            'name' => 'Contacto principal',
            'email' => 'contacto@example.test',
            'phone' => '5555555555',
            'is_primary' => true,
        ]);
        $supplier->locations()->create([
            'name' => 'Cuenta principal',
            'currency' => 'MXN',
            'account_statement_path' => 'supplier_locations/1/account_statements/1.pdf',
        ]);

        return [
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'tax_rate' => '16',
            'purchase_request_id' => $solcom->id,
            'project_id' => $solcom->project_id,
            'project_work_ids' => [$solcom->project_work_id],
            'selected_item_ids' => $itemIds,
            'item_quantities' => $quantities,
        ];
    }
}
