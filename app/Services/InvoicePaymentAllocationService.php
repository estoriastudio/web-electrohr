<?php

namespace App\Services;

use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoicePaymentAllocationService
{
    public function validateAndReplace(PurchaseOrderInvoice $invoice, array $milestoneIds, ?array $amounts, ?int $userId): void
    {
        DB::transaction(function () use ($invoice, $milestoneIds, $amounts, $userId) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($invoice->purchase_order_id);
            $lockedInvoice = PurchaseOrderInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $existing = InvoicePaymentAllocation::where('purchase_order_invoice_id', $invoice->id)->get();
            $submitted = $amounts ?? $existing->pluck('amount', 'payment_id')->all();
            $payments = Payment::query()->whereHas('milestone', fn ($query) => $query->where('purchase_order_id', $order->id))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $net = (int) round((float) ($lockedInvoice->net_scope ?? ((float) $lockedInvoice->amount - (float) ($lockedInvoice->credit_note_amount ?? 0))) * 100);
            $total = 0;
            $records = [];

            foreach ($submitted as $paymentId => $amount) {
                $cents = (int) round((float) $amount * 100);
                if ($cents === 0) {
                    continue;
                }
                $payment = $payments->get((int) $paymentId);
                if ($cents < 0 || !$payment || !in_array((int) $payment->milestone_id, $milestoneIds, true)
                    || ($lockedInvoice->currency ?: $order->currency) !== $order->currency) {
                    throw ValidationException::withMessages(['payment_amounts' => 'Los importes deben corresponder a pagos de los hitos seleccionados y a la misma moneda de la OC.']);
                }
                $other = (int) round((float) InvoicePaymentAllocation::where('payment_id', $payment->id)
                    ->where('purchase_order_invoice_id', '!=', $invoice->id)->sum('amount') * 100);
                if ($other + $cents > (int) round((float) $payment->amount * 100)) {
                    throw ValidationException::withMessages(['payment_amounts' => 'El importe asignado al pago #' . $payment->folio . ' excede su importe disponible.']);
                }
                $total += $cents;
                $records[$payment->id] = $cents;
            }
            if ($total > $net) {
                throw ValidationException::withMessages(['payment_amounts' => 'Los importes asignados exceden el alcance liquido de la factura.']);
            }
            if ($amounts === null) {
                return;
            }
            foreach ($records as $paymentId => $cents) {
                InvoicePaymentAllocation::updateOrCreate([
                    'payment_id' => $paymentId,
                    'purchase_order_invoice_id' => $invoice->id,
                ], ['amount' => $cents / 100, 'created_by' => $userId]);
            }
            $toDelete = InvoicePaymentAllocation::where('purchase_order_invoice_id', $invoice->id);
            if ($records) {
                $toDelete->whereNotIn('payment_id', array_keys($records));
            }
            $toDelete->delete();
        });
    }
}