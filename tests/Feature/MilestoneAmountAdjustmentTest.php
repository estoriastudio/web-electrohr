<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PurchaseOrderMilestone;
use App\Models\PurchaseOrderMilestoneAdjustment;
use App\Services\MilestoneCreditTerms;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PurchaseOrderPaymentTestCase;

class MilestoneAmountAdjustmentTest extends PurchaseOrderPaymentTestCase
{
    private function payload(float $amount = 600): array
    {
        return [
            'amount' => $amount,
            'reason' => 'Entrega parcial del proveedor',
            'partial' => [
                'concept' => 'Segunda entrega', 'payment_condition' => 'credito',
                'credit_start' => 'fulfillment_validated', 'credit_days' => 15, 'due_date' => '2027-02-28',
            ],
        ];
    }

    private function endpoint(PurchaseOrderMilestone $milestone): string
    {
        return route('purchase_orders.milestones.adjust_amount', [$milestone->purchaseOrder, $milestone]);
    }

    public function test_buyer_can_reduce_own_milestone_and_define_new_partial_conditions(): void
    {
        $buyer = $this->user('Orden de compra');
        $milestone = $this->milestone(['delivery_confirmed_at' => now()], $buyer);
        $this->payment($milestone, 800);
        $this->payment($milestone, 200, 'pospuesto');
        $this->payment($milestone, 500, 'rechazado');

        $this->actingAs($buyer)->post($this->endpoint($milestone), $this->payload() + [
            'concept' => 'No permitido', 'credit_days' => 999, 'payment_condition' => 'contado',
        ])->assertSessionHasNoErrors();

        $milestone->refresh();
        $this->assertSame('600.00', $milestone->value);
        $this->assertSame('Entrega original', $milestone->concept);
        $this->assertSame('credito', $milestone->payment_condition);
        $this->assertEquals(30, $milestone->credit_days);
        $partial = PurchaseOrderMilestone::where('split_from_milestone_id', $milestone->id)->sole();
        $this->assertSame('400.00', $partial->value);
        $this->assertEquals(15, $partial->credit_days);
        $this->assertSame('fulfillment_validated', $partial->credit_start);
        $this->assertNull($partial->delivery_confirmed_at);
        $this->assertNull($partial->invoice_date);
        $this->assertSame(0, $partial->invoices()->count());
        $this->assertSame(1, $partial->payments()->count());
        $this->assertSame('400.00', $partial->payments()->sole()->amount);
        $this->assertEquals(1000, $milestone->purchaseOrder->milestones->sum(fn ($m) => $m->effective_amount));
        $this->assertEquals(600, $milestone->payments()->whereIn('status', Payment::COMMITTED_STATUSES)->sum('amount'));
        $this->assertDatabaseHas('payments', ['milestone_id' => $milestone->id, 'status' => 'rechazado', 'amount' => 500]);
        $this->assertDatabaseHas('purchase_order_milestone_adjustments', [
            'milestone_id' => $milestone->id, 'user_id' => $buyer->id,
            'previous_amount' => 1000, 'new_amount' => 600, 'reason' => $this->payload()['reason'],
            'partial_milestone_id' => $partial->id,
        ]);
    }

