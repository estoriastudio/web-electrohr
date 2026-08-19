<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollPeriodController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index(): View
    {
        $payrollPeriods = PayrollPeriod::query()
            ->withCount('lines')
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->paginate(25);

        return view('human_resources.payroll-periods.index', compact('payrollPeriods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);
        $startDate = Carbon::parse($data['start_date'])->startOfDay();

        if (! $startDate->isFriday()) {
            throw ValidationException::withMessages(['start_date' => 'El periodo de nómina debe iniciar en viernes.']);
        }

        $period = PayrollPeriod::create([
            'week_number' => $startDate->isoWeek(),
            'year' => $startDate->isoWeekYear(),
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addDays(6),
            'cutoff_date' => $startDate->copy()->addDays(6),
            'status' => 'open',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->notify($period, 'create', "creó el periodo de nómina {$period->week_number}/{$period->year}.");

        return redirect()->route('human_resources.payroll-periods.index')
            ->with('success', 'Periodo de nómina creado correctamente.');
    }

    public function close(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        if ($payrollPeriod->status !== 'open') {
            return redirect()->route('human_resources.payroll-periods.index')
                ->with('error', 'Solo los periodos abiertos se pueden cerrar.');
        }

        $payrollPeriod->update(['status' => 'closed']);
        $this->notify($payrollPeriod, 'update', "cerró el periodo de nómina {$payrollPeriod->week_number}/{$payrollPeriod->year}.");

        return redirect()->route('human_resources.payroll-periods.index')
            ->with('success', 'Periodo de nómina cerrado correctamente.');
    }

    public function markPaid(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        if ($payrollPeriod->status !== 'closed') {
            return redirect()->route('human_resources.payroll-periods.index')
                ->with('error', 'Solo los periodos cerrados se pueden marcar como pagados.');
        }

        $payrollPeriod->update(['status' => 'paid']);
        $this->notify($payrollPeriod, 'update', "marcó como pagado el periodo de nómina {$payrollPeriod->week_number}/{$payrollPeriod->year}.");

        return redirect()->route('human_resources.payroll-periods.index')
            ->with('success', 'Periodo de nómina marcado como pagado.');
    }

    private function notify(PayrollPeriod $payrollPeriod, string $action, string $data): void
    {
        $this->notification->send([
            'type' => 'PayrollPeriod',
            'action_by' => Auth::id(),
            'model_action' => $action,
            'model_id' => $payrollPeriod->id,
            'data' => $data,
        ]);
    }
}