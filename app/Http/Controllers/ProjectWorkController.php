<?php

namespace App\Http\Controllers;

use App\Models\ProjectWork;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProjectWorkController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function create(): RedirectResponse
    {
        return redirect()->route('projects.index');
    }
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'name'       => 'required|string|max:255',
        ]);

        $work = ProjectWork::create(array_merge($validated, ['status' => 'active']));

        $this->notification->send([
            'type'         => 'ProjectWork',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $work->id,
            'data'         => 'creó la obra "' . $work->name . '".',
        ]);

        return redirect()->route('projects.show', $validated['project_id'])
            ->with('success', 'Obra "' . $work->name . '" creada correctamente.');
    }

    public function show(ProjectWork $projectWork): View
    {
        $projectWork->load([
            'project',
            'purchaseOrders' => function ($q) {
                $q->with('supplier')
                  ->withCount('milestones')
                  ->latest();
            },
        ]);

        return view('project_works.show', compact('projectWork'));
    }

    public function edit(ProjectWork $projectWork): View
    {
        return view('project_works.edit', compact('projectWork'));
    }

    public function update(Request $request, ProjectWork $projectWork): RedirectResponse
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $projectWork->update($validated);

        $this->notification->send([
            'type'         => 'ProjectWork',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $projectWork->id,
            'data'         => 'actualizó la obra "' . $projectWork->name . '".',
        ]);

        return redirect()->route('project_works.show', $projectWork)
            ->with('success', 'Obra actualizada correctamente.');
    }

    public function destroy(ProjectWork $projectWork): RedirectResponse
    {
        $projectId = $projectWork->project_id;
        $name      = $projectWork->name;

        $projectWork->delete();

        $this->notification->send([
            'type'         => 'ProjectWork',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $projectWork->id,
            'data'         => 'eliminó la obra "' . $name . '".',
        ]);

        return redirect()->route('projects.show', $projectId)
            ->with('success', 'Obra eliminada correctamente.');
    }
}

