<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderMilestone;
use App\Services\PurchaseOrderCedularBackfill;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseOrderCedularBackfillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'purchase_order_milestone_credits',
            'invoice_milestone',
            'purchase_order_invoices',
            'payments',
            'purchase_order_milestones',
            'purchase_order_items',
            'purchase_orders',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('status')->default('emitida');
            $table->string('currency')->default('MXN');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->nullable();
            $table->decimal('isr_rate', 7, 4)->nullable();
            $table->decimal('retention_iva_rate', 7, 4)->nullable();
            $table->decimal('retention_isr_rate', 7, 4)->nullable();
            $table->decimal('cedular_rate', 7, 4)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('description')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_price', 14, 4);
            $table->timestamps();
        });
        Schema::create('purchase_order_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('type')->nullable();
            $table->string('concept')->nullable();
            $table->string('payment_condition')->nullable();
            $table->boolean('is_advance')->default(false);
            $table->string('value_type')->nullable();
            $table->decimal('value', 15, 2)->default(0);
            $table->decimal('covered_amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('milestone_id');
            $table->string('folio')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->decimal('applied_amount', 15, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('status')->default('por_autorizar');
            $table->timestamps();
        });
        Schema::create('purchase_order_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('status')->default('en_proceso');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('credit_note_amount', 15, 2)->nullable();
            $table->decimal('net_scope', 15, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_order_milestone_credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('milestone_id');
            $table->decimal('amount', 15, 2)->default(0);
        });
        Schema::create('invoice_milestone', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_order_invoice_id');
            $table->unsignedBigInteger('purchase_order_milestone_id');
        });
    }

    public function test_preview_and_apply_update_eligible_order_and_pending_percentage_payment(): void
    {
        $order = $this->issuedOrder();
        $milestone = PurchaseOrderMilestone::create([
            'purchase_order_id' => $order->id,
            'type' => 'regular',
            'payment_condition' => 'contado',
            'value_type' => 'porcentaje',
            'value' => 100,
            'covered_amount' => 0,
            'due_date' => '2026-12-01',
        ]);
        $pending = Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-CED-1',
            'amount' => 1030,
            'payment_date' => '2026-12-01',
            'status' => 'por_autorizar',
        ]);

        $backfill = app(PurchaseOrderCedularBackfill::class);
        $preview = $backfill->preview();

        $this->assertCount(1, $preview['eligible']);
        $this->assertSame(1030.0, $preview['eligible'][0]['anterior']);
        $this->assertSame(970.0, $preview['eligible'][0]['propuesto']);

        $result = $backfill->apply();

        $this->assertSame(970.0, (float) $order->fresh()->amount);
        $this->assertSame(970.0, (float) $pending->fresh()->amount);
        $this->assertCount(1, $result['applied']);
        $this->assertSame('actualizada', $result['applied'][0]['resultado']);

        $second = $backfill->apply();
        $this->assertSame(970.0, (float) $order->fresh()->amount);
        $this->assertSame(970.0, (float) $pending->fresh()->amount);
        $this->assertSame('actualizada', $second['applied'][0]['resultado']);
    }

    public function test_conflict_is_omitted_and_closed_order_is_only_reported(): void
    {
        $conflict = $this->issuedOrder();
        $milestone = $conflict->milestones()->create([
            'type' => 'regular',
            'payment_condition' => 'contado',
            'value_type' => 'fijo',
            'value' => 1100,
            'covered_amount' => 1100,
            'due_date' => '2026-12-01',
        ]);
        Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-CED-CONFLICT',
            'amount' => 1100,
            'paid_amount' => 1100,
            'applied_amount' => 1100,
            'payment_date' => '2026-12-01',
            'status' => 'pagado',
        ]);

        $closed = $this->issuedOrder();
        $closed->update(['closed_at' => now(), 'amount' => 1030]);

        $backfill = app(PurchaseOrderCedularBackfill::class);
        $preview = $backfill->preview();
        $result = $backfill->apply();

        $this->assertCount(1, $preview['conflicts']);
        $this->assertCount(1, $preview['closed']);
        $this->assertSame(1030.0, (float) $conflict->fresh()->amount);
        $this->assertSame(1030.0, (float) $closed->fresh()->amount);
        $this->assertCount(0, $result['applied']);

        $this->artisan('purchase-orders:backfill-cedular')
            ->expectsOutputToContain('Previsualización: no se modificarán registros.')
            ->assertExitCode(0);
    }

    private function issuedOrder(): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'folio' => random_int(10000, 99999),
            'status' => 'emitida',
            'currency' => 'MXN',
            'amount' => 1030,
            'tax_rate' => 16,
            'isr_rate' => 10,
            'retention_iva_rate' => 4,
            'retention_isr_rate' => 2,
            'cedular_rate' => 3,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'description' => 'Concepto',
            'unit' => 'servicio',
            'quantity' => 1,
            'unit_price' => 1000,
        ]);

        return $order;
    }
}
