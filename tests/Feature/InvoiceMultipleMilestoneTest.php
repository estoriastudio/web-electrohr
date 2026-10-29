<?php

namespace Tests\Feature;

use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use Tests\Support\PurchaseOrderPaymentTestCase;

class InvoiceMultipleMilestoneTest extends PurchaseOrderPaymentTestCase
{
    private function sibling(PurchaseOrderMilestone $milestone, float $value): PurchaseOrderMilestone
    {
        return $milestone->purchaseOrder->milestones()->create([
            'type' => 'regular', 'concept' => 'Segundo hito', 'payment_condition' => 'credito',
            'value_type' => 'fijo', 'value' => $value,
        ]);
    }

    private function accept(PurchaseOrderInvoice $invoice, array $ids)
    {
        return $this->patch(route('invoices.status.update', $invoice), ['status' => 'aceptada', 'milestone_ids' => $ids]);
    }

    public function test_new_invoice_can_link_multiple_milestones_when_the_sum_matches(): void
    {
        $first = $this->milestone(['value' => 400]);
        $second = $this->sibling($first, 600);
        $data = ['purchase_order_id' => $first->purchase_order_id, 'amount' => 1000, 'currency' => 'MXN'];

        $this->actingAs($this->user())->post(route('invoices.store'), $data + ['milestone_ids' => [$first->id, $second->id]])
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], PurchaseOrderInvoice::first()->milestones->pluck('id')->all());
    }

    public function test_new_invoice_rejects_sums_that_exceed_or_fall_short(): void
    {
        $first = $this->milestone(['value' => 400]);
        $second = $this->sibling($first, 600);
        $data = ['purchase_order_id' => $first->purchase_order_id, 'currency' => 'MXN'];

        $this->actingAs($this->user())->post(route('invoices.store'), $data + ['amount' => 900, 'milestone_ids' => [$first->id, $second->id]])
            ->assertSessionHasErrors('milestone_ids');
        $this->post(route('invoices.store'), $data + ['amount' => 1000, 'milestone_ids' => [$first->id]])
            ->assertSessionHasErrors('milestone_ids');
        $this->post(route('invoices.store'), $data + ['amount' => 400.4, 'milestone_ids' => [$first->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, PurchaseOrderInvoice::count());
    }

    public function test_a_milestone_cannot_have_a_second_invoice(): void
    {
        $first = $this->milestone(['value' => 400]);
        $second = $this->sibling($first, 600);
        $data = ['purchase_order_id' => $first->purchase_order_id, 'currency' => 'MXN'];

        $this->actingAs($this->user())->post(route('invoices.store'), $data + ['amount' => 400, 'milestone_ids' => [$first->id]])
            ->assertSessionHasNoErrors();
        $this->post(route('invoices.store'), $data + ['amount' => 1000, 'milestone_ids' => [$first->id, $second->id]])
            ->assertSessionHasErrors('milestone_ids');
        $this->assertSame(1, PurchaseOrderInvoice::count());
    }

    public function test_acceptance_requires_matching_milestones_and_blocks_occupied_ones(): void
    {
        $first = $this->milestone(['value' => 400]);
        $second = $this->sibling($first, 600);
        $invoice = $this->invoice($first, 1000);
        $other = $this->invoice($first, 400);

        $this->actingAs($this->user())->patch(route('invoices.status.update', $invoice), ['status' => 'aceptada'])
            ->assertSessionHasErrors('milestone_ids');
        $this->accept($invoice, [$first->id])->assertSessionHasErrors('milestone_ids');
        $this->assertSame('en_proceso', $invoice->fresh()->status);

        $this->accept($invoice, [$first->id, $second->id])->assertSessionHasNoErrors();
        $this->assertSame('aceptada', $invoice->fresh()->status);
        $this->assertSame(2, $invoice->milestones()->count());

        $this->accept($other, [$first->id])->assertSessionHasErrors('milestone_ids');
        $this->assertSame(0, $other->milestones()->count());
        $this->accept($invoice, [$first->id, $second->id])->assertSessionHasNoErrors();
    }

    public function test_milestone_from_another_order_is_rejected(): void
    {
        $milestone = $this->milestone(['value' => 500]);
        $other = $this->milestone(['value' => 500]);
        $invoice = $this->invoice($milestone, 1000);

        $this->actingAs($this->user());
        $this->accept($invoice, [$milestone->id, $other->id])->assertSessionHasErrors('milestone_ids');
        $this->assertSame(0, $invoice->milestones()->count());
    }

    public function test_rejecting_an_invoice_releases_its_milestones(): void
    {
        $milestone = $this->milestone(['value' => 500]);
        $invoice = $this->invoice($milestone, 500);
        $replacement = $this->invoice($milestone, 500);

        $this->actingAs($this->user());
        $this->accept($invoice, [$milestone->id])->assertSessionHasNoErrors();
        $this->accept($replacement, [$milestone->id])->assertSessionHasErrors('milestone_ids');

        $this->patch(route('invoices.status.update', $invoice), ['status' => 'rechazada'])->assertSessionHasNoErrors();
        $this->assertSame(0, $invoice->milestones()->count());
        $this->accept($replacement, [$milestone->id])->assertSessionHasNoErrors();
    }

    public function test_milestone_json_marks_occupied_milestones_for_the_upload_form(): void
    {
        $milestone = $this->milestone();
        $this->invoice($milestone)->milestones()->attach($milestone);
        $this->actingAs($this->user())->getJson(route('milestones.for_order', $milestone->purchaseOrder))
            ->assertOk()->assertJsonPath('0.invoice_linked', true);
    }
}
