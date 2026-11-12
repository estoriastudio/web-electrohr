<?php

namespace Tests\Feature;

use App\Models\MaterialVoucher;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 3: espacios propios. Obra (Gerente y Supervisor ven la OC con hitos, facturas, evidencias y entregas,
 * sin pagos), Bandeja de Dirección y bandeja de Vales por autorizar (Almacén Autoriza).
 */
class RoleSpacesTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private PurchaseOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);

        $this->supervisor = $this->userWithRole('Supervisor');
        $project = Project::create(['name' => 'Proyecto', 'client_name' => 'Cliente', 'status' => 'active', 'currency' => 'MXN']);
        ProjectWork::create(['project_id' => $project->id, 'name' => 'Obra', 'status' => 'active', 'supervisor_user_id' => $this->supervisor->id]);

        $this->order = PurchaseOrder::create([
            'folio' => 9001,
            'type' => 'materiales_servicios',
            'supplier_id' => Supplier::create(['rfc_name' => 'Proveedor'])->id,
            'project_id' => $project->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => 'autorizada',
            'recurrence_type' => 'unico',
        ]);
        $milestone = PurchaseOrderMilestone::create([
            'purchase_order_id' => $this->order->id,
            'type' => 'regular',
            'concept' => 'Anticipo de obra',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 1000,
            'covered_amount' => 0,
            'due_date' => '2026-09-15',
        ]);
        Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-7777',
            'amount' => 1000,
            'payment_date' => '2026-09-15',
            'status' => 'por_autorizar',
        ]);
    }

    public function test_site_staff_see_milestones_evidences_and_deliveries_but_not_payments(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('purchase_orders.show', $this->order))
            ->assertOk()
            ->assertSee('Anticipo de obra')
            ->assertSee('Subir evidencia')
            ->assertSee('modalDeliveryStatus', false)
            ->assertDontSee('PAY-7777')
            ->assertDontSee('Resumen económico');

        $this->actingAs($this->userWithRole('Pagos'))
            ->get(route('purchase_orders.show', $this->order))
            ->assertOk()
            ->assertSee('PAY-7777')
            ->assertSee('Resumen económico');
    }

    public function test_direction_inbox_lists_what_waits_for_direction(): void
    {
        PurchaseOrder::create([
            'folio' => 9002,
            'type' => 'materiales_servicios',
            'supplier_id' => Supplier::create(['rfc_name' => 'Otro'])->id,
            'currency' => 'MXN',
            'amount' => 500,
            'status' => 'emitida',
            'recurrence_type' => 'unico',
        ]);

        $this->actingAs($this->userWithRole('Dirección'))
            ->get(route('direction.inbox'))
            ->assertOk()
            ->assertSee('PAY-7777')
            ->assertSee('#9002')
            ->assertSee('href="'.route('direction.inbox').'"', false);

        $this->actingAs($this->userWithRole('admin'))->get(route('direction.inbox'))->assertOk();
        $this->actingAs($this->userWithRole('Pagos'))->get(route('direction.inbox'))->assertForbidden();
    }

    public function test_warehouse_authorizer_has_a_queue_of_issued_vouchers(): void
    {
        $issued = $this->voucher('VM-0001', 'emitido');
        $this->voucher('VM-0002', 'autorizado');

        $this->actingAs($this->userWithRole('Almacén Autoriza'))
            ->get(route('material_vouchers.pending_authorization'))
            ->assertOk()
            ->assertSee('VM-0001')
            ->assertDontSee('VM-0002')
            ->assertSee(route('material_vouchers.show', $issued))
            ->assertSee('Vales por autorizar');

        $this->actingAs($this->userWithRole('Almacén'))
            ->get(route('material_vouchers.pending_authorization'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return User::factory()->create()->assignRole($role);
    }

    private function voucher(string $folio, string $status): MaterialVoucher
    {
        return MaterialVoucher::forceCreate([
            'folio' => $folio,
            'folio_prefix' => 'VM',
            'folio_number' => (int) substr($folio, -4),
            'supplier_id' => $this->order->supplier_id,
            'voucher_date' => today()->toDateString(),
            'requester' => 'Solicitante',
            'status' => $status,
            'created_by' => $this->supervisor->id,
        ]);
    }
}
