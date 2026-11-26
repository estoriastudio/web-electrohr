<?php

use App\Models\Payment;
use App\Models\PurchaseOrderMilestone;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('purchase-orders:backfill-buyers {--dry-run : Simula sin guardar cambios} {--chunk=200 : Tamaño de lote}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $chunkSize = max(1, (int) $this->option('chunk'));
    $usersByName = User::with('roles')->get(['id', 'name'])->groupBy(fn ($user) => trim($user->name));
    $assigned = 0;
    $skipped = 0;
    $processed = 0;

    if ($dryRun) {
        $this->warn('MODO DRY-RUN: no se guardarán cambios.');
    }

    PurchaseOrder::withTrashed()->whereNull('buyer_id')->chunkById($chunkSize, function ($orders) use ($usersByName, $dryRun, &$assigned, &$skipped, &$processed) {
        foreach ($orders as $order) {
            $processed++;
            $name = trim((string) $order->elaborated_by);
            $matches = $name !== '' ? $usersByName->get($name, collect()) : collect();
            $reason = match (true) {
                $name === '' => 'nombre vacío',
                $matches->isEmpty() => 'sin coincidencia exacta',
                $matches->count() !== 1 => 'nombre ambiguo',
                !$matches->first()->hasAnyRole(['admin', 'Orden de compra']) => 'usuario sin rol de comprador',
                default => null,
            };

            if ($reason !== null) {
                $skipped++;
                $this->warn('OC #' . ($order->folio ?? $order->id) . ' omitida: ' . $reason . '.');
                continue;
            }

            $buyer = $matches->first();
            $updated = $dryRun ? 1 : DB::table('purchase_orders')
                ->where('id', $order->id)
                ->whereNull('buyer_id')
                ->update(['buyer_id' => $buyer->id]);
            $assigned += $updated;
        }
    });

    $this->info('Vinculación de compradores finalizada.');
    $this->line('Procesadas: ' . $processed);
    $this->line(($dryRun ? 'Por vincular: ' : 'Vinculadas: ') . $assigned);
    $this->line('Omitidas: ' . $skipped);
})->purpose('Vincula compradores de OCs históricas por nombres exactos y únicos');

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

            $created += DB::transaction(function () use ($milestone): int {
                PurchaseOrder::query()->lockForUpdate()->findOrFail($milestone->purchase_order_id)->ensureOpen();
                $lockedMilestone = PurchaseOrderMilestone::query()->lockForUpdate()->findOrFail($milestone->id);
                if ($lockedMilestone->payments()->exists()) {
                    return 0;
                }
                Payment::create([
                    'milestone_id' => $lockedMilestone->id,
                    'folio' => generateUniquePaymentFolio(),
                    'amount' => $lockedMilestone->effective_amount,
                    'payment_date' => $lockedMilestone->due_date,
                    'invoice_date' => null,
                    'status' => 'por_autorizar',
                    'reference_number' => null,
                    'origin' => Payment::ORIGIN_INITIAL,
                ]);

                return 1;
            });
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
    $orderRuleAlerts = 0;
    $orderRuleFolios = [];
    $ordersWithAlerts = [];
    $statusAlertFolios = [];
    $rejectedPaymentFolios = [];
    $dateAlertFolios = [];

    $purchaseOrders = \App\Models\PurchaseOrder::query()
        ->active()
        ->whereHas('milestones')
        ->with([
            'items',
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
    $this->line('- Orden de comparación: hitos y pagos por ID ascendente; se usa el pago de menor ID de cada hito que no esté sustituido ni sea un abono.');
    $this->line('- Alertas de estatus: un pago inicial de un hito posterior está más avanzado que el de un hito anterior.');
    $this->line('- Pagos rechazados: el primer pago de un hito está rechazado y requiere revisión.');
    $this->line('- Alertas de fechas: la fecha de pago inicial de un hito posterior es anterior a una fecha previa; se excluyen hitos con plazo de crédito propio.');
    $this->line('- OCs atemporales: se excluyen sus alertas de orden, estatus y fechas; los hallazgos no secuenciales siguen incluidos.');
    $this->line('- Hitos omitidos sin pagos: no tienen pago inicial para comparar y no generan alerta en esta revisión.');
    $this->line('- Bloqueadas por orden de hitos: un hito tiene pagos por autorizar o pagar, pero el hito anterior no tiene pagos pendientes y no está cubierto; hay que regularizarla en la base de datos.');
    $this->newLine();

    foreach ($purchaseOrders as $purchaseOrder) {
        $firstPayments = [];
        $milestones = $purchaseOrder->milestones
            ->groupBy('purchase_order_id')
            ->get($purchaseOrder->id, collect())
            ->values();
        $paymentsByMilestone = $milestones
            ->flatMap(fn ($milestone) => $milestone->payments)
            ->where('status', '!=', Payment::STATUS_SUSTITUIDO)
            ->groupBy('milestone_id');

        foreach ($milestones as $position => $milestone) {
            $payment = $paymentsByMilestone->get($milestone->id, collect())
                ->first(fn ($candidate) => $candidate->origin !== Payment::ORIGIN_CREDIT);

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

        foreach ($milestones as $position => $milestone) {
            $milestone->setRelation('purchaseOrder', $purchaseOrder);
            if ($position === 0 || $purchaseOrder->is_atemporal) {
                continue;
            }

            $actionable = fn ($candidate) => in_array($candidate->status, ['por_autorizar', 'pospuesto', 'autorizado'], true);
            $previous = $milestones[$position - 1];
            $previous->setRelation('purchaseOrder', $purchaseOrder);

            if ($paymentsByMilestone->get($milestone->id, collect())->contains($actionable)
                && ! $paymentsByMilestone->get($previous->id, collect())->contains($actionable)
                && ! $previous->is_complete) {
                $orderRuleAlerts++;
                $ordersWithAlerts[$purchaseOrder->id] = true;
                $orderRuleFolios[$purchaseOrder->id] = $purchaseOrder->folio;
                $this->warn('ALERTA [orden] OC #'.$purchaseOrder->folio.': Hito '.($position + 1).' tiene pagos por autorizar o pagar, pero el Hito '.$position.' no tiene pagos pendientes y no está cubierto; la OC queda bloqueada.');
            }
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

            if ($purchaseOrder->is_atemporal) {
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

        if ($purchaseOrder->is_atemporal) {
            continue;
        }

        $latestComparablePayment = null;
        foreach ($firstPayments as $current) {
            $currentPayment = $current['payment'];

            if (!$currentPayment->payment_date || app(\App\Services\MilestoneCreditTerms::class)->hasTerms($current['milestone'])) {
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
    $this->line('Alertas de bloqueo por orden de hitos: ' . $orderRuleAlerts);
    $this->line('Folios bloqueados por orden de hitos: ' . (empty($orderRuleFolios) ? 'ninguno' : implode(', ', $orderRuleFolios)));
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
