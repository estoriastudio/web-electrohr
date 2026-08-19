<?php

namespace App\Http\Controllers;

use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\ProjectWork;
use App\Models\WorkerGroup;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollFieldReportController extends Controller
{
    public function index(Request $request): View
    {
        $periodId = $request->input('payroll_period_id', '');
        $projectWorkId = $request->input('project_work_id', '');
        $workerGroupId = $request->input('worker_group_id', '');
        $payrollPeriods = PayrollPeriod::query()->orderByDesc('year')->orderByDesc('week_number')->get();
        $lines = PayrollLine::query()
            ->with(['worker', 'projectWork', 'workerGroup', 'positionCategory', 'attendances'])
            ->when($periodId, fn ($query) => $query->where('payroll_period_id', $periodId))
            ->when($projectWorkId, fn ($query) => $query->where('project_work_id', $projectWorkId))
            ->when($workerGroupId, fn ($query) => $query->where('worker_group_id', $workerGroupId))
            ->orderBy('worker_id')
            ->get();
        $projectWorks = ProjectWork::orderBy('name')->get();
        $workerGroups = WorkerGroup::orderBy('name')->get();

        return view('human_resources.payroll-reports.field', compact(
            'lines', 'payrollPeriods', 'projectWorks', 'workerGroups', 'periodId', 'projectWorkId', 'workerGroupId',
        ));
    }
}