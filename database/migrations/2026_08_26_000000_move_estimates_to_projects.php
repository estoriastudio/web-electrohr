<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $projectsWithMultipleCurrencies = DB::table('project_works')
            ->whereNotNull('currency')
            ->where('currency', '!=', '')
            ->select('project_id')
            ->groupBy('project_id')
            ->havingRaw('COUNT(DISTINCT currency) > 1')
            ->pluck('project_id');

        if ($projectsWithMultipleCurrencies->isNotEmpty()) {
            throw new RuntimeException(
                'No se puede definir una moneda única para los proyectos: '
                . $projectsWithMultipleCurrencies->implode(', ')
            );
        }

        $duplicateEstimateNumbers = DB::table('project_work_estimates as estimate')
            ->join('project_works as work', 'work.id', '=', 'estimate.project_work_id')
            ->select('work.project_id', 'estimate.estimate_number')
            ->groupBy('work.project_id', 'estimate.estimate_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateEstimateNumbers->isNotEmpty()) {
            throw new RuntimeException(
                'No se puede aplicar la unicidad de folio por proyecto; existen folios repetidos en el histórico.'
            );
        }

        if (! Schema::hasColumn('projects', 'currency')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('currency', 10)->default('MXN')->after('current_agreement_value');
            });
        }

        $projectCurrencies = DB::table('project_works')
            ->select('project_id', DB::raw("MAX(NULLIF(currency, '')) as currency"))
            ->groupBy('project_id')
            ->get();

        foreach ($projectCurrencies as $projectCurrency) {
            if ($projectCurrency->currency) {
                DB::table('projects')
                    ->where('id', $projectCurrency->project_id)
                    ->update(['currency' => $projectCurrency->currency]);
            }
        }

        if (! Schema::hasTable('project_estimates')) {
            Schema::create('project_estimates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')
                    ->constrained('projects')
                    ->cascadeOnDelete();
                $table->foreignId('created_by')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->string('estimate_number', 100);
                $table->date('estimate_date');
                $table->enum('type', ['estimacion', 'nota_credito', 'anticipo']);
                $table->decimal('estimate_amount', 15, 2)->default(0);
                $table->decimal('returned_retention_amount', 15, 2)->default(0);
                $table->decimal('disfp_deduction', 15, 2)->default(0);
                $table->decimal('apaee_deduction', 15, 2)->default(0);
                $table->decimal('inc_retention_amount', 15, 2)->default(0);
                $table->decimal('vat_retention_amount', 15, 2)->default(0);
                $table->decimal('advance_amortization_amount', 15, 2)->default(0);
                $table->decimal('advance_amortization_vat_amount', 15, 2)->default(0);
                $table->decimal('funeral_expense_amount', 15, 2)->default(0);
                $table->decimal('delay_penalty_amount', 15, 2)->default(0);
                $table->string('invoice_number', 100)->nullable();
                $table->date('invoice_date')->nullable();
                $table->decimal('invoice_amount', 15, 2)->nullable();
                $table->string('spei_reference', 100)->nullable();
                $table->decimal('spei_amount', 15, 2)->nullable();
                $table->date('payment_date')->nullable();
                $table->enum('status', ['pendiente', 'pagada'])->default('pendiente');
                $table->decimal('physical_progress', 5, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['project_id', 'estimate_date']);
                $table->unique(['project_id', 'estimate_number']);
            });
        }

        if (! Schema::hasTable('project_estimate_allocations')) {
            Schema::create('project_estimate_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_estimate_id')
                    ->constrained('project_estimates')
                    ->cascadeOnDelete();
                $table->foreignId('project_work_id')
                    ->constrained('project_works')
                    ->cascadeOnDelete();
                $table->decimal('estimate_amount', 15, 2);
                $table->timestamps();

                $table->unique(['project_estimate_id', 'project_work_id'], 'proj_est_alloc_est_work_unique');
                $table->index('project_work_id');
            });
        } else {
            Schema::table('project_estimate_allocations', function (Blueprint $table) {
                $table->unique(['project_estimate_id', 'project_work_id'], 'proj_est_alloc_est_work_unique');
            });
        }

        DB::table('project_work_estimates')
            ->orderBy('id')
            ->chunkById(100, function ($estimates) {
                foreach ($estimates as $estimate) {
                    $projectId = DB::table('project_works')
                        ->where('id', $estimate->project_work_id)
                        ->value('project_id');

                    if (! $projectId) {
                        throw new RuntimeException("No se encontró el proyecto de la obra {$estimate->project_work_id}.");
                    }

                    $attributes = (array) $estimate;
                    unset($attributes['project_work_id']);
                    $attributes['project_id'] = $projectId;

                    DB::table('project_estimates')->insert($attributes);
                    DB::table('project_estimate_allocations')->insert([
                        'project_estimate_id' => $estimate->id,
                        'project_work_id' => $estimate->project_work_id,
                        'estimate_amount' => $estimate->estimate_amount,
                        'created_at' => $estimate->created_at,
                        'updated_at' => $estimate->updated_at,
                    ]);
                }
            });

        Schema::drop('project_work_estimates');
    }

    public function down(): void
    {
        throw new RuntimeException('La migración de estimaciones por proyecto no se puede revertir automáticamente.');
    }
};