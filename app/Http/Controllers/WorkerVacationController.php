<?php

namespace App\Http\Controllers;

use App\Models\ProjectWork;
use App\Models\Worker;
use App\Models\WorkerVacation;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WorkerVacationController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index(Request $request): View
    {
        $today = Carbon::today();
        $summary = [
            'in_progress' => WorkerVacation::where('status', 'in_progress')->count(),
            'approved' => WorkerVacation::where('status', 'approved')->count(),
            'taken' => WorkerVacation::where('status', 'taken')->count(),
            'cancelled' => WorkerVacation::where('status', 'cancelled')->count(),
            'away_today' => WorkerVacation::query()
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->distinct('worker_id')
                ->count('worker_id'),
        ];
        $upcomingVacations = WorkerVacation::query()
            ->with(['worker.projectWork'])
            ->whereIn('status', ['in_progress', 'approved'])
            ->whereDate('end_date', '>=', $today)
            ->orderBy('start_date')
            ->limit(12)
            ->get();

        return view('human_resources.vacations.index', compact('summary', 'upcomingVacations', 'today'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('human_resources.worker-vacations.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['status'] = 'in_progress';

        $vacation = WorkerVacation::create($data);

        $this->notify($vacation, 'create', "registró la solicitud de vacaciones de {$vacation->worker->first_name} {$vacation->worker->last_name}.");

        if ($request->boolean('return_to_worker')) {
            return redirect()->route('human_resources.workers.show', $vacation->worker_id)
                ->with('success', 'Solicitud de vacaciones creada correctamente.');
        }

        return redirect()->route('human_resources.worker-vacations.index')
            ->with('success', 'Solicitud de vacaciones creada correctamente.');
    }

    public function show(WorkerVacation $workerVacation): View
    {
        $workerVacation->load('worker.projectWork');

        return view('human_resources.vacations.show', compact('workerVacation'));
    }

    public function edit(WorkerVacation $workerVacation): View
    {
        $workers = Worker::whereIn('status', ['pre_registered', 'active'])->orderBy('last_name')->orderBy('first_name')->get();

        return view('human_resources.vacations.edit', compact('workerVacation', 'workers'));
    }

    public function update(Request $request, WorkerVacation $workerVacation): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['status'] = $request->validate(['status' => 'required|in:in_progress,approved,taken,cancelled'])['status'];

        $workerVacation->update($data);

        $this->notify($workerVacation, 'update', "actualizó las vacaciones de {$workerVacation->worker->first_name} {$workerVacation->worker->last_name}.");

        return redirect()->route('human_resources.worker-vacations.show', $workerVacation)
            ->with('success', 'Vacaciones actualizadas correctamente.');
    }

    public function destroy(WorkerVacation $workerVacation): RedirectResponse
    {
        $workerVacation->loadMissing('worker');
        $name = "{$workerVacation->worker->first_name} {$workerVacation->worker->last_name}";
        $workerVacation->delete();

        $this->notify($workerVacation, 'destroy', "eliminó las vacaciones de {$name}.");

        if ($request->boolean('return_to_worker')) {
            return redirect()->route('human_resources.workers.show', $workerVacation->worker_id)
                ->with('success', 'Vacaciones eliminadas correctamente.');
        }

        return redirect()->route('human_resources.worker-vacations.index')
            ->with('success', 'Vacaciones eliminadas correctamente.');
    }

    public function approve(WorkerVacation $workerVacation): RedirectResponse
    {
        if ($workerVacation->status !== 'in_progress') {
            return redirect()->route('human_resources.worker-vacations.show', $workerVacation)
                ->with('error', 'Solo se pueden aprobar solicitudes en curso.');
        }

        $workerVacation->update(['status' => 'approved']);

        $this->notify($workerVacation, 'update', "aprobó las vacaciones de {$workerVacation->worker->first_name} {$workerVacation->worker->last_name}.");

        if ($request->boolean('return_to_worker')) {
            return redirect()->route('human_resources.workers.show', $workerVacation->worker_id)
                ->with('success', 'Vacaciones aprobadas correctamente.');
        }

        return redirect()->route('human_resources.worker-vacations.show', $workerVacation)
            ->with('success', 'Vacaciones aprobadas correctamente.');
    }

    public function cancel(WorkerVacation $workerVacation): RedirectResponse
    {
        if (in_array($workerVacation->status, ['taken', 'cancelled'], true)) {
            return redirect()->route('human_resources.worker-vacations.show', $workerVacation)
                ->with('error', 'Estas vacaciones no se pueden cancelar.');
        }

        $workerVacation->update(['status' => 'cancelled']);

        $this->notify($workerVacation, 'update', "canceló las vacaciones de {$workerVacation->worker->first_name} {$workerVacation->worker->last_name}.");

        if ($request->boolean('return_to_worker')) {
            return redirect()->route('human_resources.workers.show', $workerVacation->worker_id)
                ->with('success', 'Vacaciones canceladas correctamente.');
        }

        return redirect()->route('human_resources.worker-vacations.show', $workerVacation)
            ->with('success', 'Vacaciones canceladas correctamente.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'worker_id' => 'required|exists:workers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'request_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $data['total_days'] = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;

        return $data;
    }

    private function notify(WorkerVacation $vacation, string $action, string $data): void
    {
        $this->notification->send([
            'type' => 'WorkerVacation',
            'action_by' => Auth::id(),
            'model_action' => $action,
            'model_id' => $vacation->id,
            'data' => $data,
        ]);
    }
}
