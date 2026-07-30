<?php

namespace App\Http\Controllers;

use App\Models\ConceptCategory;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestChangeNote;
use App\Models\MaterialRequestItem;
use App\Models\MaterialRequestItemProjectWork;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaterialRequestController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');
        $scope  = $request->input('scope', 'mine'); // 'mine' | 'all'

        $query = MaterialRequest::with(['project', 'projectWorks', 'requestedBy', 'items.workQuantities', 'purchaseRequests.purchaseOrders'])
            ->orderByDesc('folio');

        if ($scope !== 'all') {
            $query->where('requested_by', Auth::id());
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhere('zone', 'like', "%{$search}%")
                  ->orWhere('supply_category', 'like', "%{$search}%")
                  ->orWhereHas('project', fn($p) => $p->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('projectWorks', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $materialRequests = $query->paginate(15)->withQueryString();
        $projects         = Project::where('status', 'active')->orderBy('name')->get();
        $categories       = ConceptCategory::where('type', 'materiales')->orderBy('name')->get();

        return view('material_requests.index', compact('materialRequests', 'projects', 'search', 'status', 'scope', 'categories'));
    }

    public function create()
    {
        return redirect()->route('material_requests.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'                => 'nullable|string|max:100',
            'project_id'          => 'required|exists:projects,id',
            'project_work_ids'    => 'required|array|min:1',
            'project_work_ids.*'  => 'exists:project_works,id',
            'zone'                => 'required|string|max:255',
            'delivery_address'    => 'required|string|max:255',
            'location_type'       => 'nullable|in:sitio,electrohr',
            'request_date'        => 'required|date',
            'need_date'           => 'required|date|after_or_equal:request_date',
            'concept_category_id' => 'nullable|exists:concept_categories,id',
            'supply_category'     => 'nullable|string|max:255',
        ]);

        // Derivar supply_category del nombre de la categoría seleccionada
        if (!empty($data['concept_category_id'])) {
            $cat = ConceptCategory::find($data['concept_category_id']);
            if ($cat) {
                $data['supply_category'] = $cat->name;
            }
        }

        $workIds = $data['project_work_ids'];
        unset($data['project_work_ids']);

        $data['requested_by']  = Auth::id();
        $data['status']        = 'pending';
        $data['location_type'] = $data['location_type'] ?? 'sitio';

        $mr = null;
        $maxAttempts = 5;

        // Reintentar ante colisión de índice único en folio para garantizar consecutivo.
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $mr = DB::transaction(function () use ($data, $workIds) {
                    $lastFolio = MaterialRequest::withTrashed()
                        ->orderByDesc('folio')
                        ->lockForUpdate()
                        ->value('folio') ?? 16499;
                    $data['folio'] = max($lastFolio + 1, 16500);

                    $mr = MaterialRequest::create($data);
                    $mr->projectWorks()->sync($workIds);

                    return $mr;
                });

                break;
            } catch (QueryException $e) {
                $isDuplicateFolio = (int) ($e->errorInfo[1] ?? 0) === 1062
                    && str_contains((string) $e->getMessage(), 'material_requests_folio_unique');

                if (! $isDuplicateFolio || $attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $mr->id,
            'type'         => 'material_request',
            'data'         => "SOLMAT #{$mr->folio} creada.",
        ]);

        return redirect()->route('material_requests.show', $mr)
                         ->with('success', "Solicitud de Material #{$mr->folio} creada correctamente.");
    }

    public function show(MaterialRequest $materialRequest)
    {
        $materialRequest->load([
            'project',
            'projectWorks',
            'requestedBy',
            'items.workQuantities.projectWork',
            'purchaseRequests',
            'changeNotes.requestedBy',
            'changeNotes.resolvedBy',
        ]);

        return view('material_requests.show', compact('materialRequest'));
    }

    public function changeRequestsPanel(Request $request): View
    {
        $search = trim($request->input('search', ''));
        $requestedBy = $request->input('requested_by', '');

        $baseQuery = MaterialRequestChangeNote::query()
            ->with([
                'requestedBy',
                'materialRequest.project',
                'materialRequest.projectWorks',
                'materialRequest.purchaseRequests',
            ])
            ->whereNull('resolved_at')
            ->whereHas('materialRequest', function ($query) {
                $query->whereNull('archived_at');
            });

        $query = clone $baseQuery;

        if ($search !== '') {
            $query->where(function ($noteQuery) use ($search) {
                $noteQuery->where('text', 'like', "%{$search}%")
                    ->orWhereHas('materialRequest', function ($materialRequestQuery) use ($search) {
                        $materialRequestQuery->where('folio', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('supply_category', 'like', "%{$search}%")
                            ->orWhere('zone', 'like', "%{$search}%")
                            ->orWhereHas('project', fn ($projectQuery) => $projectQuery->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        if ($requestedBy !== '') {
            $query->where('requested_by', $requestedBy);
        }

        $changeNotes = $query
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        $summaryQuery = clone $baseQuery;
        $pendingCount = (clone $summaryQuery)->count();
        $affectedSolmatCount = (clone $summaryQuery)->distinct('material_request_id')->count('material_request_id');
        $urgentCount = (clone $summaryQuery)
            ->whereHas('materialRequest', function ($materialRequestQuery) {
                $materialRequestQuery->whereDate('need_date', '<=', now()->addDays(5)->toDateString());
            })
            ->count();
        $withSolcomCount = (clone $summaryQuery)
            ->whereHas('materialRequest', function ($materialRequestQuery) {
                $materialRequestQuery->has('purchaseRequests');
            })
            ->count();

        $requesterUsers = User::whereIn('id', MaterialRequestChangeNote::query()
            ->whereNull('resolved_at')
            ->select('requested_by')
            ->distinct())
            ->orderBy('name')
            ->get();

        return view('material_request_changes.index', compact(
            'changeNotes',
            'search',
            'requestedBy',
            'pendingCount',
            'affectedSolmatCount',
            'urgentCount',
            'withSolcomCount',
            'requesterUsers'
        ));
    }

    public function edit(MaterialRequest $materialRequest)
    {
        $projects   = Project::where('status', 'active')->orderBy('name')->get();
        $categories = ConceptCategory::where('type', 'materiales')->orderBy('name')->get();

        return view('material_requests.edit', compact('materialRequest', 'projects', 'categories'));
    }

    public function update(Request $request, MaterialRequest $materialRequest)
    {
        $data = $request->validate([
            'code'                => 'nullable|string|max:100',
            'project_id'          => 'required|exists:projects,id',
            'project_work_ids'    => 'required|array|min:1',
            'project_work_ids.*'  => 'exists:project_works,id',
            'zone'                => 'required|string|max:255',
            'delivery_address'    => 'required|string|max:255',
            'location_type'       => 'nullable|in:sitio,electrohr',
            'request_date'        => 'required|date',
            'need_date'           => 'required|date|after_or_equal:request_date',
            'concept_category_id' => 'nullable|exists:concept_categories,id',
            'supply_category'     => 'nullable|string|max:255',
        ]);

        // Derivar supply_category del nombre de la categoría seleccionada
        if (!empty($data['concept_category_id'])) {
            $cat = ConceptCategory::find($data['concept_category_id']);
            if ($cat) {
                $data['supply_category'] = $cat->name;
            }
        }

        $workIds = $data['project_work_ids'];
        unset($data['project_work_ids']);

        $data['location_type'] = $data['location_type'] ?? 'sitio';

        $materialRequest->update($data);
        $materialRequest->projectWorks()->sync($workIds);

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $materialRequest->id,
            'type'         => 'material_request',
            'data'         => "SOLMAT #{$materialRequest->folio} actualizada.",
        ]);

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', "Solicitud de Material #{$materialRequest->folio} actualizada.");
    }

    public function destroy(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $validated = $request->validate([
            'deletion_comment' => 'required|string|max:1000',
        ]);

        $folio = $materialRequest->folio;

        $materialRequest->update(['deletion_comment' => $validated['deletion_comment']]);
        $materialRequest->delete();

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'delete',
            'model_id'     => 0,
            'type'         => 'material_request',
            'data'         => "SOLMAT #{$folio} eliminada. Motivo: " . $validated['deletion_comment'],
        ]);

        return redirect()->route('material_requests.index')
                         ->with('success', "Solicitud de Material #{$folio} eliminada.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ARCHIVO Y PAPELERA
    // ──────────────────────────────────────────────────────────────────────────

    public function archive(MaterialRequest $materialRequest): RedirectResponse
    {
        $materialRequest->update(['archived_at' => now()]);

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'archive',
            'model_id'     => $materialRequest->id,
            'type'         => 'material_request',
            'data'         => "SOLMAT #{$materialRequest->folio} archivada.",
        ]);

        return redirect()->route('material_requests.index')
                         ->with('success', "SOLMAT #{$materialRequest->folio} archivada.");
    }

    public function unarchive(MaterialRequest $materialRequest): RedirectResponse
    {
        $materialRequest->update(['archived_at' => null]);

        return redirect()->route('material_requests.archived')
                         ->with('success', "SOLMAT #{$materialRequest->folio} restaurada al listado activo.");
    }

    public function archived(Request $request): View
    {
        $search = trim($request->input('search', ''));

        $materialRequests = MaterialRequest::with(['project', 'projectWorks', 'requestedBy', 'purchaseRequests.purchaseOrders'])
            ->archived()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('folio', 'like', '%' . $search . '%')
                        ->orWhere('zone', 'like', '%' . $search . '%')
                        ->orWhere('supply_category', 'like', '%' . $search . '%')
                        ->orWhereHas('project', fn($p) => $p->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->orderBy('archived_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('material_requests.archive', compact('materialRequests', 'search'));
    }

    public function softDeleted(Request $request): View
    {
        $search = trim($request->input('search', ''));

        $materialRequests = MaterialRequest::onlyTrashed()
            ->with(['project', 'projectWorks', 'requestedBy', 'purchaseRequests.purchaseOrders'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('folio', 'like', '%' . $search . '%')
                        ->orWhere('zone', 'like', '%' . $search . '%')
                        ->orWhere('supply_category', 'like', '%' . $search . '%')
                        ->orWhereHas('project', fn($p) => $p->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->orderBy('deleted_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('material_requests.soft_deleted', compact('materialRequests', 'search'));
    }

    public function restore(int $id): RedirectResponse
    {
        $materialRequest = MaterialRequest::onlyTrashed()->findOrFail($id);
        $materialRequest->restore();

        return redirect()->route('material_requests.soft_deleted')
                         ->with('success', "SOLMAT #{$materialRequest->folio} restaurada.");
    }

    public function forceDestroy(int $id): RedirectResponse
    {
        $materialRequest = MaterialRequest::onlyTrashed()->findOrFail($id);
        $folio = $materialRequest->folio;

        $summary = DB::transaction(function () use ($materialRequest) {
            $materialRequest->loadMissing([
                'purchaseRequests' => fn ($query) => $query->withTrashed(),
                'linkedPurchaseRequests' => fn ($query) => $query->withTrashed(),
            ]);

            $purchaseRequestIds = $materialRequest->purchaseRequests
                ->pluck('id')
                ->merge($materialRequest->linkedPurchaseRequests->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $purchaseOrderIds = collect();

            if ($purchaseRequestIds->isNotEmpty()) {
                $purchaseOrderIds = PurchaseOrder::withTrashed()
                    ->whereIn('purchase_request_id', $purchaseRequestIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($purchaseOrderIds->isNotEmpty()) {
                    PurchaseOrder::withTrashed()
                        ->whereIn('id', $purchaseOrderIds)
                        ->get()
                        ->each
                        ->forceDelete();
                }

                PurchaseRequest::withTrashed()
                    ->whereIn('id', $purchaseRequestIds)
                    ->get()
                    ->each
                    ->forceDelete();
            }

            $materialRequest->forceDelete();

            return [
                'purchase_request_count' => $purchaseRequestIds->count(),
                'purchase_order_count' => $purchaseOrderIds->count(),
            ];
        });

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'force_destroy',
            'model_id'     => 0,
            'type'         => 'material_request',
            'data'         => "SOLMAT #{$folio} eliminada permanentemente con trazabilidad asociada (" .
                $summary['purchase_request_count'] . " SOLCOM y " . $summary['purchase_order_count'] . " OC).",
        ]);

        return redirect()->route('material_requests.soft_deleted')
                         ->with('success', "SOLMAT #{$folio} eliminada permanentemente junto con {$summary['purchase_request_count']} SOLCOM y {$summary['purchase_order_count']} OC vinculadas.");
    }

    public function storeItem(Request $request, MaterialRequest $materialRequest)
    {
        if (!in_array($materialRequest->status, ['pending', 'changes_requested'], true)) {
            abort(403, 'Esta SOLMAT ya no permite editar conceptos.');
        }

        $data = $request->validate([
            'concept_id'  => 'nullable|exists:concepts,id',
            'code'        => 'required|string|max:100',
            'description' => 'required|string|max:500',
            'unit'        => 'required|string|max:50',
            'work_quantities'           => 'required|array|min:1',
            'work_quantities.*.work_id'  => 'required|exists:project_works,id',
            'work_quantities.*.quantity' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],
            'work_quantities.*.is_committed' => 'nullable|boolean',
            'spec_file'   => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
        ]);

        $allowedWorkIds = $materialRequest->projectWorks()->pluck('project_works.id')->map(fn ($id) => (int) $id);
        $submittedWorkIds = collect($data['work_quantities'])->pluck('work_id')->map(fn ($id) => (int) $id)->values();

        if ($submittedWorkIds->isEmpty()) {
            return response()->json(['message' => 'Debes capturar al menos una cantidad por obra.'], 422);
        }

        if ($submittedWorkIds->diff($allowedWorkIds)->isNotEmpty()) {
            return response()->json(['message' => 'Todas las obras deben pertenecer a la SOLMAT.'], 422);
        }

        $totalQuantity = collect($data['work_quantities'])->sum(fn ($row) => (float) $row['quantity']);

        if ($totalQuantity <= 0) {
            return response()->json(['message' => 'La cantidad total debe ser mayor a cero.'], 422);
        }

        $item = DB::transaction(function () use ($request, $materialRequest, $data, $totalQuantity) {
            $item = MaterialRequestItem::create([
                'material_request_id' => $materialRequest->id,
                'concept_id'          => $data['concept_id'] ?? null,
                'code'                => $data['code'],
                'description'         => $data['description'],
                'unit'                => $data['unit'],
                'quantity'            => $totalQuantity,
            ]);

            if ($request->hasFile('spec_file')) {
                $path = $request->file('spec_file')->store('solmat_specs', 's3');
                $item->update(['file_path' => $path]);
            }

            foreach ($data['work_quantities'] as $row) {
                MaterialRequestItemProjectWork::create([
                    'material_request_item_id' => $item->id,
                    'project_work_id'          => (int) $row['work_id'],
                    'quantity'                 => (float) $row['quantity'],
                    'is_committed'             => (bool) ($row['is_committed'] ?? false),
                ]);
            }

            return $item->load('workQuantities.projectWork');
        });

        if ($request->wantsJson()) {
            return response()->json([
                'id'          => $item->id,
                'code'        => $item->code,
                'description' => $item->description,
                'unit'        => $item->unit,
                'quantity'    => $item->total_quantity,
                'work_quantities' => $item->workQuantities->map(fn ($row) => [
                    'work_id'   => $row->project_work_id,
                    'work_name' => $row->projectWork?->name,
                    'quantity'  => (float) $row->quantity,
                    'is_committed' => $row->is_committed,
                ]),
                'file_path'   => $item->file_path,
                'file_url'    => $item->file_path
                    ? Storage::disk('s3')->temporaryUrl($item->file_path, now()->addMinutes(30))
                    : null,
            ]);
        }

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', 'Concepto agregado.');
    }

    public function destroyItem(Request $request, MaterialRequest $materialRequest, MaterialRequestItem $item)
    {
        if (!in_array($materialRequest->status, ['pending', 'changes_requested'], true)) {
            abort(403, 'Esta SOLMAT ya no permite editar conceptos.');
        }

        if ((int) $item->material_request_id !== (int) $materialRequest->id) {
            abort(404);
        }

        if ($item->file_path) {
            Storage::disk('s3')->delete($item->file_path);
        }

        $item->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', 'Concepto eliminado.');
    }

    public function updateItem(Request $request, MaterialRequest $materialRequest, MaterialRequestItem $item)
    {
        if ((int) $item->material_request_id !== (int) $materialRequest->id) {
            abort(404);
        }

        if (!in_array($materialRequest->status, ['pending', 'changes_requested'], true)) {
            abort(403, 'Esta SOLMAT ya no permite editar conceptos.');
        }

        $item->loadMissing('workQuantities');

        $data = $request->validate([
            'work_quantities'            => 'required|array|min:1',
            'work_quantities.*.work_id'  => 'required|exists:project_works,id',
            'work_quantities.*.quantity' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],
            'work_quantities.*.is_committed' => 'nullable|boolean',
        ]);

        if ($item->workQuantities->isEmpty()) {
            return response()->json(['message' => 'Este concepto no tiene desglose por obra editable.'], 422);
        }

        $existingWorkIds = $item->workQuantities->pluck('project_work_id')->map(fn ($id) => (int) $id)->sort()->values();
        $submittedWorkIds = collect($data['work_quantities'])->pluck('work_id')->map(fn ($id) => (int) $id)->sort()->values();

        if ($existingWorkIds->diff($submittedWorkIds)->isNotEmpty() || $submittedWorkIds->diff($existingWorkIds)->isNotEmpty()) {
            return response()->json(['message' => 'No se pueden agregar ni quitar obras del desglose.'], 422);
        }

        $totalQuantity = collect($data['work_quantities'])->sum(fn ($row) => (float) $row['quantity']);

        if ($totalQuantity <= 0) {
            return response()->json(['message' => 'La cantidad total debe ser mayor a cero.'], 422);
        }

        DB::transaction(function () use ($item, $data, $totalQuantity) {
            $item->update(['quantity' => $totalQuantity]);

            foreach ($data['work_quantities'] as $row) {
                MaterialRequestItemProjectWork::where('material_request_item_id', $item->id)
                    ->where('project_work_id', (int) $row['work_id'])
                    ->update([
                        'quantity' => (float) $row['quantity'],
                        'is_committed' => (bool) ($row['is_committed'] ?? false),
                    ]);
            }
        });

        $item->refresh()->load('workQuantities.projectWork');

        if ($request->wantsJson()) {
            return response()->json([
                'id'             => $item->id,
                'quantity'       => $item->total_quantity,
                'work_quantities' => $item->workQuantities->map(fn ($row) => [
                    'work_id'   => $row->project_work_id,
                    'work_name' => $row->projectWork?->name,
                    'quantity'  => (float) $row->quantity,
                    'is_committed' => $row->is_committed,
                ]),
            ]);
        }

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', 'Concepto actualizado.');
    }

    public function storeObservation(Request $request, MaterialRequest $materialRequest)
    {
        $data = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $observations   = $materialRequest->observations ?? [];
        $observations[] = [
            'user_id'    => Auth::id(),
            'user_name'  => Auth::user()->name,
            'text'       => $data['text'],
            'created_at' => now()->toDateTimeString(),
        ];

        $materialRequest->update(['observations' => $observations]);

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', 'Observación agregada.');
    }

    public function updateObservation(Request $request, MaterialRequest $materialRequest, int $noteIndex)
    {
        $data = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $observations = $materialRequest->observations ?? [];

        if (!array_key_exists($noteIndex, $observations)) {
            return redirect()->route('material_requests.show', $materialRequest)
                             ->with('error', 'La observación no existe o ya fue eliminada.');
        }

        $observations[$noteIndex]['text'] = $data['text'];
        $observations[$noteIndex]['updated_at'] = now()->toDateTimeString();

        $materialRequest->update(['observations' => $observations]);

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', 'Observación actualizada.');
    }

    public function destroyObservation(MaterialRequest $materialRequest, int $noteIndex)
    {
        $observations = $materialRequest->observations ?? [];

        if (!array_key_exists($noteIndex, $observations)) {
            return redirect()->route('material_requests.show', $materialRequest)
                             ->with('error', 'La observación no existe o ya fue eliminada.');
        }

        unset($observations[$noteIndex]);
        $materialRequest->update(['observations' => array_values($observations)]);

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', 'Observación eliminada.');
    }

    public function sendToWarehouse(MaterialRequest $materialRequest)
    {
        $materialRequest->update([
            'status' => 'sent_to_warehouse',
            'sent_to_warehouse_at' => now(),
        ]);

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $materialRequest->id,
            'type'         => 'material_request',
            'data'         => "SOLMAT #{$materialRequest->folio} enviada a Almacén.",
        ]);

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', "SOLMAT #{$materialRequest->folio} enviada a Almacén correctamente.");
    }

    public function requestChanges(Request $request, MaterialRequest $materialRequest): RedirectResponse
    {
        $data = $request->validate([
            'change_text' => 'required|string|max:2000',
        ]);

        MaterialRequestChangeNote::create([
            'material_request_id' => $materialRequest->id,
            'requested_by'        => Auth::id(),
            'text'                => $data['change_text'],
        ]);

        $materialRequest->update(['status' => 'changes_requested']);

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $materialRequest->id,
            'type'         => 'material_request',
            'data'         => "SOLMAT #{$materialRequest->folio}: cambios solicitados por " . Auth::user()->name . '.',
        ]);

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', 'Solicitud de cambios registrada. La SOLMAT queda en ajustes para el equipo solicitante.');
    }

    public function resolveChangeNote(MaterialRequest $materialRequest, MaterialRequestChangeNote $changeNote): RedirectResponse
    {
        if ((int) $changeNote->material_request_id !== (int) $materialRequest->id) {
            abort(404);
        }

        $changeNote->update([
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
        ]);

        $unresolvedCount = $materialRequest->changeNotes()->whereNull('resolved_at')->count();
        if ($unresolvedCount === 0) {
            $materialRequest->update(['status' => 'pending']);

            app(NotificationService::class)->send([
                'action_by'    => Auth::id(),
                'model_action' => 'update',
                'model_id'     => $materialRequest->id,
                'type'         => 'material_request',
                'data'         => "SOLMAT #{$materialRequest->folio}: cambios resueltos, lista para reenviar a Almacén.",
            ]);
        }

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', 'Solicitud de cambio marcada como resuelta.');
    }

    public function jsonByFolio(Request $request)
    {
        $folio = $request->input('folio');

        $mr = MaterialRequest::with(['project', 'projectWorks', 'items.concept.category.users'])
            ->where('folio', $folio)
            ->first();

        if (! $mr) {
            return response()->json(['error' => 'No se encontró la Solicitud de Material con ese folio.'], 404);
        }

        // Determinar comprador sugerido desde la categoría del primer concepto con categoría asignada
        $suggestedBuyerId   = null;
        $suggestedBuyerName = null;

        $categoryId = $mr->items
            ->whereNotNull('concept_id')
            ->map(fn($i) => optional($i->concept)->concept_category_id)
            ->filter()
            ->first();

        if ($categoryId) {
            $buyer = \App\Models\ConceptCategory::find($categoryId)
                ?->users()
                ->orderBy('name')
                ->first();

            if ($buyer) {
                $suggestedBuyerId   = $buyer->id;
                $suggestedBuyerName = $buyer->name;
            }
        }

        return response()->json([
            'id'                   => $mr->id,
            'folio'                => $mr->folio,
            'project_id'           => $mr->project_id,
            'project_name'         => $mr->project?->name,
            'concept_category_id'  => $mr->concept_category_id,
            'project_works'        => $mr->projectWorks->map(fn($pw) => [
                'id'   => $pw->id,
                'name' => $pw->name,
            ]),
            'zone'                 => $mr->zone,
            'delivery_address'     => $mr->delivery_address,
            'supply_category'      => $mr->supply_category,
            'suggested_buyer_id'   => $suggestedBuyerId,
            'suggested_buyer_name' => $suggestedBuyerName,
            'items'                => $mr->items->map(fn($i) => [
                'id'          => $i->id,
                'code'        => $i->code,
                'description' => $i->description,
                'unit'        => $i->unit,
                'quantity'    => $i->quantity,
            ]),
        ]);
    }

    // PDF
    public function downloadPdf(MaterialRequest $materialRequest): \Illuminate\Http\Response
    {
        $materialRequest->load(['project', 'projectWorks', 'requestedBy', 'items']);

        $pdf = Pdf::loadView('material_requests.pdf', compact('materialRequest'))
            ->setPaper('letter', 'portrait');

        $filename = 'SOLMAT-' . ($materialRequest->folio ?? $materialRequest->id) . '.pdf';

        return $pdf->download($filename);
    }
}
