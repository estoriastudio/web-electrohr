<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\ConceptCategory;
use App\Imports\ConceptImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\View\View;

class ConceptController extends Controller
{
    public function index(Request $request): View
    {
        $search   = $request->input('search', '');
        $status   = $request->input('status', '');
        $type     = $request->input('type', '');
        $category = $request->input('category', '');

        $concepts = Concept::with(['category', 'subcategory'])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($status,   fn ($q) => $q->where('status', $status))
            ->when($type,     fn ($q) => $q->where('type', $type))
            ->when($category, fn ($q) => $q->where('concept_category_id', $category))
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        $categories = ConceptCategory::orderBy('name')->get();

        return view('concepts.index', compact('concepts', 'search', 'status', 'type', 'category', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'                    => ['required', 'string', 'max:100', 'unique:concepts,code'],
            'description'             => ['required', 'string', 'max:500'],
            'unit'                    => ['required', 'string', 'max:50'],
            'unit_price'              => ['nullable', 'numeric', 'min:0'],
            'status'                  => ['required', Rule::in(['active', 'inactive'])],
            'type'                    => ['required', Rule::in(['materiales', 'mantenimiento'])],
            'concept_category_id'     => ['nullable', 'exists:concept_categories,id'],
            'concept_subcategory_id'  => ['nullable', 'exists:concept_subcategories,id'],
        ]);

        Concept::create($validated);

        return redirect()->route('concepts.index')
            ->with('success', 'Concepto creado correctamente.');
    }

    public function update(Request $request, Concept $concept): RedirectResponse
    {
        $validated = $request->validate([
            'code'                    => ['required', 'string', 'max:100', Rule::unique('concepts', 'code')->ignore($concept->id)],
            'description'             => ['required', 'string', 'max:500'],
            'unit'                    => ['required', 'string', 'max:50'],
            'unit_price'              => ['nullable', 'numeric', 'min:0'],
            'status'                  => ['required', Rule::in(['active', 'inactive'])],
            'type'                    => ['required', Rule::in(['materiales', 'mantenimiento'])],
            'concept_category_id'     => ['nullable', 'exists:concept_categories,id'],
            'concept_subcategory_id'  => ['nullable', 'exists:concept_subcategories,id'],
        ]);

        $concept->update($validated);

        return redirect()->route('concepts.index')
            ->with('success', 'Concepto actualizado correctamente.');
    }

    public function destroy(Concept $concept): RedirectResponse
    {
        $concept->delete();

        return redirect()->route('concepts.index')
            ->with('success', 'Concepto eliminado correctamente.');
    }

    public function search(Request $request): JsonResponse
    {
        $q        = $request->input('q', '');
        $type     = $request->input('type', '');
        $category = $request->input('concept_category_id', $request->input('category_id', $request->input('category', '')));

        // Si se especifica tipo pero no categoría, no devolver resultados
        if ($type && ! $category) {
            return response()->json([]);
        }

        $concepts = Concept::where('status', 'active')
            ->when($type,     fn ($q2) => $q2->where('type', $type))
            ->when($category, fn ($q2) => $q2->where('concept_category_id', $category))
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
            })
            ->orderBy('code')
            ->limit(20)
            ->get(['id', 'code', 'description', 'unit', 'unit_price']);

        return response()->json($concepts);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        DB::connection()->disableQueryLog();

        Excel::import(new ConceptImport, $request->file('file'));

        return redirect()->route('concepts.index')
            ->with('success', 'Conceptos importados correctamente.');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('concepts.index');
    }

    public function show(Concept $concept): RedirectResponse
    {
        return redirect()->route('concepts.index');
    }

    public function edit(Concept $concept): RedirectResponse
    {
        return redirect()->route('concepts.index');
    }
}

