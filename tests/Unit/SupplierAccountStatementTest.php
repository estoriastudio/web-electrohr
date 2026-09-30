<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use App\Services\SupplierAccountStatementService;
use Tests\TestCase;

class SupplierAccountStatementTest extends TestCase
{
    private function order(array $invoices, array $payments): PurchaseOrder
    {
        $order = new PurchaseOrder(['amount' => 1000000, 'currency' => 'MXN']);
        $order->id = 1;
        $milestone = new PurchaseOrderMilestone(['purchase_order_id' => 1]);
        $milestone->id = 1;
        $milestone->setRelation('payments', collect($payments)->map(function ($data) {
            $payment = new Payment(array_merge(['milestone_id' => 1], $data));
            $payment->id = $data['id'];
            return $payment;
        }));
        $order->setRelation('milestones', collect([$milestone]));
        $order->setRelation('invoices', collect($invoices)->map(function ($data) use ($milestone) {
            $invoice = new PurchaseOrderInvoice(array_merge(['status' => 'aceptada', 'currency' => 'MXN'], $data));
            $invoice->id = $data['id'];
            $invoice->setRelation('milestones', collect([$milestone]));
            return $invoice;
        }));
        foreach (['supplier', 'buyer', 'projectRelation'] as $relation) {
            $order->setRelation($relation, null);
        }
        return $order;
    }

    public function test_only_paid_status_reduces_the_invoice_balance(): void
    {
        $service = new SupplierAccountStatementService();
        foreach (['por_autorizar', 'pospuesto', 'autorizado', 'pagado'] as $status) {
            $order = $this->order([['id' => 1, 'amount' => 600000]], [
                ['id' => 1, 'amount' => 400000, 'status' => $status, 'spei_receipt_path' => 'receipt.pdf'],
            ]);
            $result = $service->calculateOrder($order);
            $paid = $status === 'pagado' ? 40000000 : 0;
            $this->assertSame($paid, $result['economic']['paid']);
            $this->assertSame(60000000 - $paid, $result['rows'][0]['balance']);
        }
    }

    public function test_regularization_preserves_the_original_payment_without_writing(): void
    {
        $service = new SupplierAccountStatementService();
        $order = $this->order([], [['id' => 7, 'amount' => 150000, 'status' => 'pagado']]);
        $before = $service->calculateOrder($order);
        $this->assertSame('sin_factura', $before['rows'][0]['status']);
        $this->assertSame(15000000, $before['rows'][0]['unregularized']);

        $invoice = new PurchaseOrderInvoice(['status' => 'aceptada', 'amount' => 150000, 'currency' => 'MXN']);
        $invoice->id = 9;
        $invoice->setRelation('milestones', $order->milestones);
        $order->setRelation('invoices', collect([$invoice]));
        $after = $service->calculateOrder($order);
        $this->assertSame($before['economic']['paid'], $after['economic']['paid']);
        $this->assertSame('pagado', $after['rows'][0]['status']);
        $this->assertCount(1, $after['rows']);
        $this->assertSame(7, $order->milestones[0]->payments[0]->id);
        $this->assertSame('pagado', $order->milestones[0]->payments[0]->status);
    }

    public function test_multiple_invoices_are_not_assumed_paid_without_explicit_amounts(): void
    {
        $service = new SupplierAccountStatementService();
        $order = $this->order([['id' => 1, 'amount' => 300], ['id' => 2, 'amount' => 300]], [
            ['id' => 1, 'amount' => 400, 'status' => 'pagado'],
        ]);
        $result = $service->calculateOrder($order);
        $this->assertNull($result['economic']['pending_invoices']);
        $this->assertSame(40000, $result['rows']->sum('paid'));
        $this->assertNull($result['rows'][0]['balance']);

        $resolved = $service->calculateOrder($order, [
            ['payment_id' => 1, 'purchase_order_invoice_id' => 1, 'amount' => 300],
            ['payment_id' => 1, 'purchase_order_invoice_id' => 2, 'amount' => 100],
        ]);
        $this->assertSame(20000, $resolved['economic']['pending_invoices']);
        $this->assertSame('pagado', $resolved['rows'][0]['status']);
        $this->assertSame(20000, $resolved['rows'][1]['balance']);
        $this->assertSame(40000, $resolved['rows']->sum('paid'));
    }

    public function test_due_today_is_pending_and_credit_notes_reduce_the_net_balance(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
        $service = new SupplierAccountStatementService();
        $order = $this->order([['id' => 1, 'amount' => 100, 'credit_note_amount' => 20, 'due_date' => '2026-09-30']], []);
        $result = $service->calculateOrder($order);
        $this->assertSame('pendiente', $result['rows'][0]['status']);
        $this->assertSame(8000, $result['rows'][0]['balance']);
        $order->invoices[0]->due_date = '2026-09-29';
        $this->assertSame('vencido', $service->calculateOrder($order)['rows'][0]['status']);
        $this->travelBack();
    }

