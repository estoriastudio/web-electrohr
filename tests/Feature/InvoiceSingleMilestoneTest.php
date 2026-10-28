<?php

namespace Tests\Feature;

use App\Models\PurchaseOrderInvoice;
use Tests\Support\PurchaseOrderPaymentTestCase;

class InvoiceSingleMilestoneTest extends PurchaseOrderPaymentTestCase
{
    public function test_new_invoice_cannot_link_multiple_milestones_or_an_occupied_milestone(): void
    {
        $milestone = $this->milestone();
        $other = $milestone->replicate();
        $other->save();
        $data = [
            'purchase_order_id' => $milestone->purchase_order_id, 'amount' => 100, 'currency' => 'MXN',
            'milestone_ids' => [$milestone->id, $other->id],
        ];
        $this->actingAs($this->user())->post(route('invoices.store'), $data)->assertSessionHasErrors('milestone_ids');
        $this->assertSame(0, PurchaseOrderInvoice::count());

        $this->post(route('invoices.store'), array_replace($data, ['milestone_ids' => [$milestone->id]]))->assertSessionHasNoErrors();
        $this->assertSame(1, PurchaseOrderInvoice::count());
        $this->post(route('invoices.store'), array_replace($data, ['milestone_ids' => [$milestone->id]]))->assertSessionHasErrors('milestone_ids');
        $this->assertSame(1, PurchaseOrderInvoice::count());
    }

    public function test_acceptance_rejects_multiple_links_and_a_second_invoice(): void
    {
        $milestone = $this->milestone();
        $other = $milestone->replicate();
        $other->save();
        $invoice = $this->invoice($milestone);
        $secondInvoice = $this->invoice($milestone);
        $this->actingAs($this->user())->patch(route('invoices.status.update', $invoice), [
            'status' => 'aceptada', 'milestone_ids' => [$milestone->id, $other->id],
        ])->assertSessionHasErrors('milestone_ids');
        $this->assertSame('en_proceso', $invoice->fresh()->status);
        $this->patch(route('invoices.status.update', $invoice), [
            'status' => 'aceptada', 'milestone_ids' => [$milestone->id],
        ])->assertSessionHasNoErrors();
        $this->patch(route('invoices.status.update', $secondInvoice), [
            'status' => 'aceptada', 'milestone_ids' => [$milestone->id],
        ])->assertSessionHasErrors('milestone_ids');
        $this->assertSame('en_proceso', $secondInvoice->fresh()->status);
        $this->assertSame(0, $secondInvoice->milestones()->count());
        $this->patch(route('invoices.status.update', $invoice), [
            'status' => 'aceptada', 'milestone_ids' => [$milestone->id],
        ])->assertSessionHasNoErrors();
    }

    public function test_milestone_from_another_order_is_rejected(): void
    {
        $milestone = $this->milestone();
        $other = $this->milestone();
        $invoice = $this->invoice($milestone);
        $this->actingAs($this->user())->patch(route('invoices.status.update', $invoice), [
            'status' => 'aceptada', 'milestone_ids' => [$other->id],
        ])->assertSessionHasErrors('milestone_ids');
        $this->assertSame(0, $invoice->milestones()->count());
    }

    public function test_milestone_json_marks_occupied_milestones_for_the_upload_form(): void
    {
        $milestone = $this->milestone();
        $this->invoice($milestone)->milestones()->attach($milestone);
        $this->actingAs($this->user())->getJson(route('milestones.for_order', $milestone->purchaseOrder))
            ->assertOk()->assertJsonPath('0.invoice_linked', true);
    }
}
