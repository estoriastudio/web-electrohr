<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Imports\ProjectImport;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(private NotificationService $notification) {}
    public function index(Request $request): View
    {
        $search = trim($request->input('search', ''));

        $projects = Project::withCount('works')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('client_name', 'like', "%{$search}%"))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('projects.index', compact('projects', 'search'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('projects.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'city'        => 'nullable|string|max:255',
            'state'       => 'nullable|string|max:255',
        ]);

        $project = Project::create(array_merge($validated, ['status' => 'active']));

        $this->notification->send([
            'type'         => 'Project',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $project->id,
            'data'         => 'creó el proyecto "' . $project->name . '".',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Proyecto creado correctamente.');
    }

    public function show(Project $project): View
    {
        $project->loadCount('works');
        $project->load(['works' => function ($q) {
            $q->withCount('purchaseOrders');
        }]);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project): View
    {
        return view('projects.edit', compact('project'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'city'        => 'nullable|string|max:255',
            'state'       => 'nullable|string|max:255',
            'status'      => 'required|in:active,inactive',
        ]);

        $project->update($validated);

        $this->notification->send([
            'type'         => 'Project',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $project->id,
            'data'         => 'actualizó el proyecto "' . $project->name . '".',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Proyecto actualizado correctamente.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $name = $project->name;

        $project->delete();

        $this->notification->send([
            'type'         => 'Project',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $project->id,
            'data'         => 'eliminó el proyecto "' . $name . '".',
        ]);

        return redirect()->route('projects.index')
            ->with('success', 'Proyecto eliminado correctamente.');
    }

    public function worksJson(Project $project): JsonResponse
    {
        $works = $project->works()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($works);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        Excel::import(new ProjectImport, $request->file('file'));

        return redirect()->route('projects.index')
            ->with('success', 'Proyectos y obras importados correctamente.');
    }
}

