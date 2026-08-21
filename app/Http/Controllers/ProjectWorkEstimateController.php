<?php

namespace App\Http\Controllers;

use App\Models\ProjectWork;
use App\Models\ProjectWorkEstimate;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectWorkEstimateController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function store(Request $request, ProjectWork $projectWork): RedirectResponse
    {
        $estimate = $projectWork->estimates()->create(array_merge(
            $this->validatedData($request, $projectWork),
            ['created_by' => Auth::id()]
        ));

        $this->notification->send([
            'type' => 'ProjectWorkEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $estimate->id,
            'data' => 'registró la estimación ' . $estimate->estimate_number . ' en la obra "' . $projectWork->name . '".',
        ]);

        return redirect()->route('project_works.show', $projectWork)
            ->with('success', 'Estimación registrada correctamente.');
    }

    public function update(Request $request, ProjectWorkEstimate $estimate): RedirectResponse
    {
        $this->ensureOwner($estimate);

        $estimate->update($this->validatedData($request, $estimate->projectWork, $estimate));

        $this->notification->send([
            'type' => 'ProjectWorkEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $estimate->id,
            'data' => 'actualizó la estimación ' . $estimate->estimate_number . ' de la obra "' . $estimate->projectWork->name . '".',
        ]);

        return redirect()->route('project_works.show', $estimate->projectWork)
            ->with('success', 'Estimación actualizada correctamente.');
    }

    public function destroy(ProjectWorkEstimate $estimate): RedirectResponse
    {
        $this->ensureOwner($estimate);

        $projectWork = $estimate->projectWork;
        $estimateNumber = $estimate->estimate_number;
        $estimate->delete();

        $this->notification->send([
            'type' => 'ProjectWorkEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'destroy',
            'model_id' => $estimate->id,
            'data' => 'eliminó la estimación ' . $estimateNumber . ' de la obra "' . $projectWork->name . '".',
        ]);

        return redirect()->route('project_works.show', $projectWork)
            ->with('success', 'Estimación eliminada correctamente.');
    }

    private function validatedData(
        Request $request,
        ProjectWork $projectWork,
        ?ProjectWorkEstimate $estimate = null
    ): array {
        $estimateNumberRule = Rule::unique('project_work_estimates', 'estimate_number')
            ->where('project_work_id', $projectWork->id);

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
            'type' => ['required', Rule::in(ProjectWorkEstimate::TYPES)],
            'estimate_amount' => ['required', 'numeric', 'min:0'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in([
                ProjectWorkEstimate::STATUS_PENDING,
                ProjectWorkEstimate::STATUS_PAID,
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

    private function ensureOwner(ProjectWorkEstimate $estimate): void
    {
        abort_unless((int) Auth::id() === (int) $estimate->created_by, 403);
    }
}
