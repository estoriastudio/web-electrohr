<?php

use App\Models\Payment;
use App\Models\PurchaseOrderMilestone;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payments:backfill-from-milestones {--dry-run : Simula sin guardar cambios} {--chunk=200 : Tamaño de lote}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $chunkSize = max((int) $this->option('chunk'), 1);

    $created = 0;
    $skippedNoDueDate = 0;
    $processed = 0;

    $baseQuery = PurchaseOrderMilestone::query()
        ->whereHas('purchaseOrder')
        ->doesntHave('payments')
        ->orderBy('id');

    $totalCandidates = (clone $baseQuery)->count();

    $this->info('Iniciando backfill de pagos desde hitos...');
    $this->line('Candidatos sin pago vinculado: ' . $totalCandidates);
    if ($dryRun) {
        $this->warn('MODO DRY-RUN: no se guardarán cambios.');
    }

    $baseQuery->chunkById($chunkSize, function ($milestones) use (&$created, &$processed, &$skippedNoDueDate, $dryRun): void {
        foreach ($milestones as $milestone) {
            $processed++;

            if (!$milestone->due_date) {
                $skippedNoDueDate++;
                $this->warn("Hito #{$milestone->id} omitido: no tiene due_date.");
                continue;
            }

            if ($dryRun) {
                $created++;
                continue;
            }

            Payment::create([
                'milestone_id'     => $milestone->id,
                'folio'            => generateUniquePaymentFolio(),
                'amount'           => $milestone->effective_amount,
                'payment_date'     => $milestone->due_date,
                'invoice_date'     => null,
                'status'           => 'por_autorizar',
                'reference_number' => null,
            ]);

            $created++;
        }
    });

    $this->newLine();
    $this->info('Backfill finalizado.');
    $this->line("Procesados: {$processed}");
    $this->line("Pagos " . ($dryRun ? 'a crear' : 'creados') . ": {$created}");
    $this->line("Omitidos por falta de due_date: {$skippedNoDueDate}");
})->purpose('Genera pagos faltantes para hitos existentes sin pago vinculado');

