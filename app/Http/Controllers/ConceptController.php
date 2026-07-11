<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\ConceptCategory;
use App\Models\ConceptSubcategory;
use App\Models\PurchaseOrderItem;
use App\Imports\ConceptImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'description'             => ['required', 'string', 'max:2500'],
            'unit'                    => ['required', 'string', 'max:50'],
            'unit_price'              => ['nullable', 'numeric', 'min:0'],
            'status'                  => ['required', Rule::in(['active', 'inactive'])],
            'type'                    => ['required', Rule::in(['materiales', 'mantenimiento'])],
            'concept_category_id'     => ['required', 'exists:concept_categories,id'],
            'concept_subcategory_id'  => ['required', 'exists:concept_subcategories,id'],
        ]);

        $categoryId = (int) $validated['concept_category_id'];
        $subcategoryId = (int) $validated['concept_subcategory_id'];

        $belongsToCategory = ConceptSubcategory::query()
            ->where('id', $subcategoryId)
            ->where('concept_category_id', $categoryId)
            ->exists();

        if (! $belongsToCategory) {
            throw ValidationException::withMessages([
                'concept_subcategory_id' => 'La subcategoría seleccionada no pertenece a la categoría indicada.',
            ]);
        }

        $category = ConceptCategory::query()->findOrFail($categoryId);
        $subcategory = ConceptSubcategory::query()->findOrFail($subcategoryId);

        $validated['code'] = $this->buildNextCode($category, $subcategory);

        Concept::create($validated);

        return redirect()->route('concepts.index')
            ->with('success', 'Concepto creado correctamente.');
    }

    public function update(Request $request, Concept $concept): RedirectResponse
    {
        $validated = $request->validate([
            'code'                    => ['required', 'string', 'max:100', Rule::unique('concepts', 'code')->ignore($concept->id)],
            'description'             => ['required', 'string', 'max:2500'],
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

    public function nextCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'concept_category_id'    => ['required', 'integer', 'exists:concept_categories,id'],
            'concept_subcategory_id' => ['required', 'integer', 'exists:concept_subcategories,id'],
        ]);

        $categoryId = (int) $validated['concept_category_id'];
        $subcategoryId = (int) $validated['concept_subcategory_id'];

        $subcategory = ConceptSubcategory::query()
            ->where('id', $subcategoryId)
            ->where('concept_category_id', $categoryId)
            ->first();

        if (! $subcategory) {
            return response()->json([
                'message' => 'La subcategoría seleccionada no pertenece a la categoría indicada.',
            ], 422);
        }

        $category = ConceptCategory::query()->findOrFail($categoryId);
        $prefix = $this->buildCodePrefix($category->name, $subcategory->name);
        $nextNumber = $this->nextNumberForPrefix($categoryId, $subcategoryId, $prefix);

        return response()->json([
            'code'        => $this->makeAvailableCode($prefix, $nextNumber),
            'prefix'      => $prefix,
            'next_number' => $nextNumber,
        ]);
    }

    public function awardedPrices(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $items = PurchaseOrderItem::query()
            ->select('purchase_order_items.*')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->with(['purchaseOrder.supplier', 'concept'])
            ->whereNull('purchase_orders.deleted_at')
            ->when($search === '', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($group) use ($search) {
                    $group->where('purchase_order_items.description', 'like', "%{$search}%")
                        ->orWhereHas('concept', function ($q2) use ($search) {
                            $q2->where('description', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('purchase_orders.created_at')
            ->orderByDesc('purchase_orders.id')
            ->paginate(25)
            ->withQueryString();

        return view('concepts.awarded_prices', compact('items', 'search'));
    }

    public function destroy(Concept $concept): RedirectResponse
    {
        $concept->delete();

        return redirect()->route('concepts.index')
            ->with('success', 'Concepto eliminado correctamente.');
    }

    public function search(Request $request): JsonResponse
    {
        $q        = trim((string) $request->input('q', ''));
        $type     = $request->input('type', '');
        $category = $request->input('concept_category_id', $request->input('category_id', $request->input('category', '')));
        $limit    = max(20, min((int) $request->input('limit', 300), 1000));
        $perPage  = max(10, min((int) $request->input('per_page', 50), 200));
        $paginated = $request->boolean('paginated');
        $tokens   = collect(preg_split('/\s+/', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->values();

        $query = Concept::query()
            ->where('status', 'active')
            ->when($type,     fn ($q2) => $q2->where('type', $type))
            ->when($category, fn ($q2) => $q2->where('concept_category_id', $category))
            ->when($q !== '', function ($query) use ($q, $tokens) {
                $query->where(function ($group) use ($q, $tokens) {
                    // Filtro base por texto completo
                    $group->where('code', 'like', "%{$q}%")
                          ->orWhere('description', 'like', "%{$q}%");

                    // Doble filtro: por cada token, buscar en código/descripcion
                    if ($tokens->isNotEmpty()) {
                        $group->orWhere(function ($tokenGroup) use ($tokens) {
                            foreach ($tokens as $token) {
                                $tokenGroup->where(function ($tokenMatch) use ($token) {
                                    $tokenMatch->whereRaw('LOWER(code) like ?', ["%{$token}%"])
                                               ->orWhereRaw('LOWER(description) like ?', ["%{$token}%"]);
                                });
                            }
                        });
                    }
                });
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->orderByRaw(
                    "CASE
                        WHEN LOWER(description) LIKE ? THEN 0
                        WHEN LOWER(code) LIKE ? THEN 1
                        WHEN LOWER(description) LIKE ? THEN 2
                        WHEN LOWER(code) LIKE ? THEN 3
                        ELSE 4
                    END",
                    [mb_strtolower($q) . '%', mb_strtolower($q) . '%', '%' . mb_strtolower($q) . '%', '%' . mb_strtolower($q) . '%']
                );
            })
            ->orderBy('description')
            ->orderBy('code');

        // Compatibilidad: modo anterior (array simple, sin metadatos)
        if (! $paginated) {
            $concepts = $query
                ->limit($limit)
                ->get(['id', 'code', 'description', 'unit', 'unit_price']);

            return response()->json($concepts);
        }

        // Modo paginado para catálogos grandes en autocompletes.
        $result = $query->paginate(
            $perPage,
            ['id', 'code', 'description', 'unit', 'unit_price'],
            'page',
            (int) $request->input('page', 1)
        );

        return response()->json([
            'data' => $result->items(),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
                'has_more'     => $result->hasMorePages(),
            ],
        ]);
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

    private function buildNextCode(ConceptCategory $category, ConceptSubcategory $subcategory): string
    {
        $prefix = $this->buildCodePrefix($category->name, $subcategory->name);
        $nextNumber = $this->nextNumberForPrefix($category->id, $subcategory->id, $prefix);

        return $this->makeAvailableCode($prefix, $nextNumber);
    }

    private function nextNumberForPrefix(int $categoryId, int $subcategoryId, string $prefix): int
    {
        $max = 0;

        $codes = Concept::query()
            ->where('concept_category_id', $categoryId)
            ->where('concept_subcategory_id', $subcategoryId)
            ->where('code', 'like', $prefix . '-%')
            ->pluck('code');

        foreach ($codes as $code) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', (string) $code, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max + 1;
    }

    private function makeAvailableCode(string $prefix, int $startNumber): string
    {
        $number = max(1, $startNumber);

        do {
            $code = $prefix . '-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $number++;
        } while (Concept::query()->where('code', $code)->exists());

        return $code;
    }

    private function buildCodePrefix(string $categoryName, string $subcategoryName): string
    {
        return $this->abbr($categoryName) . '-' . $this->abbr($subcategoryName);
    }

    private function abbr(string $name): string
    {
        $ascii = Str::upper(Str::ascii(trim($name)));
        $lettersOnly = preg_replace('/[^A-Z]/', '', $ascii) ?? '';
        $piece = substr($lettersOnly, 0, 3);

        return str_pad($piece, 3, 'X');
    }
}

