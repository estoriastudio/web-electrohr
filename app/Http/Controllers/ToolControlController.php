<?php

namespace App\Http\Controllers;

use App\Models\ProjectWork;
use App\Models\Tool;
use App\Models\ToolControl;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ToolControlController extends Controller
{
    public function __construct(private NotificationService $notification)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', '');
        $loanType = (string) $request->input('loan_type', '');

        $controls = ToolControl::with(['tool', 'projectWork.project'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('tool', function ($toolQuery) use ($search) {
                        $toolQuery->where('economic_number', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    })
                    ->orWhereHas('projectWork', function ($workQuery) use ($search) {
                        $workQuery->where('name', 'like', "%{$search}%")
                            ->orWhereHas('project', fn ($projectQuery) => $projectQuery->where('name', 'like', "%{$search}%"));
                    })
                    ->orWhere('responsible', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($loanType, fn ($query) => $query->where('loan_type', $loanType))
            ->latest('checkout_date')
            ->paginate(20)
            ->withQueryString();

        $tools = Tool::whereIn('status', ['active', 'in_service'])
            ->orderBy('economic_number')
            ->orderBy('description')
            ->get(['id', 'economic_number', 'name', 'description']);

        $projectWorks = ProjectWork::with('project')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'project_id', 'name']);

        return view('tools.control', compact('controls', 'tools', 'projectWorks', 'search', 'status', 'loanType'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tool_id' => ['required', 'exists:tools,id'],
            'project_work_id' => ['required', 'exists:project_works,id'],
            'responsible' => ['required', 'string', 'max:150'],
            'loan_type' => ['required', Rule::in(['fixed', 'provisional'])],
            'checkout_date' => ['required', 'date'],
            'review_date' => ['nullable', 'date', 'after_or_equal:checkout_date'],
            'observations_text' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'closed'])],
        ]);

        $toolControl = ToolControl::create([
            'tool_id' => $validated['tool_id'],
            'project_work_id' => $validated['project_work_id'],
            'responsible' => $validated['responsible'],
            'loan_type' => $validated['loan_type'],
            'checkout_date' => $validated['checkout_date'],
            'review_date' => $validated['review_date'] ?? null,
            'observations' => $this->normalizeObservations($validated['observations_text'] ?? null),
            'status' => $validated['status'],
        ]);

        $this->notification->send([
            'type' => 'ToolControl',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $toolControl->id,
            'data' => 'created tool control for tool ID #' . $toolControl->tool_id . '.',
        ]);

        return redirect()->back()
            ->with('success', 'Control de uso de herramienta creado correctamente.');
    }

    public function update(Request $request, ToolControl $toolControl): RedirectResponse
    {
        $validated = $request->validate([
            'project_work_id' => ['required', 'exists:project_works,id'],
            'responsible' => ['required', 'string', 'max:150'],
            'loan_type' => ['required', Rule::in(['fixed', 'provisional'])],
            'checkout_date' => ['required', 'date'],
            'review_date' => ['nullable', 'date', 'after_or_equal:checkout_date'],
            'observations_text' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'closed'])],
        ]);

        $toolControl->update([
            'project_work_id' => $validated['project_work_id'],
            'responsible' => $validated['responsible'],
            'loan_type' => $validated['loan_type'],
            'checkout_date' => $validated['checkout_date'],
            'review_date' => $validated['review_date'] ?? null,
            'observations' => $this->normalizeObservations($validated['observations_text'] ?? null),
            'status' => $validated['status'],
        ]);

        $this->notification->send([
            'type' => 'ToolControl',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $toolControl->id,
            'data' => 'updated tool control #' . $toolControl->id . '.',
        ]);

        return redirect()->back()
            ->with('success', 'Control de uso de herramienta actualizado correctamente.');
    }

    public function destroy(ToolControl $toolControl): RedirectResponse
    {
        $controlId = $toolControl->id;
        $toolControl->delete();

        $this->notification->send([
            'type' => 'ToolControl',
            'action_by' => Auth::id(),
            'model_action' => 'destroy',
            'model_id' => $controlId,
            'data' => 'deleted tool control #' . $controlId . '.',
        ]);

        return redirect()->back()
            ->with('success', 'Control de uso de herramienta eliminado correctamente.');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('tools.index');
    }

    public function show(ToolControl $toolControl): RedirectResponse
    {
        return redirect()->route('tools.index');
    }

    public function edit(ToolControl $toolControl): RedirectResponse
    {
        return redirect()->route('tools.index');
    }

    private function normalizeObservations(?string $observationsText): ?array
    {
        if (! $observationsText) {
            return null;
        }

        return [[
            'note' => trim($observationsText),
            'created_at' => now()->toDateTimeString(),
            'created_by' => Auth::id(),
        ]];
    }
}
