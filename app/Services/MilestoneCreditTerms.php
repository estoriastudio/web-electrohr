<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Plazo de crédito de un hito: fecha base (validación de factura o cumplimiento validado) + días pactados.
 */
class MilestoneCreditTerms
{
    public function hasTerms(PurchaseOrderMilestone $milestone): bool
    {
        return ($milestone->payment_condition ?? 'credito') === 'credito'
            && !$milestone->is_advance
            && $milestone->credit_days !== null;
    }

    /** Los hitos a crédito sin configuración (legados) usan la factura aceptada como inicio. */
    public function startsWith(PurchaseOrderMilestone $milestone): string
    {
        return $milestone->credit_start ?: PurchaseOrderMilestone::CREDIT_START_INVOICE;
    }

    public function baseDate(PurchaseOrderMilestone $milestone): ?Carbon
    {
        if ($this->startsWith($milestone) === PurchaseOrderMilestone::CREDIT_START_FULFILLMENT) {
            return $milestone->delivery_confirmed_at?->copy()->startOfDay();
        }

        $accepted = DB::table('invoice_milestone')
            ->join('purchase_order_invoices', 'purchase_order_invoices.id', '=', 'invoice_milestone.purchase_order_invoice_id')
            ->where('invoice_milestone.purchase_order_milestone_id', $milestone->id)
            ->where('purchase_order_invoices.status', PurchaseOrderInvoice::STATUS_ACEPTADA)
            ->whereNotNull('purchase_order_invoices.accepted_at')
            ->min('purchase_order_invoices.accepted_at');

        return $accepted ? Carbon::parse($accepted)->startOfDay() : null;
    }

    public function computedDueDate(PurchaseOrderMilestone $milestone): ?Carbon
    {
        if (!$this->hasTerms($milestone)) {
            return null;
        }

        return $this->baseDate($milestone)?->addDays((int) $milestone->credit_days);
    }

    /**
     * Recalcula el vencimiento cuando ya existe la fecha base y lo propaga a los pagos aún no autorizados.
     * Cada hito tiene su propio vencimiento, independiente del orden de los otros hitos.
     */
    public function apply(PurchaseOrderMilestone $milestone): ?string
    {
        DB::transaction(function () use ($milestone) {
            PurchaseOrder::query()->lockForUpdate()->findOrFail($milestone->purchase_order_id);
            $lockedMilestone = PurchaseOrderMilestone::query()->lockForUpdate()->findOrFail($milestone->id);
            $due = $this->computedDueDate($lockedMilestone);
            if (!$due) {
                return;
            }
            $payments = $lockedMilestone->payments()->lockForUpdate()->orderBy('id')->get();
            $lockedMilestone->update(['due_date' => $due->toDateString()]);
            $payments->whereIn('status', ['por_autorizar', 'pospuesto'])
                ->each(fn ($payment) => $payment->update(['payment_date' => $due->toDateString()]));
        });

        return null;
    }
}