    public function test_only_owner_and_admin_can_adjust_amount(): void
    {
        $owner = $this->user('Orden de compra');
        $milestone = $this->milestone([], $owner);
        $this->payment($milestone, 1000);
        foreach ([$this->user('Orden de compra'), $this->user('Pagos')] as $user) {
            $this->actingAs($user)->post($this->endpoint($milestone), $this->payload())->assertForbidden();
        }
        $this->actingAs($this->user())->post($this->endpoint($milestone), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(1, PurchaseOrderMilestoneAdjustment::count());
    }

    public static function lockedStatuses(): array
    {
        return [['autorizado'], ['pagado']];
    }

    #[DataProvider('lockedStatuses')]
    public function test_any_authorized_or_paid_payment_blocks_amount_changes(string $status): void
    {
        $milestone = $this->milestone();
        $this->payment($milestone, 100, $status);
        $this->payment($milestone, 900);
        $this->actingAs($this->user())->post($this->endpoint($milestone), $this->payload())->assertSessionHasErrors('amount');
        $this->assertSame('1000.00', $milestone->fresh()->value);
        $this->assertSame(0, PurchaseOrderMilestoneAdjustment::count());
        $this->assertSame(1, PurchaseOrderMilestone::count());
    }

    public function test_accepted_invoice_is_a_floor_but_pending_invoice_is_not(): void
    {
        $milestone = $this->milestone();
        $invoice = $this->invoice($milestone, 700, 'aceptada');
        $invoice->milestones()->attach($milestone);
        $this->actingAs($this->user())->post($this->endpoint($milestone), $this->payload())->assertSessionHasErrors('amount');
        $this->assertSame(0, PurchaseOrderMilestoneAdjustment::count());
        $this->post($this->endpoint($milestone), $this->payload(700))->assertSessionHasNoErrors();
        $this->assertSame('700.00', $milestone->fresh()->value);
    }

    public function test_pending_invoice_does_not_set_a_floor(): void
    {
        $milestone = $this->milestone();
        $this->invoice($milestone, 900)->milestones()->attach($milestone);
        $this->actingAs($this->user())->post($this->endpoint($milestone), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('600.00', $milestone->fresh()->value);
    }

    public function test_covered_amount_is_a_floor_even_for_legacy_data_without_paid_payment_rows(): void
    {
        $milestone = $this->milestone(['covered_amount' => 650]);
        $this->actingAs($this->user())->post($this->endpoint($milestone), $this->payload())->assertSessionHasErrors('amount');
        $this->assertSame('1000.00', $milestone->fresh()->value);
        $this->assertSame(0, PurchaseOrderMilestoneAdjustment::count());
    }

    public function test_a_milestone_cannot_be_adjusted_through_another_order(): void
    {
        $milestone = $this->milestone();
        $other = $this->milestone();
        $this->actingAs($this->user())->post(
            route('purchase_orders.milestones.adjust_amount', [$other->purchaseOrder, $milestone]),
            $this->payload(),
        )->assertNotFound();
        $this->assertSame('1000.00', $milestone->fresh()->value);
        $this->assertSame(0, PurchaseOrderMilestoneAdjustment::count());
    }

    public function test_increase_cannot_exceed_order_and_failure_rolls_back(): void
    {
        $milestone = $this->milestone(['value' => 600]);
        $this->payment($milestone, 600);
        $this->actingAs($this->user())->post($this->endpoint($milestone), $this->payload(1000.01))->assertSessionHasErrors('amount');
        $this->assertSame('600.00', $milestone->fresh()->value);
        $this->assertSame(0, PurchaseOrderMilestoneAdjustment::count());
        $this->post($this->endpoint($milestone), $this->payload(1000))->assertSessionHasNoErrors();
        $this->assertSame(1, PurchaseOrderMilestone::count());
        $this->assertEquals(1000, $milestone->payments()->sum('amount'));
    }

    public function test_percentage_is_converted_to_fixed_and_audited(): void
    {
        $milestone = $this->milestone(['value_type' => 'porcentaje', 'value' => 100]);
        $this->payment($milestone, 1000);
        $this->actingAs($this->user())->post($this->endpoint($milestone), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('fijo', $milestone->fresh()->value_type);
        $this->assertDatabaseHas('purchase_order_milestone_adjustments', [
            'previous_value_type' => 'porcentaje', 'previous_value' => 100, 'previous_amount' => 1000, 'new_amount' => 600,
        ]);
    }

    public function test_closed_order_and_missing_reason_are_rejected(): void
    {
        $milestone = $this->milestone();
        $this->actingAs($this->user())->post($this->endpoint($milestone), array_replace($this->payload(), ['reason' => ' ']))
            ->assertSessionHasErrors('reason');
        $milestone->purchaseOrder->update(['closed_at' => now()]);
        $this->post($this->endpoint($milestone), $this->payload())->assertSessionHasErrors('purchase_order');
        $this->assertSame(0, PurchaseOrderMilestoneAdjustment::count());
    }

    public function test_partial_requires_complete_conditions_and_keeps_transaction_atomic(): void
    {
        $milestone = $this->milestone();
        $this->actingAs($this->user())->post($this->endpoint($milestone), ['amount' => 600, 'reason' => 'Entrega parcial'])
            ->assertSessionHasErrors('partial');
        $this->assertSame('1000.00', $milestone->fresh()->value);
        $this->assertSame(0, PurchaseOrderMilestoneAdjustment::count());
    }

    public function test_legacy_update_cannot_bypass_amount_audit_or_change_buyer_conditions(): void
    {
        $buyer = $this->user('Orden de compra');
        $milestone = $this->milestone([], $buyer);
        $data = ['value_type' => 'fijo', 'value' => 800, 'payment_condition' => 'contado', 'due_date' => '2027-01-30'];
        $this->actingAs($buyer)->put(route('milestones.update', $milestone), $data)->assertForbidden();
        $this->actingAs($this->user())->put(route('milestones.update', $milestone), $data)->assertSessionHasErrors('value');
        $this->assertSame('1000.00', $milestone->fresh()->value);
    }

    public function test_partial_credit_uses_its_own_base_date_independent_of_previous_milestones(): void
    {
        $milestone = $this->milestone();
        $this->payment($milestone, 1000);
        $this->actingAs($this->user())->post($this->endpoint($milestone), $this->payload())->assertSessionHasNoErrors();
        $partial = PurchaseOrderMilestone::where('split_from_milestone_id', $milestone->id)->sole();
        $this->assertNull(app(MilestoneCreditTerms::class)->computedDueDate($partial));
        $partial->update(['delivery_confirmed_at' => '2027-01-01']);
        app(MilestoneCreditTerms::class)->apply($partial);
        $this->assertSame('2027-01-16', $partial->fresh()->due_date->toDateString());
        $this->assertSame('2027-01-30', $milestone->fresh()->due_date->toDateString());
        $this->assertSame('2027-01-16', $partial->payments()->sole()->payment_date->toDateString());
    }

    public function test_admin_can_change_conditions_without_counting_rejected_payments(): void
    {
        $milestone = $this->milestone();
        $this->payment($milestone, 1000, 'rechazado');
        $this->actingAs($this->user())->put(route('milestones.update', $milestone), [
            'value_type' => 'fijo', 'value' => 1000, 'payment_condition' => 'contado', 'due_date' => '2027-02-01',
        ])->assertSessionHasNoErrors();
        $this->assertSame('contado', $milestone->fresh()->payment_condition);
        $this->assertSame('1000.00', $milestone->value);
        $this->assertSame('rechazado', $milestone->payments()->sole()->status);
    }

    public function test_rendered_modal_presets_new_hito_conditions_without_editing_original_ones(): void
    {
        $this->travelTo(Carbon::parse('2026-10-27'));
        $milestone = $this->milestone();
        $html = view('purchase_orders.partials._adjust_milestone_amount', [
            'milestone' => $milestone, 'milestonePosition' => 1,
            'purchaseOrder' => $milestone->purchaseOrder, 'purchaseOrderPayments' => collect(),
        ])->render();
        $this->assertStringContainsString('name="partial[credit_days]"', $html);
        $this->assertStringContainsString('value="30"', $html);
        $this->assertStringContainsString('name="reason" maxlength="1000" required', $html);
        $this->assertStringContainsString('name="partial[due_date]" value="2027-01-30"', $html);
        $this->assertStringNotContainsString('name="payment_condition"', $html);
    }
}
