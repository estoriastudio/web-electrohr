<?php

namespace App\Http\Controllers;

use App\Models\ConceptCategory;
use App\Models\ConceptSubcategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConceptCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->input('type', 'materiales');

        $categories = ConceptCategory::with(['subcategories', 'users'])
            ->withCount('concepts')
            ->where('type', $type)
            ->orderBy('name')
            ->get();

        $purchasingUsers = User::role(['admin', 'Orden de compra'])->orderBy('name')->get();

        return view('concept_categories.index', compact('categories', 'type', 'purchasingUsers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'type'        => ['required', Rule::in(['materiales', 'mantenimiento'])],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        ConceptCategory::create($validated);

        return redirect()->route('concept_categories.index', ['type' => $validated['type']])
            ->with('success', 'Categoría creada correctamente.');
    }

    public function update(Request $request, ConceptCategory $conceptCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'type'        => ['required', Rule::in(['materiales', 'mantenimiento'])],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $conceptCategory->update($validated);

        return redirect()->route('concept_categories.index', ['type' => $validated['type']])
            ->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(ConceptCategory $conceptCategory): RedirectResponse
    {
        $type = $conceptCategory->type;
        $conceptCategory->delete();

        return redirect()->route('concept_categories.index', ['type' => $type])
            ->with('success', 'Categoría eliminada correctamente.');
    }

    // ── Subcategorías ────────────────────────────────────────────────────────

    public function storeSubcategory(Request $request, ConceptCategory $conceptCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['concept_category_id'] = $conceptCategory->id;

        ConceptSubcategory::create($validated);

        return redirect()->route('concept_categories.index', ['type' => $conceptCategory->type])
            ->with('success', 'Subcategoría creada correctamente.');
    }

    public function updateSubcategory(Request $request, ConceptCategory $conceptCategory, ConceptSubcategory $subcategory): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $subcategory->update($validated);

        return redirect()->route('concept_categories.index', ['type' => $conceptCategory->type])
            ->with('success', 'Subcategoría actualizada correctamente.');
    }

    public function destroySubcategory(ConceptCategory $conceptCategory, ConceptSubcategory $subcategory): RedirectResponse
    {
        $subcategory->delete();

        return redirect()->route('concept_categories.index', ['type' => $conceptCategory->type])
            ->with('success', 'Subcategoría eliminada correctamente.');
    }

    // ── Usuarios asignados ───────────────────────────────────────────────────

    public function syncUsers(Request $request, ConceptCategory $conceptCategory): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids'   => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $conceptCategory->users()->sync($validated['user_ids'] ?? []);

        return redirect()->route('concept_categories.index', ['type' => $conceptCategory->type])
            ->with('success', 'Compradores actualizados correctamente.');
    }

    // ── JSON: subcategorías por categoría (para selects dinámicos) ───────────

    public function subcategoriesJson(ConceptCategory $conceptCategory): JsonResponse
    {
        return response()->json(
            $conceptCategory->subcategories()->orderBy('name')->get(['id', 'name'])
        );
    }
}
