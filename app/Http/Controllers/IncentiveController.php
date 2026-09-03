<?php

namespace App\Http\Controllers;

use App\Models\Incentive;
use App\Models\Worker;
use App\Services\NotificationService;
use App\Services\WorkerIncentiveService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IncentiveController extends Controller
{
    public function __construct(
        private NotificationService $notification,
        private WorkerIncentiveService $incentiveService,
    ) {}

    public function index(Request $request): View
    {
        $workerId = $request->input('worker_id', '');
        $incentives = Incentive::query()
            ->with('worker')
            ->when($workerId, fn ($query) => $query->where('worker_id', $workerId))
            ->orderByDesc('incentive_date')
            ->paginate(25);
        $formIncentive = new Incentive;
        $workers = Worker::query()
            ->whereIn('status', ['pre_registered', 'active'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('human_resources.incentives.index', compact('incentives', 'formIncentive', 'workers', 'workerId'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('human_resources.incentives.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['status'] = 'active';

        try {
            $this->incentiveService->ensurePeriodOpen(new Incentive($data));
            $incentive = Incentive::create($data);
            $this->incentiveService->sync($incentive);
        } catch (DomainException $exception) {
            if (isset($incentive)) {
                $incentive->delete();
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }
        $this->notify($incentive, 'create', "registró un incentivo para {$incentive->worker->first_name} {$incentive->worker->last_name}.");

        return $this->redirectAfterMutation($request, $incentive)
            ->with('success', 'Incentivo creado correctamente.');
    }

    public function show(Incentive $incentive): RedirectResponse
    {
        return redirect()->route('human_resources.incentives.index');
    }

    public function edit(Incentive $incentive): RedirectResponse
    {
        return redirect()->route('human_resources.incentives.index');
    }

    public function update(Request $request, Incentive $incentive): RedirectResponse
    {
        try {
            $data = $this->validatedData($request, $incentive);
            $this->incentiveService->ensureMutable($incentive);
            $this->incentiveService->ensurePeriodOpen(new Incentive($data));
            $incentive->update($data);
            $this->incentiveService->sync($incentive);
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
        $this->notify($incentive, 'update', "actualizó un incentivo de {$incentive->worker->first_name} {$incentive->worker->last_name}.");

        return $this->redirectAfterMutation($request, $incentive)
            ->with('success', 'Incentivo actualizado correctamente.');
    }

    public function destroy(Request $request, Incentive $incentive): RedirectResponse
    {
        try {
            $this->incentiveService->remove($incentive);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->notify($incentive, 'destroy', 'eliminó un incentivo de trabajador.');

        return $this->redirectAfterMutation($request, $incentive)
            ->with('success', 'Incentivo eliminado correctamente.');
    }

    private function validatedData(Request $request, ?Incentive $incentive = null): array
    {
        $data = $request->validate([
            'worker_id' => 'required|exists:workers,id',
            'category' => ['required', Rule::in(['incentive', 'overtime', 'day_off_exchange'])],
            'rate_type' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'overtime_hours' => 'nullable|numeric|min:0.01|max:99.99',
            'overtime_hourly_rate' => 'nullable|numeric|min:0.01|max:99999.99',
            'incentive_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($data['category'] === 'overtime') {
            if (! $request->filled('overtime_hours') || ! $request->filled('overtime_hourly_rate')) {
                throw ValidationException::withMessages([
                    'overtime_hours' => 'Indica la cantidad de horas extra.',
                    'overtime_hourly_rate' => 'Indica el valor de la hora extra.',
                ]);
            }

            $data['rate_type'] = null;
        } else {
            if (! $request->filled('rate_type')) {
                throw ValidationException::withMessages(['rate_type' => 'Selecciona un tipo de incentivo.']);
            }

            if ($data['category'] === 'day_off_exchange' && $data['rate_type'] === 'D') {
                throw ValidationException::withMessages(['rate_type' => 'El tipo D no está disponible para libranza.']);
            }

            $data['overtime_hours'] = null;
            $data['overtime_hourly_rate'] = null;
        }

        $duplicate = Incentive::query()
            ->where('worker_id', $data['worker_id'])
            ->where('category', $data['category'])
            ->where('rate_type', $data['rate_type'])
            ->whereDate('incentive_date', $data['incentive_date'])
            ->when($incentive, fn ($query) => $query->whereKeyNot($incentive->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['incentive_date' => 'El trabajador ya tiene ese incentivo en la fecha indicada.']);
        }

        return $data;
    }

    private function redirectAfterMutation(Request $request, Incentive $incentive): RedirectResponse
    {
        if ($request->boolean('return_to_worker')) {
            return redirect()->route('human_resources.workers.show', $incentive->worker_id);
        }

        return redirect()->route('human_resources.incentives.index', ['worker_id' => $incentive->worker_id]);
    }

    private function notify(Incentive $incentive, string $action, string $data): void
    {
        $this->notification->send([
            'type' => 'Incentive',
            'action_by' => Auth::id(),
            'model_action' => $action,
            'model_id' => $incentive->id,
            'data' => $data,
        ]);
    }
}