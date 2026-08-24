<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\Worker;
use App\Models\WorkerGroup;
use App\Services\NotificationService;
use App\Services\WorkerGroupMembershipService;
use App\Services\WorkerGroupRelocationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkerGroupController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index(Request $request): View
    {
        $search = trim($request->input('search', ''));
        $status = $request->input('status', '');
        $projectWorkId = $request->input('project_work_id', '');

        $workerGroups = WorkerGroup::query()
            ->with('projectWork')
            ->withCount([
                'activeMembers',
                'activeMembers as dc3_covered_members_count' => fn ($query) => $query->has('dc3s'),
            ])
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($projectWorkId, fn ($query) => $query->where('project_work_id', $projectWorkId))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $projects = Project::orderBy('name')->get();
        $projectWorks = ProjectWork::orderBy('name')->get();

        return view('human_resources.worker-groups.index', compact('workerGroups', 'projects', 'projectWorks', 'search', 'status', 'projectWorkId'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('human_resources.worker-groups.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $workerGroup = WorkerGroup::create($this->validatedData($request));

        $this->notify($workerGroup, 'create', "creó la cuadrilla {$workerGroup->name}.");

        return redirect()->route('human_resources.worker-groups.index')
            ->with('success', 'Cuadrilla creada correctamente.');
    }

    public function show(WorkerGroup $workerGroup): View
    {
        $workerGroup->load(['projectWork', 'activeMembers.positionCategory', 'attendances.worker']);

        $projects = Project::orderBy('name')->get();
        $projectWorks = ProjectWork::orderBy('name')->get();

        return view('human_resources.worker-groups.show', compact('workerGroup', 'projects', 'projectWorks'));
    }

    public function edit(WorkerGroup $workerGroup): View
    {
        $workerGroup->load('projectWork');
        $projects = Project::orderBy('name')->get();
        $projectWorks = ProjectWork::orderBy('name')->get();

        return view('human_resources.worker-groups.edit', compact('workerGroup', 'projects', 'projectWorks'));
    }

    public function update(Request $request, WorkerGroup $workerGroup): RedirectResponse
    {
        $workerGroup->update($this->validatedData($request));

        $this->notify($workerGroup, 'update', "actualizó la cuadrilla {$workerGroup->name}.");

        return redirect()->route('human_resources.worker-groups.show', $workerGroup)
            ->with('success', 'Cuadrilla actualizada correctamente.');
    }

    public function destroy(WorkerGroup $workerGroup): RedirectResponse
    {
        if ($workerGroup->activeMembers()->exists()) {
            return redirect()->route('human_resources.worker-groups.show', $workerGroup)
                ->with('error', 'No se puede eliminar una cuadrilla con integrantes activos.');
        }

        $name = $workerGroup->name;
        $workerGroup->delete();

        $this->notify($workerGroup, 'destroy', "eliminó la cuadrilla {$name}.");

        return redirect()->route('human_resources.worker-groups.index')
            ->with('success', 'Cuadrilla eliminada correctamente.');
    }

    public function relocate(Request $request, WorkerGroup $workerGroup, WorkerGroupRelocationService $relocationService): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'project_work_id' => 'required|exists:project_works,id',
        ]);

        $belongsToProject = ProjectWork::query()
            ->whereKey($data['project_work_id'])
            ->where('project_id', $data['project_id'])
            ->exists();

        if (! $belongsToProject) {
            throw ValidationException::withMessages([
                'project_work_id' => 'La obra seleccionada no pertenece al proyecto indicado.',
            ]);
        }

        $relocationService->relocate($workerGroup, (int) $data['project_work_id']);

        $this->notify($workerGroup, 'update', "reubicó la cuadrilla {$workerGroup->name}.");

        return redirect()->route('human_resources.worker-groups.show', $workerGroup)
            ->with('success', 'Cuadrilla reubicada correctamente.');
    }

    public function searchEligibleMembers(Request $request, WorkerGroup $workerGroup): JsonResponse
    {
        $search = trim((string) $request->input('q', ''));

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $workers = Worker::query()
            ->whereIn('status', ['pre_registered', 'active'])
            ->whereDoesntHave('groups', fn ($query) => $query->whereNull('worker_group_members.left_at'))
            ->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(20)
            ->with('positionCategory:id,name')
            ->get(['id', 'employee_code', 'first_name', 'last_name', 'position_category_id'])
            ->map(fn (Worker $worker) => [
                'id' => $worker->id,
                'employee_code' => $worker->employee_code,
                'first_name' => $worker->first_name,
                'last_name' => $worker->last_name,
                'position_category_name' => $worker->positionCategory?->name,
            ]);

        return response()->json($workers);
    }

    public function addMember(Request $request, WorkerGroup $workerGroup, WorkerGroupMembershipService $membershipService): RedirectResponse|JsonResponse
    {
        if ($workerGroup->status !== 'active') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Solo se pueden agregar integrantes a cuadrillas activas.',
                ], 422);
            }

            return redirect()->route('human_resources.worker-groups.show', $workerGroup)
                ->with('error', 'Solo se pueden agregar integrantes a cuadrillas activas.');
        }

        $data = $request->validate([
            'worker_id' => 'required|exists:workers,id',
            'joined_at' => 'required|date',
        ]);
        $worker = Worker::findOrFail($data['worker_id']);

        try {
            $membershipService->add($workerGroup, $worker, $data['joined_at']);
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return redirect()->route('human_resources.worker-groups.show', $workerGroup)
                ->with('error', $exception->getMessage());
        }

        $this->notify($workerGroup, 'update', "agregó a {$worker->first_name} {$worker->last_name} a la cuadrilla {$workerGroup->name}.");

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Integrante agregado correctamente.',
                'member' => [
                    'id' => $worker->id,
                    'first_name' => $worker->first_name,
                    'last_name' => $worker->last_name,
                    'position_category_name' => $worker->positionCategory?->name,
                    'joined_at' => Carbon::parse($data['joined_at'])->format('d/m/Y'),
                ],
            ], 201);
        }

        return redirect()->route('human_resources.worker-groups.show', $workerGroup)
            ->with('success', 'Integrante agregado correctamente.');
    }

    public function removeMember(Request $request, WorkerGroup $workerGroup, Worker $worker, WorkerGroupMembershipService $membershipService): RedirectResponse
    {
        $data = $request->validate([
            'left_at' => 'required|date',
        ]);

        try {
            $membershipService->remove($workerGroup, $worker, $data['left_at']);
        } catch (DomainException $exception) {
            return redirect()->route('human_resources.worker-groups.show', $workerGroup)
                ->with('error', $exception->getMessage());
        }

        $this->notify($workerGroup, 'update', "dio de baja a {$worker->first_name} {$worker->last_name} de la cuadrilla {$workerGroup->name}.");

        return redirect()->route('human_resources.worker-groups.show', $workerGroup)
            ->with('success', 'Integrante removido correctamente.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'project_id' => 'required|exists:projects,id',
            'project_work_id' => 'required|exists:project_works,id',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        $belongsToProject = ProjectWork::query()
            ->whereKey($data['project_work_id'])
            ->where('project_id', $data['project_id'])
            ->exists();

        if (! $belongsToProject) {
            throw ValidationException::withMessages([
                'project_work_id' => 'La obra seleccionada no pertenece al proyecto indicado.',
            ]);
        }

        unset($data['project_id']);

        return $data;
    }

    private function notify(WorkerGroup $workerGroup, string $action, string $data): void
    {
        $this->notification->send([
            'type' => 'WorkerGroup',
            'action_by' => Auth::id(),
            'model_action' => $action,
            'model_id' => $workerGroup->id,
            'data' => $data,
        ]);
    }
}
