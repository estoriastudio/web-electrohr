<?php

namespace App\Http\Controllers;

use App\Models\PositionCategory;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PositionCategoryController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index(): View
    {
        $positionCategories = PositionCategory::query()
            ->withCount(['workers', 'terminations'])
            ->orderBy('name')
            ->paginate(25);

        return view('human_resources.position-categories.index', compact('positionCategories'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('human_resources.position-categories.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $positionCategory = PositionCategory::create($this->validatedData($request));

        $this->notify($positionCategory, 'create', "creó la categoría de puesto {$positionCategory->name}.");

        return redirect()->route('human_resources.position-categories.index')
            ->with('success', 'Categoría de puesto creada correctamente.');
    }

    public function show(PositionCategory $positionCategory): RedirectResponse
    {
        return redirect()->route('human_resources.position-categories.index');
    }

    public function edit(PositionCategory $positionCategory): RedirectResponse
    {
        return redirect()->route('human_resources.position-categories.index');
    }

    public function update(Request $request, PositionCategory $positionCategory): RedirectResponse
    {
        $positionCategory->update($this->validatedData($request, $positionCategory));

        $this->notify($positionCategory, 'update', "actualizó la categoría de puesto {$positionCategory->name}.");

        return redirect()->route('human_resources.position-categories.index')
            ->with('success', 'Categoría de puesto actualizada correctamente.');
    }

    public function destroy(PositionCategory $positionCategory): RedirectResponse
    {
        if ($positionCategory->workers()->exists() || $positionCategory->terminations()->exists()) {
            return redirect()->route('human_resources.position-categories.index')
                ->with('error', 'No se puede eliminar una categoría que tiene historial de trabajadores o bajas.');
        }

        $name = $positionCategory->name;
        $positionCategory->delete();

        $this->notify($positionCategory, 'destroy', "eliminó la categoría de puesto {$name}.");

        return redirect()->route('human_resources.position-categories.index')
            ->with('success', 'Categoría de puesto eliminada correctamente.');
    }

    private function validatedData(Request $request, ?PositionCategory $positionCategory = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('position_categories', 'name')->ignore($positionCategory?->id),
            ],
            'active' => 'required|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);
    }

    private function notify(PositionCategory $positionCategory, string $action, string $data): void
    {
        $this->notification->send([
            'type' => 'PositionCategory',
            'action_by' => Auth::id(),
            'model_action' => $action,
            'model_id' => $positionCategory->id,
            'data' => $data,
        ]);
    }
}