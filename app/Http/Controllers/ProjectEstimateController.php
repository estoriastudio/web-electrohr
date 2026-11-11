<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectEstimateController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function create(Project $project): View
    {
        return view('projects.estimates.create', compact('project'));
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validatedData($request, $project);

        $estimate = $project->estimates()->create(array_merge($data, [
            'created_by' => Auth::id(),
        ]));

        $this->notification->send([
            'type' => 'ProjectEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $estimate->id,
            'data' => 'registró la estimación '.$estimate->estimate_number.' en el proyecto "'.$project->name.'".',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Estimación registrada correctamente.');
    }

    public function update(Request $request, ProjectEstimate $estimate): RedirectResponse
    {
        $project = $estimate->project;
        $data = $this->validatedData($request, $project, $estimate);

        $estimate->update($data);

        $this->notification->send([
            'type' => 'ProjectEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $estimate->id,
            'data' => 'actualizó la estimación '.$estimate->estimate_number.' del proyecto "'.$project->name.'".',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Estimación actualizada correctamente.');
    }

    public function destroy(ProjectEstimate $estimate): RedirectResponse
    {
        $project = $estimate->project;
        $estimateNumber = $estimate->estimate_number;
        $documentPaths = array_merge(
            $estimate->files()->pluck('file_path')->all(),
            $estimate->invoices->flatMap->storedPaths()->all()
        );
        $estimate->delete();
        $this->deleteStoredPaths($documentPaths);

        $this->notification->send([
            'type' => 'ProjectEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'destroy',
            'model_id' => $estimate->id,
            'data' => 'eliminó la estimación '.$estimateNumber.' del proyecto "'.$project->name.'".',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Estimación eliminada correctamente.');
    }

    private function validatedData(
        Request $request,
        Project $project,
        ?ProjectEstimate $estimate = null
    ): array {
        $estimateNumberRule = Rule::unique('project_estimates', 'estimate_number')
            ->where('project_id', $project->id);

        if ($estimate) {
            $estimateNumberRule->ignore($estimate->id);
        }

        foreach ([
            'estimate_amount',
            'returned_retention_amount',
            'disfp_deduction',
            'apaee_deduction',
            'inc_retention_amount',
            'vat_retention_amount',
            'advance_amortization_amount',
            'advance_amortization_vat_amount',
            'funeral_expense_amount',
            'delay_penalty_amount',
        ] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => str_replace(',', '', $request->input($field))]);
            }
        }

        $validated = $request->validate([
            'estimate_number' => ['required', 'string', 'max:100', $estimateNumberRule],
            'estimate_date' => ['required', 'date'],
            'type' => ['required', Rule::in(ProjectEstimate::TYPES)],
            'estimate_amount' => ['required', 'numeric', 'min:0'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in([
                ProjectEstimate::STATUS_PENDING,
                ProjectEstimate::STATUS_PAID,
            ])],
            'returned_retention_amount' => ['nullable', 'numeric', 'min:0'],
            'disfp_deduction' => ['nullable', 'numeric', 'min:0'],
            'apaee_deduction' => ['nullable', 'numeric', 'min:0'],
            'inc_retention_amount' => ['nullable', 'numeric', 'min:0'],
            'vat_retention_amount' => ['nullable', 'numeric', 'min:0'],
            'advance_amortization_amount' => ['nullable', 'numeric', 'min:0'],
            'advance_amortization_vat_amount' => ['nullable', 'numeric', 'min:0'],
            'funeral_expense_amount' => ['nullable', 'numeric', 'min:0'],
            'delay_penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ([
            'returned_retention_amount',
            'disfp_deduction',
            'apaee_deduction',
            'inc_retention_amount',
            'vat_retention_amount',
            'advance_amortization_amount',
            'advance_amortization_vat_amount',
            'funeral_expense_amount',
            'delay_penalty_amount',
        ] as $field) {
            $validated[$field] = ($validated[$field] ?? null) === '' ? 0 : ($validated[$field] ?? 0);
        }

        return $validated;
    }

    private function deleteStoredPaths(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            if (Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        }
    }
}
