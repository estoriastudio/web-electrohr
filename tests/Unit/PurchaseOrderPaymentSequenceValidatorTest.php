<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;
use App\Models\Supplier;
use App\Services\PurchaseOrderPaymentSequenceValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseOrderPaymentSequenceValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_a_later_initial_payment_that_advances_ahead_of_an_earlier_one(): void
    {
        $purchaseOrder = $this->createPurchaseOrder();
        $this->createMilestoneWithPayment($purchaseOrder, 'por_autorizar', '2026-09-01');
        $laterPayment = $this->createMilestoneWithPayment($purchaseOrder, 'por_autorizar', '2026-09-15');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Hito 2');

        app(PurchaseOrderPaymentSequenceValidator::class)->ensureStatusSequence(
            $purchaseOrder,
            [$laterPayment->id => 'autorizado'],
        );
    }

    public function test_it_accepts_consistent_status_changes_for_multiple_initial_payments_in_the_same_order(): void
    {
        $purchaseOrder = $this->createPurchaseOrder();
        $firstPayment = $this->createMilestoneWithPayment($purchaseOrder, 'por_autorizar', '2026-09-01');
        $secondPayment = $this->createMilestoneWithPayment($purchaseOrder, 'por_autorizar', '2026-09-15');

        app(PurchaseOrderPaymentSequenceValidator::class)->ensureStatusSequence(
            $purchaseOrder,
            [
                $firstPayment->id => 'autorizado',
                $secondPayment->id => 'autorizado',
            ],
        );

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_an_initial_payment_date_that_breaks_its_order_sequence(): void
    {
        $purchaseOrder = $this->createPurchaseOrder();
        $this->createMilestoneWithPayment($purchaseOrder, 'por_autorizar', '2026-09-15');
        $laterPayment = $this->createMilestoneWithPayment($purchaseOrder, 'por_autorizar', '2026-09-30');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no puede ser anterior');

        app(PurchaseOrderPaymentSequenceValidator::class)->ensurePaymentDateSequence(
            $purchaseOrder,
            [$laterPayment->id => '2026-09-01'],
        );
    }

    private function createPurchaseOrder(): PurchaseOrder
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

    private function createMilestoneWithPayment(PurchaseOrder $purchaseOrder, string $status, string $paymentDate): Payment
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

        return Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-' . random_int(10000, 99999),
            'amount' => 100,
            'payment_date' => $paymentDate,
            'status' => $status,
        ]);
    }
}