    public function test_explicit_partial_application_is_not_silently_increased(): void
    {
        $service = new SupplierAccountStatementService();
        $order = $this->order([['id' => 1, 'amount' => 600]], [['id' => 1, 'amount' => 400, 'status' => 'pagado']]);
        $result = $service->calculateOrder($order, [
            ['payment_id' => 1, 'purchase_order_invoice_id' => 1, 'amount' => 200],
        ]);
        $this->assertSame(40000, $result['rows'][0]['balance']);
        $this->assertSame(20000, $result['rows'][1]['unregularized']);
        $this->assertSame(40000, $result['rows']->sum('paid'));
    }

    public function test_rejected_invoice_application_does_not_move_to_another_invoice(): void
    {
        $service = new SupplierAccountStatementService();
        $order = $this->order([['id' => 1, 'amount' => 600], ['id' => 2, 'amount' => 100, 'status' => 'rechazada']], [
            ['id' => 1, 'amount' => 400, 'status' => 'pagado'],
        ]);
        $result = $service->calculateOrder($order, [
            ['payment_id' => 1, 'purchase_order_invoice_id' => 1, 'amount' => 300],
            ['payment_id' => 1, 'purchase_order_invoice_id' => 2, 'amount' => 100],
        ]);
        $this->assertSame(30000, $result['rows'][0]['balance']);
        $this->assertSame(10000, $result['rows'][1]['unregularized']);
        $this->assertSame(40000, $result['rows']->sum('paid'));
    }

    public function test_paid_amount_exceeding_documented_invoice_remains_without_invoice(): void
    {
        $service = new SupplierAccountStatementService();
        $order = $this->order([['id' => 1, 'amount' => 100]], [['id' => 1, 'amount' => 150, 'status' => 'pagado']]);
        $result = $service->calculateOrder($order);
        $this->assertSame(0, $result['economic']['pending_invoices']);
        $this->assertSame(5000, $result['rows'][1]['unregularized']);
        $this->assertSame(15000, $result['rows']->sum('paid'));
    }

    public function test_different_currencies_are_not_added_in_the_order_economic_summary(): void
    {
        $service = new SupplierAccountStatementService();
        $order = $this->order([['id' => 1, 'amount' => 100, 'currency' => 'USD']], [
            ['id' => 1, 'amount' => 100, 'status' => 'pagado'],
        ]);
        $result = $service->calculateOrder($order);
        $this->assertNull($result['economic']['invoiced']);
        $this->assertNull($result['economic']['pending_invoices']);
        $this->assertSame('conciliacion', $result['rows'][1]['status']);
        $summary = $service->summarize($result['rows']);
        $this->assertSame(10000, $summary['USD']['invoiced']);
        $this->assertSame(10000, $summary['MXN']['paid']);
    }

    public function test_order_examples_distinguish_pending_contract_from_unpaid_invoices(): void
    {
        $service = new SupplierAccountStatementService();
        $order = $this->order([['id' => 1, 'amount' => 600000]], [
            ['id' => 1, 'amount' => 400000, 'status' => 'pagado'],
        ]);
        $exampleA = $service->calculateOrder($order)['economic'];
        $this->assertSame(40000000, $exampleA['pending_invoice']);
        $this->assertSame(60000000, $exampleA['pending_order']);
        $this->assertSame(20000000, $exampleA['pending_invoices']);

        $advanceMilestone = new PurchaseOrderMilestone(['purchase_order_id' => 1]);
        $advanceMilestone->id = 2;
        $advance = new Payment(['milestone_id' => 2, 'amount' => 150000, 'status' => 'pagado']);
        $advance->id = 2;
        $advanceMilestone->setRelation('payments', collect([$advance]));
        $order->milestones->push($advanceMilestone);
        $exampleB = $service->calculateOrder($order);
        $this->assertSame(55000000, $exampleB['economic']['paid']);
        $this->assertSame(45000000, $exampleB['economic']['pending_order']);
        $this->assertSame(20000000, $exampleB['economic']['pending_invoices']);
        $this->assertSame(15000000, $exampleB['rows']->sum('unregularized'));

        $laterInvoice = new PurchaseOrderInvoice(['amount' => 150000, 'status' => 'aceptada', 'currency' => 'MXN']);
        $laterInvoice->id = 2;
        $laterInvoice->setRelation('milestones', collect([$advanceMilestone]));
        $order->invoices->push($laterInvoice);
        $regularized = $service->calculateOrder($order);
        $this->assertSame(55000000, $regularized['economic']['paid']);
        $this->assertSame(20000000, $regularized['economic']['pending_invoices']);
        $this->assertSame(0, $regularized['rows']->sum('unregularized'));
        $this->assertSame(2, $order->milestones->sum(fn ($milestone) => $milestone->payments->count()));
    }
}