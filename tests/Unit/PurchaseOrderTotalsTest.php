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
}