Artisan::command('purchase-orders:health', function () {
    $statusRanks = [
        'por_autorizar' => 1,
        'pospuesto' => 1,
        'autorizado' => 2,
        'pagado' => 3,
    ];
    $statusAlerts = 0;
    $dateAlerts = 0;
    $rejectedPaymentAlerts = 0;
    $milestonesWithoutPayment = 0;
    $ordersWithAlerts = [];
    $statusAlertFolios = [];
    $rejectedPaymentFolios = [];
    $dateAlertFolios = [];

    $purchaseOrders = \App\Models\PurchaseOrder::query()
        ->active()
        ->whereHas('milestones')
        ->with([
            'milestones' => fn ($query) => $query->orderBy('id')->with([
                'payments' => fn ($query) => $query->orderBy('id'),
            ]),
        ])
        ->orderBy('folio')
        ->orderBy('id')
        ->get();

    $this->info('Reporte de salud de OCs');
    $this->line('OCs activas con hitos inspeccionadas: ' . $purchaseOrders->count());
    $this->line('Criterios del reporte:');
    $this->line('- Alcance: cada OC activa se analiza por separado; solo se comparan hitos de la misma OC y pagos del mismo hito.');
    $this->line('- Orden de comparación: hitos y pagos por ID ascendente; se usa el pago de menor ID de cada hito.');
    $this->line('- Alertas de estatus: un pago inicial de un hito posterior está más avanzado que el de un hito anterior.');
    $this->line('- Pagos rechazados: el primer pago de un hito está rechazado y requiere revisión.');
    $this->line('- Alertas de fechas: la fecha de pago inicial de un hito posterior es anterior a una fecha previa.');
    $this->line('- Hitos omitidos sin pagos: no tienen pago inicial para comparar y no generan alerta en esta revisión.');
    $this->newLine();

    foreach ($purchaseOrders as $purchaseOrder) {
        $firstPayments = [];
        $milestones = $purchaseOrder->milestones
            ->groupBy('purchase_order_id')
            ->get($purchaseOrder->id, collect())
            ->values();
        $paymentsByMilestone = $milestones
            ->flatMap(fn ($milestone) => $milestone->payments)
            ->groupBy('milestone_id');

        foreach ($milestones as $position => $milestone) {
            $payment = $paymentsByMilestone->get($milestone->id, collect())->first();

            if (!$payment) {
                $milestonesWithoutPayment++;
                continue;
            }

            $firstPayments[] = [
                'milestone' => $milestone,
                'position' => $position + 1,
                'payment' => $payment,
            ];
        }

        $reportedStatusPairs = [];
        foreach ($firstPayments as $currentIndex => $current) {
            $currentPayment = $current['payment'];

            if ($currentPayment->status === 'rechazado') {
                $rejectedPaymentAlerts++;
                $ordersWithAlerts[$purchaseOrder->id] = true;
                $rejectedPaymentFolios[$purchaseOrder->id] = $purchaseOrder->folio;
                $this->warn("ALERTA [estatus] OC #{$purchaseOrder->folio}: Hito {$current['position']} (pago #{$currentPayment->folio}) está rechazado y requiere revisión.");
                continue;
            }

            $currentRank = $statusRanks[$currentPayment->status] ?? null;
            if ($currentRank === null) {
                continue;
            }

            foreach (array_slice($firstPayments, 0, $currentIndex) as $previous) {
                $previousPayment = $previous['payment'];
                $previousRank = $statusRanks[$previousPayment->status] ?? null;

                if ($previousRank === null || $currentRank <= $previousRank) {
                    continue;
                }

                $pairKey = $previous['milestone']->id . ':' . $current['milestone']->id;
                if (isset($reportedStatusPairs[$pairKey])) {
                    continue;
                }

                $reportedStatusPairs[$pairKey] = true;
                $statusAlerts++;
                $ordersWithAlerts[$purchaseOrder->id] = true;
                $statusAlertFolios[$purchaseOrder->id] = $purchaseOrder->folio;
                $this->warn("ALERTA [estatus] OC #{$purchaseOrder->folio}: Hito {$current['position']} (pago #{$currentPayment->folio}, {$currentPayment->status}) está más avanzado que Hito {$previous['position']} (pago #{$previousPayment->folio}, {$previousPayment->status}).");
            }
        }

        $latestComparablePayment = null;
        foreach ($firstPayments as $current) {
            $currentPayment = $current['payment'];

            if (!$currentPayment->payment_date) {
                continue;
            }

            if ($latestComparablePayment !== null
                && $currentPayment->payment_date->lt($latestComparablePayment['payment']->payment_date)) {
                $dateAlerts++;
                $ordersWithAlerts[$purchaseOrder->id] = true;
                $dateAlertFolios[$purchaseOrder->id] = $purchaseOrder->folio;
                $previousPayment = $latestComparablePayment['payment'];
                $this->warn("ALERTA [fecha] OC #{$purchaseOrder->folio}: Hito {$current['position']} (pago #{$currentPayment->folio}, {$currentPayment->payment_date->format('Y-m-d')}) tiene fecha anterior a Hito {$latestComparablePayment['position']} (pago #{$previousPayment->folio}, {$previousPayment->payment_date->format('Y-m-d')}).");
            }

            if ($latestComparablePayment === null
                || $currentPayment->payment_date->gt($latestComparablePayment['payment']->payment_date)) {
                $latestComparablePayment = $current;
            }
        }
    }

    $this->newLine();
    if ($ordersWithAlerts === []) {
        $this->info('Sin alertas de secuencia de estatus ni fechas de pago.');
    }
    $this->line('OCs con alertas: ' . count($ordersWithAlerts));
    $this->line('Alertas de estatus: ' . $statusAlerts);
    $this->line('Folios con alertas de estatus: ' . (empty($statusAlertFolios) ? 'ninguno' : implode(', ', $statusAlertFolios)));
    $this->line('Alertas de pagos rechazados: ' . $rejectedPaymentAlerts);
    $this->line('Folios con pagos rechazados: ' . (empty($rejectedPaymentFolios) ? 'ninguno' : implode(', ', $rejectedPaymentFolios)));
    $this->line('Alertas de fechas: ' . $dateAlerts);
    $this->line('Folios con alertas de fechas: ' . (empty($dateAlertFolios) ? 'ninguno' : implode(', ', $dateAlertFolios)));
    $this->line('Hitos omitidos sin pagos: ' . $milestonesWithoutPayment);
})->purpose('Reporta discrepancias de estatus y fechas entre pagos iniciales de hitos de OCs activas');

if (!function_exists('generateUniquePaymentFolio')) {
    function generateUniquePaymentFolio(): string
    {
        do {
            $folio = strtoupper('PAY-' . random_int(10000, 99999));
        } while (Payment::where('folio', $folio)->exists());

        return $folio;
    }
}
