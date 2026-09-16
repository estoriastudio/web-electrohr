<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderHealthReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_no_alerts_for_a_valid_payment_sequence(): void
    {
        $purchaseOrder = $this->createPurchaseOrder();
        $this->createMilestoneWithPayment($purchaseOrder, 'por_autorizar', '2026-09-01');
        $this->createMilestoneWithPayment($purchaseOrder, 'autorizado', '2026-09-15');
        $this->createMilestoneWithPayment($purchaseOrder, 'pagado', '2026-09-30');

        $this->artisan('purchase-orders:health')
            ->expectsOutputToContain('Criterios del reporte:')
            ->expectsOutputToContain('Alcance: cada OC activa se analiza por separado; solo se comparan hitos de la misma OC y pagos del mismo hito.')
            ->expectsOutputToContain('Alertas de estatus: un pago inicial de un hito posterior está más avanzado que el de un hito anterior.')
            ->expectsOutputToContain('Sin alertas de secuencia de estatus ni fechas de pago.')
            ->expectsOutputToContain('OCs con alertas: 0')
            ->expectsOutputToContain('Alertas de estatus: 0')
            ->expectsOutputToContain('Alertas de fechas: 0')
            ->assertExitCode(0);
    }

    public function test_it_alerts_when_a_later_milestone_is_more_advanced_than_an_earlier_one(): void
    {
        $purchaseOrder = $this->createPurchaseOrder();
        $this->createMilestoneWithPayment($purchaseOrder, 'por_autorizar', '2026-09-01');
        $this->createMilestoneWithPayment($purchaseOrder, 'autorizado', '2026-09-15');

        $this->artisan('purchase-orders:health')
            ->expectsOutputToContain("ALERTA [estatus] OC #{$purchaseOrder->folio}")
            ->expectsOutputToContain('Alertas de estatus: 1')
            ->expectsOutputToContain("Folios con alertas de estatus: {$purchaseOrder->folio}")
            ->assertExitCode(0);
    }

    public function test_it_alerts_when_later_payments_advance_despite_pending_middle_milestones(): void
    {
        $purchaseOrder = $this->createPurchaseOrder();
        $this->createMilestoneWithPayment($purchaseOrder, 'pagado', '2026-09-01');
        $this->createMilestoneWithPayment($purchaseOrder, 'por_autorizar', '2026-09-15');
        $this->createMilestoneWithPayment($purchaseOrder, 'pospuesto', '2026-09-20');
        $this->createMilestoneWithPayment($purchaseOrder, 'autorizado', '2026-09-30');

        $this->artisan('purchase-orders:health')
            ->expectsOutputToContain("ALERTA [estatus] OC #{$purchaseOrder->folio}")
            ->expectsOutputToContain('Alertas de estatus: 2')
            ->assertExitCode(0);
    }

    public function test_it_alerts_when_a_later_milestone_has_an_earlier_payment_date(): void
    {
        $purchaseOrder = $this->createPurchaseOrder();
        $this->createMilestoneWithPayment($purchaseOrder, 'por_autorizar', '2026-09-15');
        $this->createMilestoneWithPayment($purchaseOrder, 'por_autorizar', '2026-09-01');

        $this->artisan('purchase-orders:health')
            ->expectsOutputToContain("ALERTA [fecha] OC #{$purchaseOrder->folio}")
            ->expectsOutputToContain('Alertas de fechas: 1')
            ->expectsOutputToContain("Folios con alertas de fechas: {$purchaseOrder->folio}")
            ->assertExitCode(0);
    }

    public function test_it_does_not_compare_payments_between_different_purchase_orders(): void
    {
        $firstPurchaseOrder = $this->createPurchaseOrder();
        $this->createMilestoneWithPayment($firstPurchaseOrder, 'por_autorizar', '2026-09-15');

        $secondPurchaseOrder = $this->createPurchaseOrder();
        $this->createMilestoneWithPayment($secondPurchaseOrder, 'autorizado', '2026-09-01');

        $this->artisan('purchase-orders:health')
            ->expectsOutputToContain('OCs activas con hitos inspeccionadas: 2')
            ->expectsOutputToContain('OCs con alertas: 0')
            ->expectsOutputToContain('Alertas de estatus: 0')
            ->expectsOutputToContain('Alertas de fechas: 0')
            ->assertExitCode(0);
    }

    public function test_it_excludes_archived_orders_and_reports_milestones_without_payments(): void
    {
        $archivedPurchaseOrder = $this->createPurchaseOrder(['archived_at' => now()]);
        $this->createMilestoneWithPayment($archivedPurchaseOrder, 'por_autorizar', '2026-09-15');
        $this->createMilestoneWithPayment($archivedPurchaseOrder, 'autorizado', '2026-09-01');

        $activePurchaseOrder = $this->createPurchaseOrder();
        PurchaseOrderMilestone::create([
            'purchase_order_id' => $activePurchaseOrder->id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 100,
            'covered_amount' => 0,
            'due_date' => '2026-09-01',
        ]);

        $this->artisan('purchase-orders:health')
            ->expectsOutputToContain('OCs activas con hitos inspeccionadas: 1')
            ->expectsOutputToContain('OCs con alertas: 0')
            ->expectsOutputToContain('Hitos omitidos sin pagos: 1')
            ->assertExitCode(0);
    }

    private function createPurchaseOrder(array $attributes = []): PurchaseOrder
    {
        $supplier = Supplier::create(['rfc_name' => 'Proveedor de prueba']);

        return PurchaseOrder::create(array_merge([
            'folio' => random_int(25000, 99999),
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'amount' => 1000,
            'status' => 'autorizada',
            'recurrence_type' => 'unico',
        ], $attributes));
    }

    private function createMilestoneWithPayment(PurchaseOrder $purchaseOrder, string $status, string $paymentDate): void
    {
        $milestone = PurchaseOrderMilestone::create([
            'purchase_order_id' => $purchaseOrder->id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 100,
            'covered_amount' => 0,
            'due_date' => $paymentDate,
        ]);

        Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-' . random_int(10000, 99999),
            'amount' => 100,
            'payment_date' => $paymentDate,
            'status' => $status,
        ]);
    }
}