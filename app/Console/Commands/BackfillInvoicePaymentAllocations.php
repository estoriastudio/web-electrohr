<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use App\Services\InvoicePaymentAllocationService;
use App\Services\SupplierAccountStatementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillInvoicePaymentAllocations extends Command
{
    protected $signature = 'purchase-orders:backfill-invoice-payments {--dry-run : Simular sin guardar relaciones}';

    protected $description = 'Conserva relaciones monetarias historicas inequivocas entre facturas y pagos realizados';

    public function handle(SupplierAccountStatementService $statement, InvoicePaymentAllocationService $allocations): int
    {
        $candidates = 0;
        $unresolved = 0;
        foreach (PurchaseOrder::query()->select('id')->lazyById(100) as $reference) {
            DB::transaction(function () use ($reference, $statement, $allocations, &$candidates, &$unresolved) {
                $order = $statement->orderQuery()->whereKey($reference->id)->lockForUpdate()->first();
                if (!$order) {
                    return;
                }
                $existing = $order->invoices->flatMap(fn ($invoice) => $invoice->paymentAllocations);
                $result = $statement->calculateOrder($order, $existing->toArray());
                $payments = $order->milestones->flatMap(fn ($milestone) => $milestone->payments)->keyBy('id');
                $byInvoice = [];
                foreach ($result['applications'] as $application) {
                    $paymentId = $application['payment_id'];
                    if ($existing->contains('payment_id', $paymentId)
                        || (int) round($application['amount'] * 100) !== (int) round((float) $payments[$paymentId]->amount * 100)) {
                        continue;
                    }
                    $byInvoice[$application['purchase_order_invoice_id']][$paymentId] = $application['amount'];
                }
                foreach ($byInvoice as $invoiceId => $amounts) {
                    $invoice = $order->invoices->firstWhere('id', $invoiceId);
                    $candidates += count($amounts);
                    if (!$this->option('dry-run')) {
                        $allocations->validateAndReplace(
                            $invoice, $invoice->milestones->pluck('id')->map(fn ($id) => (int) $id)->all(),
                            array_replace($invoice->paymentAllocations->pluck('amount', 'payment_id')->all(), $amounts), null,
                        );
                    }
                }
                if ($result['rows']->contains('status', 'conciliacion')) {
                    $unresolved++;
                    $this->line('Por conciliar: ' . ($order->folio ?: $order->id));
                }
            });
        }
        $this->info(($this->option('dry-run') ? 'Simulacion: ' : 'Registradas: ') . $candidates . ' relaciones inequivocas.');
        $this->line('OC por conciliar: ' . $unresolved . '. No se modificaron pagos, hitos ni comprobantes.');
        return self::SUCCESS;
    }
}