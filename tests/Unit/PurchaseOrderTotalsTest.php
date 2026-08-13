<?php

namespace Tests\Unit;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class PurchaseOrderTotalsTest extends TestCase
{
    public function test_total_adds_iva_and_subtracts_retentions(): void
    {
        $purchaseOrder = new PurchaseOrder([
            'tax_rate' => 16,
            'isr_rate' => 10,
            'retention_iva_rate' => 4.1234,
            'retention_isr_rate' => 1.25,
        ]);

        $purchaseOrder->setRelation('items', new Collection([
            new PurchaseOrderItem(['quantity' => 2, 'unit_price' => 100]),
        ]));

        $this->assertSame(200.0, $purchaseOrder->subtotal);
        $this->assertSame(32.0, $purchaseOrder->iva);
        $this->assertSame(30.75, $purchaseOrder->additional_taxes_amount);
        $this->assertSame(201.25, $purchaseOrder->total_with_iva);
    }

    public function test_percentage_milestones_reconcile_rounding_with_order_total(): void
    {
        $purchaseOrder = new PurchaseOrder([
            'tax_rate' => 16,
            'isr_rate' => 10,
            'retention_iva_rate' => 4.1234,
            'retention_isr_rate' => 1.25,
        ]);
        $purchaseOrder->setRelation('items', new Collection([
            new PurchaseOrderItem(['quantity' => 2, 'unit_price' => 100]),
        ]));

        $milestones = collect([33.33, 33.33, 33.34])->map(function (float $value, int $index) use ($purchaseOrder) {
            $milestone = new \App\Models\PurchaseOrderMilestone([
                'id' => $index + 1,
                'value_type' => 'porcentaje',
                'value' => $value,
            ]);
            $milestone->setRelation('purchaseOrder', $purchaseOrder);

            return $milestone;
        });
        $purchaseOrder->setRelation('milestones', $milestones);

        $this->assertSame($purchaseOrder->total_with_iva, $milestones->sum('effective_amount'));
    }

    public function test_two_fifty_percent_milestones_reconcile_an_odd_cent_total(): void
    {
        $purchaseOrder = new PurchaseOrder(['tax_rate' => 16]);
        $purchaseOrder->setRelation('items', new Collection([
            new PurchaseOrderItem(['quantity' => 1, 'unit_price' => 126684.25]),
        ]));

        $milestones = collect([50, 50])->map(function (int $value, int $index) use ($purchaseOrder) {
            $milestone = new \App\Models\PurchaseOrderMilestone([
                'value_type' => 'porcentaje',
                'value' => $value,
            ]);
            $milestone->id = $index + 1;
            $milestone->setRelation('purchaseOrder', $purchaseOrder);

            return $milestone;
        });
        $purchaseOrder->setRelation('milestones', $milestones);

        $this->assertSame(146953.73, $purchaseOrder->total_with_iva);
        $this->assertSame(73476.87, $milestones->first()->effective_amount);
        $this->assertSame(73476.86, $milestones->last()->effective_amount);
        $this->assertSame($purchaseOrder->total_with_iva, round($milestones->sum('effective_amount'), 2));
    }
}