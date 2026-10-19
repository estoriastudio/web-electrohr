<?php

namespace App\Services;

use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plazo de crédito de un hito: fecha base (validación de factura o cumplimiento validado) + días pactados.
 */
class MilestoneCreditTerms
{
    public function __construct(private PurchaseOrderPaymentSequenceValidator $sequenceValidator) {}

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
     * Devuelve un aviso si no se pudo aplicar por romper la secuencia de fechas entre hitos.
     */
    public function apply(PurchaseOrderMilestone $milestone): ?string
    {
        $due = $this->computedDueDate($milestone);

        if (!$due || ($milestone->due_date && $milestone->due_date->isSameDay($due))) {
            return null;
        }

        $payments = $milestone->payments()->orderBy('id')->get();
        $first = $payments->first();

        try {
            if ($first?->status === 'por_autorizar') {
                $this->sequenceValidator->ensurePaymentDateSequence(
                    $milestone->purchaseOrder,
                    [$first->id => $due->toDateString()],
                    'due_date',
                );
            }
        } catch (ValidationException $e) {
            return 'No se actualizó el vencimiento del hito #' . $milestone->id . ' a ' . $due->format('d/m/Y')
                . ': ' . collect($e->errors())->flatten()->first();
        }

        DB::transaction(function () use ($milestone, $payments, $due) {
            $milestone->update(['due_date' => $due->toDateString()]);
            $payments->whereIn('status', ['por_autorizar', 'pospuesto'])
                ->each(fn ($payment) => $payment->update(['payment_date' => $due->toDateString()]));
        });

        return null;
    }
}
