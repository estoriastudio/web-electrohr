<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresHrOffice;
use App\Models\WorkerGroup;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkerGroupController extends Controller
{
    use RequiresHrOffice;

    public function __construct(private NotificationService $notification) {}

    public function index(Request $request): View
    {
        $search = trim($request->input('search', ''));
        $status = $request->input('status', '');

        $workerGroups = WorkerGroup::query()
            ->withCount('workers')
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('human_resources.worker-groups.index', compact('workerGroups', 'search', 'status'));
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

    public function update(Request $request, WorkerGroup $workerGroup): RedirectResponse
    {
        $workerGroup->update($this->validatedData($request, $workerGroup));

        $this->notify($workerGroup, 'update', "actualizó la cuadrilla {$workerGroup->name}.");

        return redirect()->route('human_resources.worker-groups.index')
            ->with('success', 'Cuadrilla actualizada correctamente.');
    }

    public function destroy(WorkerGroup $workerGroup): RedirectResponse
    {
        if ($workerGroup->workers()->exists()) {
            return redirect()->route('human_resources.worker-groups.index')
                ->with('error', 'No se puede eliminar una cuadrilla con trabajadores asignados.');
        }

        $name = $workerGroup->name;
        $workerGroup->delete();

        $this->notify($workerGroup, 'destroy', "eliminó la cuadrilla {$name}.");

        return redirect()->route('human_resources.worker-groups.index')
            ->with('success', 'Cuadrilla eliminada correctamente.');
    }

    private function validatedData(Request $request, ?WorkerGroup $workerGroup = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('worker_groups', 'name')->ignore($workerGroup?->id),
            ],
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);
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
