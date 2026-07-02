<?php

namespace App\Http\Controllers;

use App\Models\ToolCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ToolCategoryController extends Controller
{
    public function index(): View
    {
        $rootCategories = ToolCategory::with(['children' => function ($query) {
                $query->withCount('tools')->orderBy('name');
            }])
            ->withCount('tools')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        $allCategories = ToolCategory::orderBy('name')->get();

        return view('tool_categories.index', compact('rootCategories', 'allCategories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:tool_categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        ToolCategory::create($validated);

        return redirect()->route('tool_categories.index')
            ->with('success', 'Categoría de herramienta creada correctamente.');
    }

    public function update(Request $request, ToolCategory $toolCategory): RedirectResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:tool_categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if (($validated['parent_id'] ?? null) && (int) $validated['parent_id'] === $toolCategory->id) {
            return redirect()->route('tool_categories.index')
                ->with('error', 'Una categoría no puede ser su propia categoría padre.');
        }

        // Keep hierarchy simple: only one parent level.
        if (! empty($validated['parent_id'])) {
            $parent = ToolCategory::find($validated['parent_id']);
            if ($parent && $parent->parent_id) {
                return redirect()->route('tool_categories.index')
                    ->with('error', 'Solo las categorías raíz pueden usarse como categoría padre.');
            }
        }

        $toolCategory->update($validated);

        return redirect()->route('tool_categories.index')
            ->with('success', 'Categoría de herramienta actualizada correctamente.');
    }

    public function destroy(ToolCategory $toolCategory): RedirectResponse
    {
        if ($toolCategory->children()->exists()) {
            return redirect()->route('tool_categories.index')
                ->with('error', 'No puedes eliminar una categoría que aún tiene subcategorías.');
        }

        $toolCategory->delete();

        return redirect()->route('tool_categories.index')
            ->with('success', 'Categoría de herramienta eliminada correctamente.');
    }

    public function subcategoriesJson(ToolCategory $toolCategory): JsonResponse
    {
        return response()->json(
            $toolCategory->children()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('tool_categories.index');
    }

    public function show(ToolCategory $toolCategory): RedirectResponse
    {
        return redirect()->route('tool_categories.index');
    }

    public function edit(ToolCategory $toolCategory): RedirectResponse
    {
        return redirect()->route('tool_categories.index');
    }
}
