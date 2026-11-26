<?php

namespace Tests\Feature;

use App\Models\MobileAsset;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderAtemporalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);
    }

    public function test_admin_and_direction_can_toggle_atemporal_on_an_authorized_order(): void
    {
        $order = $this->purchaseOrder('autorizada');

        foreach (['admin', 'Dirección'] as $role) {
            $user = User::factory()->create()->assignRole($role);

            $this->actingAs($user)
                ->get(route('purchase_orders.edit', $order))
                ->assertOk()
                ->assertSee('OC Atemporal')
                ->assertSee('name="is_atemporal"', false);

            foreach ([true, false] as $isAtemporal) {
                $this->actingAs($user)
                    ->put(route('purchase_orders.update', $order), $this->validUpdateData($order) + [
                        'is_atemporal' => $isAtemporal ? '1' : '0',
                    ])
                    ->assertRedirect(route('purchase_orders.show', $order));

                $this->assertSame($isAtemporal, $order->fresh()->is_atemporal);
            }
        }

        $this->actingAs(User::factory()->create()->assignRole('Dirección'))
            ->patch(route('purchase_orders.archive', $order))
            ->assertForbidden();
    }

    public function test_order_buyer_cannot_change_atemporal_and_omitting_it_preserves_the_value(): void
    {
        $buyer = User::factory()->create()->assignRole('Orden de compra');
        $order = $this->purchaseOrder('pendiente', $buyer);
        $order->update(['is_atemporal' => true]);

        $this->actingAs($buyer)
            ->get(route('purchase_orders.edit', $order))
            ->assertOk()
            ->assertDontSee('name="is_atemporal"', false);

        $this->actingAs($buyer)
            ->put(route('purchase_orders.update', $order), $this->validUpdateData($order) + [
                'is_atemporal' => '0',
            ])
            ->assertForbidden();

        $this->assertTrue($order->fresh()->is_atemporal);

        $this->actingAs($buyer)
            ->put(route('purchase_orders.update', $order), $this->validUpdateData($order))
            ->assertRedirect(route('purchase_orders.show', $order));

        $this->assertTrue($order->fresh()->is_atemporal);
    }

    private function purchaseOrder(string $status, ?User $buyer = null): PurchaseOrder
    {
        $supplier = Supplier::create(['rfc_name' => 'Proveedor de prueba']);
        $asset = MobileAsset::create(['name' => 'Equipo de prueba']);

        return PurchaseOrder::create([
            'buyer_id' => $buyer?->id,
            'folio' => random_int(25000, 99999),
            'type' => 'mantenimiento',
            'supplier_id' => $supplier->id,
            'mobile_asset_id' => $asset->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => $status,
            'recurrence_type' => 'unico',
        ]);
    }

    private function validUpdateData(PurchaseOrder $order): array
    {
        return [
            'type' => 'mantenimiento',
            'supplier_id' => $order->supplier_id,
            'currency' => 'MXN',
            'tax_rate' => '16',
            'mobile_asset_id' => $order->mobile_asset_id,
        ];
    }
}
