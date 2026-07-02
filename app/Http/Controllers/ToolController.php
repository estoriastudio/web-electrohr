<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\ProjectWork;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ToolController extends Controller
{
    public function __construct(private NotificationService $notification)
    {
    }

    public function index(Request $request): View
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');
        $rootCategoryId = $request->input('root_category_id', '');

        $tools = Tool::with(['category.parent'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('economic_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            }))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($rootCategoryId, function ($query) use ($rootCategoryId) {
                $query->whereHas('category', function ($categoryQuery) use ($rootCategoryId) {
                    $categoryQuery->where('id', $rootCategoryId)
                        ->orWhere('parent_id', $rootCategoryId);
                });
            })
            ->orderBy('economic_number')
            ->paginate(25)
            ->withQueryString();

        $rootCategories = ToolCategory::whereNull('parent_id')
            ->where('status', 'active')
            ->with(['children' => fn ($query) => $query->where('status', 'active')->orderBy('name')])
            ->orderBy('name')
            ->get();

        $projectWorks = ProjectWork::with('project')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('tools.index', compact('tools', 'search', 'status', 'rootCategoryId', 'rootCategories', 'projectWorks'));
    }

    public function show(Tool $tool): View
    {
        $tool->load([
            'category.parent',
            'controls' => fn ($query) => $query->with('projectWork.project')->latest('checkout_date'),
        ]);

        $rootCategories = ToolCategory::whereNull('parent_id')
            ->where('status', 'active')
            ->with(['children' => fn ($query) => $query->where('status', 'active')->orderBy('name')])
            ->orderBy('name')
            ->get();

        $projectWorks = ProjectWork::with('project')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'project_id', 'name']);

        return view('tools.show', compact('tool', 'rootCategories', 'projectWorks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'root_category_id' => ['required', 'exists:tool_categories,id'],
            'subcategory_id' => ['nullable', 'exists:tool_categories,id'],
            'economic_number' => ['required', 'string', 'max:100', 'unique:tools,economic_number'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:500'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $toolCategoryId = $this->resolveToolCategoryId($validated['root_category_id'], $validated['subcategory_id'] ?? null);

        $tool = Tool::create([
            'tool_category_id' => $toolCategoryId,
            'economic_number' => $validated['economic_number'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'status' => $validated['status'],
        ]);

        $this->notification->send([
            'type' => 'Tool',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $tool->id,
            'data' => 'created tool "' . $tool->economic_number . ' - ' . $tool->name . '".',
        ]);

        return redirect()->route('tools.index')
            ->with('success', 'Herramienta creada correctamente.');
    }

    public function update(Request $request, Tool $tool): RedirectResponse
    {
        $validated = $request->validate([
            'root_category_id' => ['required', 'exists:tool_categories,id'],
            'subcategory_id' => ['nullable', 'exists:tool_categories,id'],
            'economic_number' => ['required', 'string', 'max:100', Rule::unique('tools', 'economic_number')->ignore($tool->id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:500'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $toolCategoryId = $this->resolveToolCategoryId($validated['root_category_id'], $validated['subcategory_id'] ?? null);

        $tool->update([
            'tool_category_id' => $toolCategoryId,
            'economic_number' => $validated['economic_number'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'status' => $validated['status'],
        ]);

        $this->notification->send([
            'type' => 'Tool',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $tool->id,
            'data' => 'updated tool "' . $tool->economic_number . ' - ' . $tool->name . '".',
        ]);

        return redirect()->route('tools.index')
            ->with('success', 'Herramienta actualizada correctamente.');
    }

    public function destroy(Tool $tool): RedirectResponse
    {
        $label = $tool->economic_number . ' - ' . $tool->name;
        $tool->delete();

        $this->notification->send([
            'type' => 'Tool',
            'action_by' => Auth::id(),
            'model_action' => 'destroy',
            'model_id' => $tool->id,
            'data' => 'deleted tool "' . $label . '".',
        ]);

        return redirect()->route('tools.index')
            ->with('success', 'Herramienta eliminada correctamente.');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('tools.index');
    }

    public function edit(Tool $tool): RedirectResponse
    {
        return redirect()->route('tools.index');
    }

    private function resolveToolCategoryId(string $rootCategoryId, ?string $subcategoryId): int
    {
        $rootCategory = ToolCategory::findOrFail($rootCategoryId);

        if ($rootCategory->parent_id) {
            throw ValidationException::withMessages([
                'root_category_id' => 'Selected category must be a root category.',
            ]);
        }

        if ($subcategoryId) {
            $subcategory = ToolCategory::findOrFail($subcategoryId);
            if ((int) $subcategory->parent_id !== (int) $rootCategory->id) {
                throw ValidationException::withMessages([
                    'subcategory_id' => 'Selected subcategory does not belong to the selected category.',
                ]);
            }

            return (int) $subcategory->id;
        }

        return (int) $rootCategory->id;
    }
}
