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

if (!function_exists('generateUniquePaymentFolio')) {
    function generateUniquePaymentFolio(): string
    {
        do {
            $folio = strtoupper('PAY-' . random_int(10000, 99999));
        } while (Payment::where('folio', $folio)->exists());

        return $folio;
    }
}
