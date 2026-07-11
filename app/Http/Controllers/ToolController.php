<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\ProjectWork;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
            'calibrations',
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

        $nextCalibrationFolio = $this->previewNextCalibrationFolio($tool);

        return view('tools.show', compact('tool', 'rootCategories', 'projectWorks', 'nextCalibrationFolio'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'root_category_id' => ['required', 'exists:tool_categories,id'],
            'subcategory_id' => ['nullable', 'exists:tool_categories,id'],
            'economic_number' => ['required', 'string', 'max:100', 'unique:tools,economic_number'],
            'description' => ['required', 'string', 'max:500'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'requires_calibration' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive', 'in_service'])],
        ]);

        $toolCategoryId = $this->resolveToolCategoryId($validated['root_category_id'], $validated['subcategory_id'] ?? null);

        $tool = Tool::create([
            'tool_category_id' => $toolCategoryId,
            'economic_number' => $validated['economic_number'],
            'name' => $validated['description'],
            'description' => $validated['description'],
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'requires_calibration' => (bool) ($validated['requires_calibration'] ?? false),
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
            'description' => ['required', 'string', 'max:500'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'requires_calibration' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive', 'in_service'])],
        ]);

        $toolCategoryId = $this->resolveToolCategoryId($validated['root_category_id'], $validated['subcategory_id'] ?? null);

        $tool->update([
            'tool_category_id' => $toolCategoryId,
            'economic_number' => $validated['economic_number'],
            'name' => $validated['description'],
            'description' => $validated['description'],
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'requires_calibration' => (bool) ($validated['requires_calibration'] ?? false),
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

        foreach ([1, 2, 3] as $slot) {
            $field = 'photo' . $slot;
            if ($tool->$field) {
                Storage::disk('s3')->delete($tool->$field);
            }
        }

        foreach ($tool->calibrations as $calibration) {
            if ($calibration->file_path) {
                Storage::disk('s3')->delete($calibration->file_path);
            }
        }

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

    public function uploadPhoto(Request $request, Tool $tool, int $slot): JsonResponse
    {
        if (! in_array($slot, [1, 2, 3])) {
            return response()->json(['error' => 'Slot inválido.'], 404);
        }

        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $field = 'photo' . $slot;

        if ($tool->$field) {
            Storage::disk('s3')->delete($tool->$field);
        }

        $uploadedFile = $request->file('file');
        $extension = $uploadedFile->getClientOriginalExtension();
        $s3Path = 'tools/' . $tool->id . '/photo' . $slot . '_' . time() . '.' . $extension;

        $stored = Storage::disk('s3')->put($s3Path, file_get_contents($uploadedFile));

        if (! $stored) {
            return response()->json(['error' => 'No se pudo subir la imagen a S3.'], 500);
        }

        $tool->update([$field => $s3Path]);

        return response()->json([
            'url' => Storage::disk('s3')->url($s3Path),
            'path' => $s3Path,
            'slot' => $slot,
        ]);
    }

    public function deletePhoto(Tool $tool, int $slot): RedirectResponse
    {
        if (! in_array($slot, [1, 2, 3])) {
            abort(404);
        }

        $field = 'photo' . $slot;

        if ($tool->$field) {
            Storage::disk('s3')->delete($tool->$field);
            $tool->update([$field => null]);
        }

        return redirect()->route('tools.show', $tool)
            ->with('success', 'Fotografía eliminada.');
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

    private function previewNextCalibrationFolio(Tool $tool): string
    {
        $nextNumber = 1;

        foreach ($tool->calibrations as $calibration) {
            if (! is_string($calibration->folio)) {
                continue;
            }

            if (preg_match('/-(\d+)$/', $calibration->folio, $matches) === 1) {
                $currentNumber = (int) $matches[1];
                if ($currentNumber >= $nextNumber) {
                    $nextNumber = $currentNumber + 1;
                }
            }
        }

        return sprintf('CAL-%s-%03d', $tool->economic_number, $nextNumber);
    }
}
