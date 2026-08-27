<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\ProjectWork;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectEstimateController extends Controller
{
    private const DOCUMENTS = [
        'invoice_pdf' => ['column' => 'invoice_pdf_path', 'extension' => 'pdf'],
        'invoice_xml' => ['column' => 'invoice_xml_path', 'extension' => 'xml'],
        'credit_note_pdf' => ['column' => 'credit_note_pdf_path', 'extension' => 'pdf'],
        'credit_note_xml' => ['column' => 'credit_note_xml_path', 'extension' => 'xml'],
        'spei_receipt' => ['column' => 'spei_receipt_path', 'extension' => 'pdf'],
    ];

    public function __construct(private NotificationService $notification) {}

    public function create(Project $project): View
    {
        $project->load('works');

        return view('projects.estimates.create', compact('project'));
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        [$data, $allocations] = $this->validatedData($request, $project);

        $estimate = DB::transaction(function () use ($project, $data, $allocations) {
            $estimate = $project->estimates()->create(array_merge($data, [
                'created_by' => Auth::id(),
            ]));

            $estimate->allocations()->createMany($allocations);

            return $estimate;
        });

        $this->notification->send([
            'type' => 'ProjectEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $estimate->id,
            'data' => 'registró la estimación ' . $estimate->estimate_number . ' en el proyecto "' . $project->name . '".',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Estimación registrada correctamente.');
    }

    public function update(Request $request, ProjectEstimate $estimate): RedirectResponse
    {
        $this->ensureOwner($estimate);

        $project = $estimate->project;
        [$data, $allocations] = $this->validatedData($request, $project, $estimate);

        DB::transaction(function () use ($estimate, $data, $allocations) {
            $estimate->update($data);
            $estimate->allocations()->delete();
            $estimate->allocations()->createMany($allocations);
        });

        $this->notification->send([
            'type' => 'ProjectEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $estimate->id,
            'data' => 'actualizó la estimación ' . $estimate->estimate_number . ' del proyecto "' . $project->name . '".',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Estimación actualizada correctamente.');
    }

    public function updateDocuments(Request $request, ProjectEstimate $estimate): RedirectResponse
    {
        $this->ensureOwner($estimate);
        $request->validateWithBag('estimateDocuments', $this->documentValidationRules());

        $uploadedPaths = [];
        $replacedPaths = [];

        try {
            [$documentData, $uploadedPaths, $replacedPaths] = $this->storeDocuments($request, $estimate, true);
            $estimate->update($documentData);
        } catch (\Throwable $exception) {
            $this->deleteStoredPaths($uploadedPaths);
            report($exception);

            return back()->withInput()->withErrors([
                'attachments' => 'No se pudo almacenar uno de los archivos en S3. Intenta nuevamente.',
            ], 'estimateDocuments');
        }

        $this->deleteStoredPaths($replacedPaths);

        $this->notification->send([
            'type' => 'ProjectEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $estimate->id,
            'data' => 'actualizó los archivos de la estimación ' . $estimate->estimate_number . ' del proyecto "' . $estimate->project->name . '".',
        ]);

        return redirect()->route('projects.show', $estimate->project)
            ->with('success', 'Archivos de la estimación actualizados correctamente.');
    }

    public function destroy(ProjectEstimate $estimate): RedirectResponse
    {
        $this->ensureOwner($estimate);

        $project = $estimate->project;
        $estimateNumber = $estimate->estimate_number;
        $documentPaths = $this->documentPaths($estimate);
        $estimate->delete();
        $this->deleteStoredPaths($documentPaths);

        $this->notification->send([
            'type' => 'ProjectEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'destroy',
            'model_id' => $estimate->id,
            'data' => 'eliminó la estimación ' . $estimateNumber . ' del proyecto "' . $project->name . '".',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Estimación eliminada correctamente.');
    }

    public function download(ProjectEstimate $estimate, string $document): mixed
    {
        abort_unless(array_key_exists($document, self::DOCUMENTS), 404);

        $definition = self::DOCUMENTS[$document];
        $path = $estimate->{$definition['column']};

        abort_unless($path && Storage::disk('s3')->exists($path), 404);

        return Storage::disk('s3')->download(
            $path,
            $estimate->estimate_number . '-' . $document . '.' . $definition['extension']
        );
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

        $request->merge([
            'allocations' => collect($request->input('allocations', []))
                ->map(function ($allocation) {
                    $allocation['estimate_amount'] = str_replace(',', '', $allocation['estimate_amount'] ?? '');

                    return $allocation;
                })
                ->all(),
        ]);

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
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.project_work_id' => ['required', 'integer', 'distinct', 'exists:project_works,id'],
            'allocations.*.estimate_amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $workIds = collect($validated['allocations'])
            ->pluck('project_work_id')
            ->map(fn ($workId) => (int) $workId)
            ->unique()
            ->values();

        if (ProjectWork::where('project_id', $project->id)->whereIn('id', $workIds)->count() !== $workIds->count()) {
            throw ValidationException::withMessages([
                'allocations' => 'Todas las obras seleccionadas deben pertenecer al proyecto.',
            ]);
        }

        $allocatedCents = collect($validated['allocations'])
            ->sum(fn ($allocation) => (int) round((float) $allocation['estimate_amount'] * 100));
        $estimateCents = (int) round((float) $validated['estimate_amount'] * 100);

        if ($allocatedCents !== $estimateCents) {
            throw ValidationException::withMessages([
                'allocations' => 'La suma de importes por obra debe coincidir con el importe de la estimación.',
            ]);
        }

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

        $allocations = collect($validated['allocations'])
            ->map(fn ($allocation) => [
                'project_work_id' => $allocation['project_work_id'],
                'estimate_amount' => $allocation['estimate_amount'],
            ])
            ->all();
        unset($validated['allocations']);

        return [$validated, $allocations];
    }

    private function ensureOwner(ProjectEstimate $estimate): void
    {
        abort_unless((int) Auth::id() === (int) $estimate->created_by, 403);
    }

    private function documentValidationRules(): array
    {
        return [
            'invoice_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'invoice_xml' => ['nullable', 'file', 'mimes:xml', 'max:10240'],
            'credit_note_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'credit_note_xml' => ['nullable', 'file', 'mimes:xml', 'max:10240'],
            'spei_receipt' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    private function storeDocuments(Request $request, ProjectEstimate $estimate, bool $replace = false): array
    {
        $documentData = [];
        $uploadedPaths = [];
        $replacedPaths = [];

        foreach (self::DOCUMENTS as $input => $definition) {
            if (! $request->hasFile($input)) {
                continue;
            }

            $fileName = $input . '-' . Str::uuid() . '.' . $definition['extension'];
            $path = Storage::disk('s3')->putFileAs(
                "projects/{$estimate->project_id}/estimates/{$estimate->id}",
                $request->file($input),
                $fileName
            );

            if (! $path) {
                throw new \RuntimeException("No se pudo guardar el archivo {$input}.");
            }

            $documentData[$definition['column']] = $path;
            $uploadedPaths[] = $path;

            if ($replace && $estimate->{$definition['column']}) {
                $replacedPaths[] = $estimate->{$definition['column']};
            }
        }

        return [$documentData, $uploadedPaths, $replacedPaths];
    }

    private function documentPaths(ProjectEstimate $estimate): array
    {
        return collect(self::DOCUMENTS)
            ->map(fn ($definition) => $estimate->{$definition['column']})
            ->filter()
            ->all();
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