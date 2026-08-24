<?php

namespace App\Services;

use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\PieceworkWeeklyEntry;
use Illuminate\Support\Collection;

class PayrollCostReportService
{
    public function byProjectWork(PayrollPeriod $payrollPeriod): Collection
    {
        return $this->mergeTotals(
            PayrollLine::query()
                ->where('payroll_period_id', $payrollPeriod->id)
                ->selectRaw('project_work_id, SUM(total_amount) as total')
                ->groupBy('project_work_id')
                ->pluck('total', 'project_work_id'),
            PieceworkWeeklyEntry::query()
                ->where('week_number', $payrollPeriod->week_number)
                ->where('year', $payrollPeriod->year)
                ->selectRaw('project_work_id, SUM(total_amount) as total')
                ->groupBy('project_work_id')
                ->pluck('total', 'project_work_id'),
        );
    }

    public function byWorkerGroup(PayrollPeriod $payrollPeriod): Collection
    {
        return $this->mergeTotals(
            PayrollLine::query()
                ->where('payroll_period_id', $payrollPeriod->id)
                ->whereNotNull('worker_group_id')
                ->selectRaw('worker_group_id, SUM(total_amount) as total')
                ->groupBy('worker_group_id')
                ->pluck('total', 'worker_group_id'),
            PieceworkWeeklyEntry::query()
                ->where('week_number', $payrollPeriod->week_number)
                ->where('year', $payrollPeriod->year)
                ->whereNotNull('worker_group_id')
                ->selectRaw('worker_group_id, SUM(total_amount) as total')
                ->groupBy('worker_group_id')
                ->pluck('total', 'worker_group_id'),
        );
    }

    private function mergeTotals(Collection $salaried, Collection $piecework): Collection
    {
        return $salaried->keys()
            ->merge($piecework->keys())
            ->unique()
            ->mapWithKeys(fn ($id) => [$id => round((float) ($salaried[$id] ?? 0) + (float) ($piecework[$id] ?? 0), 2)])
            ->sortKeys();
    }
}