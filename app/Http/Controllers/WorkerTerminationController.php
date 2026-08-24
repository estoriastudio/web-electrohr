<?php

namespace App\Http\Controllers;

use App\Models\ProjectWork;
use App\Models\Worker;
use App\Models\WorkerTermination;
use App\Services\NotificationService;
use App\Services\WorkerTerminationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WorkerTerminationController extends Controller
{
    public function __construct(
        private NotificationService $notification,
        private WorkerTerminationService $terminationService,
    ) {}

    public function index(Request $request): View
    {
        $terminationType = $request->input('termination_type', '');
        $projectWorkId = $request->input('project_work_id', '');

        $terminations = WorkerTermination::query()
            ->with(['worker', 'projectWork'])
            ->when($terminationType, fn ($query) => $query->where('termination_type', $terminationType))
            ->when($projectWorkId, fn ($query) => $query->where('project_work_id', $projectWorkId))
            ->orderByDesc('termination_date')
            ->paginate(25)
            ->withQueryString();
        $projectWorks = ProjectWork::orderBy('name')->get();

        return view('human_resources.terminations.index', compact('terminations', 'projectWorks', 'terminationType', 'projectWorkId'));
    }

    public function show(WorkerTermination $workerTermination): View
    {
        $workerTermination->load(['worker', 'projectWork', 'positionCategory']);

        return view('human_resources.terminations.show', compact('workerTermination'));
    }

    public function store(Request $request, Worker $worker): RedirectResponse
    {
        $data = $request->validate([
            'project_work_id' => 'nullable|exists:project_works,id',
            'position_category_id' => 'nullable|exists:position_categories,id',
            'salary' => 'nullable|numeric|min:0',
            'termination_type' => 'required|in:resignation,dismissal,rest',
            'reason' => 'nullable|string|max:255',
            'termination_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);
        $termination = $this->terminationService->terminate($worker, $data);

        $this->notification->send([
            'type' => 'WorkerTermination',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $termination->id,
            'data' => "registró la baja de {$worker->first_name} {$worker->last_name}.",
        ]);

        return redirect()->route('human_resources.workers.show', $worker)
            ->with('success', 'Baja registrada correctamente.');
    }
}