<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Models\ProjectWork;
use App\Models\WorkerGroup;
use App\Services\PayrollCostReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollCostReportController extends Controller
{
    public function __construct(private PayrollCostReportService $costReport) {}

    public function byProjectWork(Request $request): View
    {
        [$payrollPeriod, $payrollPeriods] = $this->periodContext($request);
        $totals = $payrollPeriod ? $this->costReport->byProjectWork($payrollPeriod) : collect();
        $projectWorks = ProjectWork::whereIn('id', $totals->keys())->get()->keyBy('id');

        return view('human_resources.payroll-reports.by-project-work', compact('payrollPeriod', 'payrollPeriods', 'totals', 'projectWorks'));
    }

    public function byWorkerGroup(Request $request): View
    {
        [$payrollPeriod, $payrollPeriods] = $this->periodContext($request);
        $totals = $payrollPeriod ? $this->costReport->byWorkerGroup($payrollPeriod) : collect();
        $workerGroups = WorkerGroup::whereIn('id', $totals->keys())->get()->keyBy('id');

        return view('human_resources.payroll-reports.by-worker-group', compact('payrollPeriod', 'payrollPeriods', 'totals', 'workerGroups'));
    }

    private function periodContext(Request $request): array
    {
        $payrollPeriods = PayrollPeriod::query()->orderByDesc('year')->orderByDesc('week_number')->get();
        $payrollPeriod = $request->filled('payroll_period_id')
            ? $payrollPeriods->firstWhere('id', (int) $request->input('payroll_period_id'))
            : $payrollPeriods->first();

        return [$payrollPeriod, $payrollPeriods];
    }
}