<?php

namespace App\Http\Controllers;

use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\Worker;
use App\Services\NotificationService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollGenerationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollLineController extends Controller
{
    public function __construct(
        private NotificationService $notification,
        private PayrollGenerationService $generation,
        private PayrollCalculationService $calculation,
    ) {}

    public function index(Request $request): View
    {
        $periodId = $request->input('payroll_period_id', '');
        $payrollLines = PayrollLine::query()
            ->with(['worker', 'projectWork', 'workerGroup', 'positionCategory'])
            ->when($periodId, fn ($query) => $query->where('payroll_period_id', $periodId))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
        $payrollPeriods = PayrollPeriod::query()->orderByDesc('year')->orderByDesc('week_number')->get();

        return view('human_resources.payroll-lines.index', compact('payrollLines', 'payrollPeriods', 'periodId'));
    }

    public function show(PayrollLine $payrollLine): View
    {
        $payrollLine->load([
            'payrollPeriod', 'worker', 'projectWork', 'workerGroup', 'positionCategory',
            'extraPayments.incentive', 'attendances.workerGroup',
        ]);

        return view('human_resources.payroll-lines.show', compact('payrollLine'));
    }

    public function generate(Request $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $request->validate(['worker_ids' => 'nullable|array', 'worker_ids.*' => 'integer|exists:workers,id']);
        $workers = Worker::query()
            ->where('status', 'active')
            ->where('payment_type', 'salaried')
            ->when($request->filled('worker_ids'), fn ($query) => $query->whereIn('id', $request->input('worker_ids')))
            ->orderBy('last_name')
            ->get();
        $generated = 0;
        $errors = [];

        foreach ($workers as $worker) {
            try {
                $this->generation->generateLine($payrollPeriod, $worker);
                $generated++;
            } catch (DomainException $exception) {
                $errors[] = "{$worker->first_name} {$worker->last_name}: {$exception->getMessage()}";
            }
        }

        $this->notification->send([
            'type' => 'PayrollLine',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $payrollPeriod->id,
            'data' => "generó {$generated} línea(s) del periodo {$payrollPeriod->week_number}/{$payrollPeriod->year}.",
        ]);

        $message = "Se generaron {$generated} línea(s) de nómina.";
        if ($errors !== []) {
            $message .= ' Pendientes: '.implode(' | ', array_slice($errors, 0, 5));
        }

        return redirect()->route('human_resources.payroll-lines.index', ['payroll_period_id' => $payrollPeriod->id])
            ->with($errors === [] ? 'success' : 'error', $message);
    }

    public function update(Request $request, PayrollLine $payrollLine): RedirectResponse
    {
        $this->ensureOpen($payrollLine);
        $data = $request->validate([
            'lost_material_amount' => 'required|numeric|min:0',
            'loan_amount' => 'required|numeric|min:0',
            'infonavit_amount' => 'required|numeric|min:0',
            'savings_fund_amount' => 'required|numeric|min:0',
            'extras_amount' => 'required|numeric|min:0',
            'fiscal_amount' => 'required|numeric|min:0',
        ]);
        $payrollLine->update($data);
        $this->calculation->recalculate($payrollLine);
        $this->notify($payrollLine, 'update', "actualizó los ajustes de nómina de {$payrollLine->worker->first_name} {$payrollLine->worker->last_name}.");

        return redirect()->route('human_resources.payroll-lines.show', $payrollLine)
            ->with('success', 'Ajustes de nómina actualizados correctamente.');
    }

    public function rebuild(PayrollLine $payrollLine): RedirectResponse
    {
        try {
            $line = $this->generation->rebuildLine($payrollLine);
        } catch (DomainException $exception) {
            return redirect()->route('human_resources.payroll-lines.show', $payrollLine)
                ->with('error', $exception->getMessage());
        }

        $this->notify($line, 'update', "reconstruyó la nómina de {$line->worker->first_name} {$line->worker->last_name} desde asistencias.");

        return redirect()->route('human_resources.payroll-lines.show', $line)
            ->with('success', 'Línea de nómina reconstruida correctamente.');
    }

    private function ensureOpen(PayrollLine $payrollLine): void
    {
        $payrollLine->loadMissing('payrollPeriod');

        if ($payrollLine->payrollPeriod->status !== 'open') {
            throw ValidationException::withMessages(['payroll_line' => 'Solo se pueden modificar líneas de periodos abiertos.']);
        }
    }

    private function notify(PayrollLine $payrollLine, string $action, string $data): void
    {
        $this->notification->send([
            'type' => 'PayrollLine',
            'action_by' => Auth::id(),
            'model_action' => $action,
            'model_id' => $payrollLine->id,
            'data' => $data,
        ]);
    }
}