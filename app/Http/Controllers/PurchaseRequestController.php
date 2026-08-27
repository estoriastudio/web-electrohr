<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestChangeNote;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PurchaseRequestController extends Controller
{
    private const SOLMAT_PILE_SELECTION_SESSION_KEY = 'warehouse.solmat_pile.selection';

    public function changeRequestsPanel(Request $request)
    {
        $search = trim($request->input('search', ''));
        $assignedTo = $request->input('assigned_to', '');
        $requestedBy = $request->input('requested_by', '');

        $baseQuery = PurchaseRequestChangeNote::query()
            ->with([
                'requestedBy',
                'purchaseRequest.project',
                'purchaseRequest.projectWork',
                'purchaseRequest.projectWorks',
                'purchaseRequest.materialRequest',
                'purchaseRequest.assignedTo',
            ])
            ->whereNull('resolved_at')
            ->whereHas('purchaseRequest', function ($query) {
                $query->whereNull('archived_at');
            });

        $query = clone $baseQuery;

        if ($search !== '') {
            $query->where(function ($noteQuery) use ($search) {
                $noteQuery->where('text', 'like', "%{$search}%")
                    ->orWhereHas('purchaseRequest', function ($purchaseRequestQuery) use ($search) {
                        $purchaseRequestQuery->where('folio', 'like', "%{$search}%")
                            ->orWhere('short_description', 'like', "%{$search}%")
                            ->orWhere('zone', 'like', "%{$search}%")
                            ->orWhereHas('project', fn ($projectQuery) => $projectQuery->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        if ($assignedTo !== '') {
            $query->whereHas('purchaseRequest', function ($purchaseRequestQuery) use ($assignedTo) {
                $purchaseRequestQuery->where('assigned_to', $assignedTo);
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
        $affectedSolcomCount = (clone $summaryQuery)->distinct('purchase_request_id')->count('purchase_request_id');
        $urgentCount = (clone $summaryQuery)
            ->whereHas('purchaseRequest', function ($purchaseRequestQuery) {
                $purchaseRequestQuery->whereDate('need_date', '<=', now()->addDays(5)->toDateString());
            })
            ->count();
        $unassignedCount = (clone $summaryQuery)
            ->whereHas('purchaseRequest', function ($purchaseRequestQuery) {
                $purchaseRequestQuery->whereNull('assigned_to');
            })
            ->count();

        $purchasingUsers = User::role(['admin', 'Orden de compra'])->orderBy('name')->get();
        $requesterUsers = User::whereIn('id', PurchaseRequestChangeNote::query()
            ->whereNull('resolved_at')
            ->select('requested_by')
            ->distinct())
            ->orderBy('name')
            ->get();

        return view('purchase_request_changes.index', compact(
            'changeNotes',
            'search',
            'assignedTo',
            'requestedBy',
            'pendingCount',
            'affectedSolcomCount',
            'urgentCount',
            'unassignedCount',
            'purchasingUsers',
            'requesterUsers'
        ));
    }

    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');

        $query = PurchaseRequest::with(['project', 'projectWork', 'requestedBy', 'materialRequest', 'purchaseOrders'])
            ->whereNull('archived_at')
            ->orderByDesc('folio');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhere('zone', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%")
                  ->orWhereHas('project', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $purchaseRequests = $query->paginate(15)->withQueryString();
        $nextFolio        = max((PurchaseRequest::withTrashed()->max('folio') ?? 17999), 17999) + 1;
        $projects         = Project::where('status', 'active')->orderBy('name')->get();
        $purchasingUsers  = User::role(['admin', 'Orden de compra'])->orderBy('name')->get();

        return view('purchase_requests.index', compact('purchaseRequests', 'nextFolio', 'projects', 'search', 'status', 'purchasingUsers'));
    }

    public function create()
    {
        return redirect()->route('purchase_requests.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'folio'               => 'required|integer|min:18000|unique:purchase_requests,folio',
            'code'                => 'nullable|string|max:100',
            'material_request_id' => 'nullable|exists:material_requests,id',
            'material_request_ids' => 'nullable|array|min:1',
            'material_request_ids.*' => 'integer|exists:material_requests,id',
            'selected_item_keys' => 'nullable|array|min:1',
            'selected_item_keys.*' => 'required|string|max:255',
            'project_id'          => 'required|exists:projects,id',
            'project_work_id'     => 'nullable|exists:project_works,id',
            'project_work_ids'    => 'nullable|array|min:1',
            'project_work_ids.*'  => 'exists:project_works,id',
            'zone'                => 'required|string|max:255',
            'delivery_address'    => 'required|string|max:255',
            'short_description'   => 'required|string|max:255',
            'request_date'        => 'required|date',
            'need_date'           => 'required|date|after_or_equal:request_date',
            'assigned_to'         => 'nullable|exists:users,id',
            'clear_solmat_pile_selection_on_success' => 'nullable|boolean',
        ]);

        $clearSolmatPileSelectionOnSuccess = (bool) ($data['clear_solmat_pile_selection_on_success'] ?? false);
        unset($data['clear_solmat_pile_selection_on_success']);

        $selectedWorkIds = collect($data['project_work_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($selectedWorkIds->isEmpty() && !empty($data['project_work_id'])) {
            $selectedWorkIds = collect([(int) $data['project_work_id']]);
        }

        if ($selectedWorkIds->isEmpty()) {
            throw ValidationException::withMessages([
                'project_work_ids' => 'Debes seleccionar al menos una obra.',
            ]);
        }

        $sourceMaterialRequests = collect();
        $selectedItemKeys = collect();

        $materialRequestIds = collect($data['material_request_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($materialRequestIds->isEmpty() && !empty($data['material_request_id'])) {
            $materialRequestIds = collect([(int) $data['material_request_id']]);
        }

        if ($materialRequestIds->isNotEmpty()) {
            $sourceMaterialRequests = $this->loadSourceMaterialRequestsByIds($materialRequestIds);
            $data['project_id'] = (int) $sourceMaterialRequests->first()->project_id;
        }

        $validWorksCount = ProjectWork::where('project_id', (int) $data['project_id'])
            ->whereIn('id', $selectedWorkIds)
            ->count();

        if ($validWorksCount !== $selectedWorkIds->count()) {
            throw ValidationException::withMessages([
                'project_work_ids' => 'Todas las obras deben pertenecer al proyecto seleccionado.',
            ]);
        }

        if ($sourceMaterialRequests->isNotEmpty()) {
            $allowedWorkIds = $sourceMaterialRequests
                ->flatMap(fn ($materialRequest) => $materialRequest->projectWorks->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $outside = $selectedWorkIds->diff($allowedWorkIds);
            if ($outside->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'project_work_ids' => 'Solo puedes seleccionar obras vinculadas a las SOLMAT origen.',
                ]);
            }

            $availableItems = $this->aggregateMaterialRequestItems($sourceMaterialRequests, $selectedWorkIds);
            $selectedItemKeys = collect($data['selected_item_keys'] ?? [])
                ->filter()
                ->unique()
                ->values();

            if ($selectedItemKeys->isEmpty()) {
                throw ValidationException::withMessages([
                    'selected_item_keys' => 'Debes seleccionar al menos un concepto disponible.',
                ]);
            }

            if ($selectedItemKeys->diff($availableItems->pluck('item_key'))->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'selected_item_keys' => 'La selección de conceptos no es válida para las obras elegidas.',
                ]);
            }
        }

        $data['project_work_id'] = $selectedWorkIds->first();
        unset($data['project_work_ids']);
        unset($data['material_request_ids']);
        unset($data['selected_item_keys']);

        $data['requested_by'] = Auth::id();
        $data['status']       = 'pending';

        if ($sourceMaterialRequests->isNotEmpty()) {
            $data['material_request_id'] = (int) $sourceMaterialRequests->first()->id;
        }

        $pr = DB::transaction(function () use ($data, $selectedWorkIds, $sourceMaterialRequests, $selectedItemKeys) {
            $purchaseRequest = PurchaseRequest::create($data);
            $purchaseRequest->projectWorks()->sync($selectedWorkIds->all());

            if ($sourceMaterialRequests->isNotEmpty()) {
                $aggregatedItems = $this->aggregateMaterialRequestItems($sourceMaterialRequests, $selectedWorkIds)
                    ->whereIn('item_key', $selectedItemKeys)
                    ->values();

                foreach ($aggregatedItems as $item) {
                    if ((float) $item['resolved_quantity'] <= 0) {
                        continue;
                    }

                    PurchaseRequestItem::create([
                        'purchase_request_id' => $purchaseRequest->id,
                        'concept_id'          => $item['concept_id'],
                        'code'                => $item['code'],
                        'description'         => $item['description'],
                        'unit'                => $item['unit'],
                        'requested_quantity'  => $item['resolved_quantity'],
                        'purchase_quantity'   => $item['resolved_quantity'],
                        'file_path'           => $item['file_path'],
                    ]);
                }

                // Si no se eligió un comprador manualmente, sugerir desde la primera categoría encontrada.
                if (empty($purchaseRequest->assigned_to)) {
                    $categoryId = $sourceMaterialRequests
                        ->flatMap(fn ($materialRequest) => $materialRequest->items)
                        ->whereNotNull('concept_id')
                        ->map(fn($item) => optional($item->concept)->concept_category_id)
                        ->filter()
                        ->first();

                    if ($categoryId) {
                        $suggestedUser = \App\Models\ConceptCategory::find($categoryId)
                            ?->users()
                            ->orderBy('name')
                            ->first();

                        if ($suggestedUser) {
                            $purchaseRequest->update(['assigned_to' => $suggestedUser->id]);
                        }
                    }
                }

                // Heredar observaciones de la primera SOLMAT para mantener compatibilidad del flujo actual.
                $firstMaterialRequest = $sourceMaterialRequests->first();
                if (!empty($firstMaterialRequest?->observations) && empty($purchaseRequest->observations)) {
                    $purchaseRequest->update(['observations' => $firstMaterialRequest->observations]);
                }

                $contributingMaterialRequestIds = $aggregatedItems
                    ->flatMap(fn ($item) => $item['source_material_request_ids'])
                    ->unique()
                    ->values();

                $sourceMaterialRequests
                    ->whereIn('id', $contributingMaterialRequestIds)
                    ->each(function ($materialRequest) {
                    $materialRequest->update(['status' => 'linked']);
                });

                $purchaseRequest->materialRequests()->sync($contributingMaterialRequestIds->all());
            }

            return $purchaseRequest;
        });

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $pr->id,
            'type'         => 'purchase_request',
            'data'         => "SOLCOM #{$pr->folio} creada.",
        ]);

        if ($clearSolmatPileSelectionOnSuccess) {
            $request->session()->forget(self::SOLMAT_PILE_SELECTION_SESSION_KEY);
        }

        return redirect()->route('purchase_requests.show', $pr)
                         ->with('success', "Solicitud de Compra #{$pr->folio} creada correctamente.");
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load([
            'project',
            'projectWork',
            'projectWorks',
            'requestedBy',
            'assignedTo',
            'items',
            'purchaseOrders',
            'materialRequest.items.workQuantities.projectWork',
            'materialRequests.items.workQuantities.projectWork',
            'changeNotes.requestedBy',
            'changeNotes.resolvedBy',
        ]);

        $selectedWorkIds = $purchaseRequest->projectWorks
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($selectedWorkIds->isEmpty() && !empty($purchaseRequest->project_work_id)) {
            $selectedWorkIds = collect([(int) $purchaseRequest->project_work_id]);
        }

        $sourceMaterialRequests = $purchaseRequest->materialRequests;
        if ($sourceMaterialRequests->isEmpty() && $purchaseRequest->materialRequest) {
            $sourceMaterialRequests = collect([$purchaseRequest->materialRequest]);
        }

        $sourceItems = $sourceMaterialRequests
            ->flatMap(fn ($materialRequest) => $materialRequest->items)
            ->values();

        $sourceItemsByKey = $sourceItems->groupBy(function ($materialItem) {
            return $this->materialItemGroupingKey($materialItem);
        });

        $purchaseRequest->items->each(function ($purchaseItem) use ($sourceItemsByKey, $selectedWorkIds) {
            $groupKey = $this->purchaseItemGroupingKey($purchaseItem);
            $matchingSourceItems = $sourceItemsByKey->get($groupKey, collect());

            if ($matchingSourceItems->isEmpty()) {
                $purchaseItem->setAttribute('selected_work_breakdown', []);
                return;
            }

            $workTotals = [];

            $matchingSourceItems->each(function ($sourceItem) use ($selectedWorkIds, &$workTotals) {
                if ($sourceItem->workQuantities->isEmpty()) {
                    return;
                }

                $sourceItem->workQuantities->each(function ($workQuantity) use ($selectedWorkIds, &$workTotals) {
                    $workId = (int) $workQuantity->project_work_id;

                    if ($selectedWorkIds->isNotEmpty() && !$selectedWorkIds->contains($workId)) {
                        return;
                    }

                    if (!isset($workTotals[$workId])) {
                        $workTotals[$workId] = [
                            'work_id' => (string) $workId,
                            'work_name' => $workQuantity->projectWork?->name,
                            'quantity' => 0,
                        ];
                    }

                    $workTotals[$workId]['quantity'] += (float) $workQuantity->quantity;
                });
            });

            $breakdown = collect($workTotals)
                ->sortBy('work_name')
                ->map(function ($row) {
                    $row['quantity'] = number_format((float) $row['quantity'], 2, '.', '');
                    return $row;
                })
                ->values()
                ->all();

            $purchaseItem->setAttribute('selected_work_breakdown', $breakdown);
        });

        $history         = Notification::where('type', 'purchase_request')
                                       ->where('model_id', $purchaseRequest->id)
                                       ->with('user')
                                       ->orderBy('created_at')
                                       ->get();

        $purchasingUsers = User::role('Orden de compra')->orderBy('name')->get();

        $solmatRequester = $purchaseRequest->materialRequest?->requestedBy?->name
            ?? $purchaseRequest->materialRequest?->requested_by
            ?? $purchaseRequest->elaborated_by
            ?? '—';

        return view('purchase_requests.show', compact('purchaseRequest', 'history', 'purchasingUsers', 'solmatRequester'));
    }

    public function edit(PurchaseRequest $purchaseRequest)
    {
        $projects = Project::where('status', 'active')->orderBy('name')->get();

        return view('purchase_requests.edit', compact('purchaseRequest', 'projects'));
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate([
            'code'              => 'nullable|string|max:100',
            'project_id'        => 'required|exists:projects,id',
            'project_work_id'   => 'nullable|exists:project_works,id',
            'project_work_ids'  => 'nullable|array|min:1',
            'project_work_ids.*'=> 'exists:project_works,id',
            'zone'              => 'required|string|max:255',
            'delivery_address'  => 'required|string|max:255',
            'short_description' => 'required|string|max:255',
            'request_date'      => 'required|date',
            'need_date'         => 'required|date|after_or_equal:request_date',
        ]);

        $selectedWorkIds = collect($data['project_work_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($selectedWorkIds->isEmpty() && !empty($data['project_work_id'])) {
            $selectedWorkIds = collect([(int) $data['project_work_id']]);
        }

        if ($selectedWorkIds->isEmpty()) {
            throw ValidationException::withMessages([
                'project_work_ids' => 'Debes seleccionar al menos una obra.',
            ]);
        }

        $validWorksCount = ProjectWork::where('project_id', (int) $data['project_id'])
            ->whereIn('id', $selectedWorkIds)
            ->count();

        if ($validWorksCount !== $selectedWorkIds->count()) {
            throw ValidationException::withMessages([
                'project_work_ids' => 'Todas las obras deben pertenecer al proyecto seleccionado.',
            ]);
        }

        if ($purchaseRequest->materialRequest) {
            $allowedWorkIds = $purchaseRequest->materialRequest
                ->projectWorks()
                ->pluck('project_works.id')
                ->map(fn ($id) => (int) $id);

            $outside = $selectedWorkIds->diff($allowedWorkIds);
            if ($outside->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'project_work_ids' => 'Solo puedes seleccionar obras vinculadas a la SOLMAT origen.',
                ]);
            }
        }

        $data['project_work_id'] = $selectedWorkIds->first();
        unset($data['project_work_ids']);

        $purchaseRequest->update($data);
        $purchaseRequest->projectWorks()->sync($selectedWorkIds->all());

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseRequest->id,
            'type'         => 'purchase_request',
            'data'         => "SOLCOM #{$purchaseRequest->folio} actualizada.",
        ]);

        return redirect()->route('purchase_requests.show', $purchaseRequest)
                         ->with('success', "Solicitud de Compra #{$purchaseRequest->folio} actualizada.");
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $validated = request()->validate([
            'deletion_comment' => 'required|string|max:1000',
        ]);

        $folio = $purchaseRequest->folio;

        $purchaseRequest->update([
            'deletion_comment' => $validated['deletion_comment'],
        ]);

        $purchaseRequest->delete();

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => 0,
            'type'         => 'purchase_request',
            'data'         => "SOLCOM #{$folio} eliminada. Motivo: {$validated['deletion_comment']}",
        ]);

        return redirect()->route('purchase_requests.index')
                         ->with('success', "Solicitud de Compra #{$folio} eliminada.");
    }

    public function storeItem(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate([
            'concept_id'         => 'nullable|exists:concepts,id',
            'code'               => 'required|string|max:100',
            'description'        => 'required|string|max:500',
            'unit'               => 'required|string|max:50',
            'purchase_quantity'  => 'required|numeric|min:0|decimal:0,4',
            'spec_file'          => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
        ]);

        $item = DB::transaction(function () use ($request, $purchaseRequest, $data) {
            $item = PurchaseRequestItem::create([
                'purchase_request_id' => $purchaseRequest->id,
                'concept_id'          => $data['concept_id'] ?? null,
                'code'                => $data['code'],
                'description'         => $data['description'],
                'unit'                => $data['unit'],
                'requested_quantity'  => 0,
                'purchase_quantity'   => $data['purchase_quantity'],
            ]);

            if ($request->hasFile('spec_file')) {
                $path = $request->file('spec_file')->store('solcom_specs', 's3');
                $item->update(['file_path' => $path]);
            }

            return $item->fresh();
        });

        if ($request->wantsJson()) {
            return response()->json([
                'id'                 => $item->id,
                'code'               => $item->code,
                'description'        => $item->description,
                'unit'               => $item->unit,
                'requested_quantity' => $item->requested_quantity,
                'purchase_quantity'  => $item->purchase_quantity,
                'file_path'          => $item->file_path,
                'file_url'           => $item->file_path
                    ? Storage::disk('s3')->temporaryUrl($item->file_path, now()->addMinutes(30))
                    : null,
            ]);
        }

        return redirect()->route('purchase_requests.show', $purchaseRequest)
                         ->with('success', 'Concepto agregado.');
    }

    public function updateItem(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestItem $item)
    {
        $data = $request->validate([
            'purchase_quantity' => 'required|numeric|min:0|decimal:0,4',
        ]);

        $item->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'id'                => $item->id,
                'purchase_quantity' => $item->purchase_quantity,
            ]);
        }

        return back()->with('success', 'Cantidad actualizada.');
    }

    public function destroyItem(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestItem $item)
    {
        $item->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('purchase_requests.show', $purchaseRequest)
                         ->with('success', 'Concepto eliminado.');
    }

    public function storeObservation(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $observations   = $purchaseRequest->observations ?? [];
        $observations[] = [
            'user_id'    => Auth::id(),
            'user_name'  => Auth::user()->name,
            'text'       => $data['text'],
            'created_at' => now()->toDateTimeString(),
        ];

        $purchaseRequest->update(['observations' => $observations]);

        return redirect()->route('purchase_requests.show', $purchaseRequest)
                         ->with('success', 'Observación agregada.');
    }

    public function updateObservation(Request $request, PurchaseRequest $purchaseRequest, int $noteIndex)
    {
        $data = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $observations = $purchaseRequest->observations ?? [];

        if (!array_key_exists($noteIndex, $observations)) {
            return redirect()->route('purchase_requests.show', $purchaseRequest)
                             ->with('error', 'La observación no existe o ya fue eliminada.');
        }

        $observations[$noteIndex]['text'] = $data['text'];
        $observations[$noteIndex]['updated_at'] = now()->toDateTimeString();

        $purchaseRequest->update(['observations' => $observations]);

        return redirect()->route('purchase_requests.show', $purchaseRequest)
                         ->with('success', 'Observación actualizada.');
    }

    public function destroyObservation(PurchaseRequest $purchaseRequest, int $noteIndex)
    {
        $observations = $purchaseRequest->observations ?? [];

        if (!array_key_exists($noteIndex, $observations)) {
            return redirect()->route('purchase_requests.show', $purchaseRequest)
                             ->with('error', 'La observación no existe o ya fue eliminada.');
        }

        unset($observations[$noteIndex]);
        $purchaseRequest->update(['observations' => array_values($observations)]);

        return redirect()->route('purchase_requests.show', $purchaseRequest)
                         ->with('success', 'Observación eliminada.');
    }

    /**
     * Devuelve los ítems de una SOLCOM en JSON para precarga en el modal de creación de OC.
     */
    public function itemsJson(PurchaseRequest $purchaseRequest): \Illuminate\Http\JsonResponse
    {
        $purchaseRequest->load('items.concept');

        $items = $purchaseRequest->items->map(function ($item) {
            return [
                'concept_id'   => $item->concept_id,
                'description'  => $item->description,
                'unit'         => $item->unit,
                'quantity'     => (float) $item->purchase_quantity,
                'unit_price'   => (float) ($item->concept?->unit_price ?? 0),
            ];
        });

        return response()->json([
            'id'    => $purchaseRequest->id,
            'folio' => $purchaseRequest->folio,
            'items' => $items,
        ]);
    }

    /**
     * Busca una SOLCOM por número de folio y devuelve sus ítems en JSON.
     * GET /solicitudes-compra/buscar-por-folio?folio=X
     */
    public function itemsJsonByFolio(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $folio = $request->query('folio');

        if (!$folio || !is_numeric($folio)) {
            return response()->json(['error' => 'Folio requerido'], 422);
        }

        $pr = PurchaseRequest::where('folio', (int) $folio)->first();

        if (!$pr) {
            return response()->json(['error' => 'SOLCOM no encontrada'], 404);
        }

        $pr->load('items.concept');

        $items = $pr->items->map(function ($item) {
            return [
                'concept_id'   => $item->concept_id,
                'description'  => $item->description,
                'unit'         => $item->unit,
                'quantity'     => (float) $item->purchase_quantity,
                'unit_price'   => (float) ($item->concept?->unit_price ?? 0),
            ];
        });

        return response()->json([
            'id'    => $pr->id,
            'folio' => $pr->folio,
            'items' => $items,
        ]);
    }

    // PDF
    public function downloadPdf(PurchaseRequest $purchaseRequest): \Illuminate\Http\Response
    {
        $purchaseRequest->load(['project', 'projectWork', 'requestedBy', 'items']);

        $pdf = Pdf::loadView('purchase_requests.pdf', compact('purchaseRequest'))
            ->setPaper('letter', 'portrait');

        $filename = 'SOLCOM-' . ($purchaseRequest->folio ?? $purchaseRequest->id) . '.pdf';

        return $pdf->download($filename);
    }

    // ── Almacén: Pila SOLMAT ────────────────────────────────────────────────
    public function solmatPile(Request $request)
    {
        $search  = $request->input('search', '');
        $section = $request->input('section', 'entrada'); // entrada | salida | todas
        $selectionState = $this->getSolmatPileSelectionState($request);

        $query = MaterialRequest::active()
            ->with(['project', 'projectWorks', 'requestedBy', 'items.workQuantities'])
            ->withCount('purchaseRequests')
            ->withMax('purchaseRequests', 'created_at');

        if ($section === 'entrada') {
            $query->where('status', 'sent_to_warehouse');
        } elseif ($section === 'salida') {
            $query->where('status', 'linked');
        } else {
            $query->whereIn('status', ['sent_to_warehouse', 'linked']);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhere('zone', 'like', "%{$search}%")
                  ->orWhereHas('project', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

          // Prioridad operativa: atender primero las SOLMAT liberadas antes a Almacen.
          $query->orderByRaw('COALESCE(sent_to_warehouse_at, request_date, created_at) asc')
              ->orderBy('folio');

        $materialRequests = $query->paginate(20)->withQueryString();

        $entryCount = MaterialRequest::active()->where('status', 'sent_to_warehouse')->count();
        $outCount   = MaterialRequest::active()->where('status', 'linked')->count();

        return view('warehouse.solmat_pile', compact(
            'materialRequests',
            'search',
            'section',
            'entryCount',
            'outCount',
            'selectionState'
        ));
    }

    // ── Crear SOLCOM desde SOLMAT (formulario pre-llenado) ───────────────────
    public function createFromSolmat(MaterialRequest $materialRequest)
    {
        $sourceMaterialRequests = $this->loadSourceMaterialRequestsByIds([(int) $materialRequest->id]);

        return $this->renderCreateFromSolmatView($sourceMaterialRequests, false);
    }

    public function createFromSolmatMulti(Request $request)
    {
        $data = $request->validate([
            'material_request_ids' => 'nullable|array|min:1',
            'material_request_ids.*' => 'integer|exists:material_requests,id',
        ]);

        $materialRequestIds = collect($data['material_request_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($materialRequestIds)) {
            $selectionState = $this->getSolmatPileSelectionState($request);
            $materialRequestIds = $selectionState['selected_ids'];
        }

        if (empty($materialRequestIds)) {
            throw ValidationException::withMessages([
                'material_request_ids' => 'Debes seleccionar al menos una SOLMAT para crear una SOLCOM consolidada.',
            ]);
        }

        $sourceMaterialRequests = $this->loadSourceMaterialRequestsByIds($materialRequestIds);

        return $this->renderCreateFromSolmatView($sourceMaterialRequests, true);
    }

    public function syncSolmatPileSelection(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'selected_ids' => 'required|array|max:500',
            'selected_ids.*' => 'integer|distinct',
            'selected_project_id' => 'nullable|integer|min:1',
        ]);

        $selectionState = $this->sanitizeSolmatPileSelection(
            $data['selected_ids'],
            isset($data['selected_project_id']) ? (int) $data['selected_project_id'] : null
        );

        $this->persistSolmatPileSelectionState($request, $selectionState);

        return response()->json([
            'selected_ids' => $selectionState['selected_ids'],
            'selected_count' => $selectionState['selected_count'],
            'selected_project_id' => $selectionState['selected_project_id'],
        ]);
    }

    public function clearSolmatPileSelection(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->persistSolmatPileSelectionState($request, [
            'selected_ids' => [],
            'selected_project_id' => null,
            'selected_count' => 0,
        ]);

        return response()->json([
            'selected_ids' => [],
            'selected_count' => 0,
            'selected_project_id' => null,
        ]);
    }

    private function renderCreateFromSolmatView(Collection $sourceMaterialRequests, bool $clearSolmatPileSelectionOnSuccess = false)
    {
        $sourceMaterialRequests = $sourceMaterialRequests
            ->filter(fn (MaterialRequest $materialRequest) => $materialRequest->hasAvailableQuantityForProjectWorks())
            ->values();

        if ($sourceMaterialRequests->isEmpty()) {
            throw ValidationException::withMessages([
                'material_request_ids' => 'Las SOLMAT seleccionadas no tienen conceptos disponibles para crear una SOLCOM.',
            ]);
        }

        $materialRequest = $sourceMaterialRequests->first();

        $nextFolio = max((PurchaseRequest::withTrashed()->max('folio') ?? 17999), 17999) + 1;
        $projects  = Project::where('status', 'active')->orderBy('name')->get();
        $isMultiSource = $sourceMaterialRequests->count() > 1;

        $availableProjectWorks = $sourceMaterialRequests
            ->flatMap(fn ($mr) => $mr->projectWorks)
            ->unique('id')
            ->sortBy('name')
            ->values();

        $previewItems = $this->aggregateMaterialRequestItems(
            $sourceMaterialRequests,
            $availableProjectWorks->pluck('id')->map(fn ($id) => (int) $id)
        )->values();

        if ($previewItems->isEmpty()) {
            throw ValidationException::withMessages([
                'material_request_ids' => 'Las SOLMAT seleccionadas no tienen conceptos disponibles para crear una SOLCOM.',
            ]);
        }

        $defaultSelectedWorks = $availableProjectWorks
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $sourceMaterialRequestIds = $sourceMaterialRequests
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('purchase_requests.create_from_solmat',
            compact(
                'materialRequest',
                'nextFolio',
                'projects',
                'isMultiSource',
                'sourceMaterialRequests',
                'availableProjectWorks',
                'previewItems',
                'defaultSelectedWorks',
                'sourceMaterialRequestIds',
                'clearSolmatPileSelectionOnSuccess'
            )
        );
    }

    private function getSolmatPileSelectionState(Request $request): array
    {
        $rawState = $request->session()->get(self::SOLMAT_PILE_SELECTION_SESSION_KEY, []);

        $selectionState = $this->sanitizeSolmatPileSelection(
            $rawState['selected_ids'] ?? [],
            isset($rawState['selected_project_id']) ? (int) $rawState['selected_project_id'] : null
        );

        $this->persistSolmatPileSelectionState($request, $selectionState);

        return $selectionState;
    }

    private function sanitizeSolmatPileSelection(array $ids, ?int $selectedProjectId = null): array
    {
        $normalizedIds = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($normalizedIds->isEmpty()) {
            return [
                'selected_ids' => [],
                'selected_project_id' => null,
                'selected_count' => 0,
            ];
        }

        $allowedMaterialRequests = MaterialRequest::query()
            ->select(['id', 'project_id', 'status'])
            ->whereIn('id', $normalizedIds)
            ->get()
            ->filter(function (MaterialRequest $materialRequest) {
                return in_array((string) $materialRequest->status, ['sent_to_warehouse', 'linked'], true);
            })
            ->keyBy('id');

        if ($allowedMaterialRequests->isEmpty()) {
            return [
                'selected_ids' => [],
                'selected_project_id' => null,
                'selected_count' => 0,
            ];
        }

        $orderedSelection = $normalizedIds
            ->map(fn ($id) => $allowedMaterialRequests->get($id))
            ->filter()
            ->values();

        if ($orderedSelection->isEmpty()) {
            return [
                'selected_ids' => [],
                'selected_project_id' => null,
                'selected_count' => 0,
            ];
        }

        $availableProjectIds = $orderedSelection
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($availableProjectIds->isEmpty()) {
            return [
                'selected_ids' => [],
                'selected_project_id' => null,
                'selected_count' => 0,
            ];
        }

        $anchorProjectId = $selectedProjectId && $availableProjectIds->contains($selectedProjectId)
            ? $selectedProjectId
            : (int) $availableProjectIds->first();

        $selectedIds = $orderedSelection
            ->filter(fn (MaterialRequest $materialRequest) => (int) $materialRequest->project_id === $anchorProjectId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'selected_ids' => $selectedIds,
            'selected_project_id' => $anchorProjectId,
            'selected_count' => count($selectedIds),
        ];
    }

    private function persistSolmatPileSelectionState(Request $request, array $selectionState): void
    {
        if (empty($selectionState['selected_ids'])) {
            $request->session()->forget(self::SOLMAT_PILE_SELECTION_SESSION_KEY);
            return;
        }

        $request->session()->put(self::SOLMAT_PILE_SELECTION_SESSION_KEY, [
            'selected_ids' => array_values(array_map('intval', $selectionState['selected_ids'])),
            'selected_project_id' => (int) ($selectionState['selected_project_id'] ?? 0),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    // ── Enviar SOLCOM a Compras ──────────────────────────────────────────────
    public function sendToPurchasing(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $purchaseRequest->update([
            'assigned_to' => $data['assigned_to'],
            'status'      => 'sent_to_purchasing',
        ]);

        $assignedUser = User::find($data['assigned_to']);

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseRequest->id,
            'type'         => 'purchase_request',
            'data'         => "SOLCOM #{$purchaseRequest->folio} enviada a Compras (asignada a {$assignedUser?->name}).",
        ]);

        return redirect()->route('purchase_requests.show', $purchaseRequest)
                         ->with('success', "SOLCOM #{$purchaseRequest->folio} enviada a Compras.");
    }

    // ── Reasignar SOLCOM en Compras ────────────────────────────────────────
    public function reassignPurchasing(Request $request, PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'sent_to_purchasing') {
            return redirect()->route('purchase_requests.show', $purchaseRequest)
                ->with('error', 'Solo puedes reasignar una SOLCOM que esté en Compras.');
        }

        if (empty($purchaseRequest->assigned_to)) {
            return redirect()->route('purchase_requests.show', $purchaseRequest)
                ->with('error', 'La SOLCOM no tiene un usuario de Compras asignado actualmente.');
        }

        $data = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $currentAssignedId = (int) $purchaseRequest->assigned_to;
        $newAssignedId = (int) $data['assigned_to'];

        if ($currentAssignedId === $newAssignedId) {
            return redirect()->route('purchase_requests.show', $purchaseRequest)
                ->with('error', 'Selecciona un usuario diferente al actual para reasignar la SOLCOM.');
        }

        $previousAssignedUser = User::find($currentAssignedId);
        $newAssignedUser = User::find($newAssignedId);

        $purchaseRequest->update([
            'assigned_to' => $newAssignedId,
        ]);

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseRequest->id,
            'type'         => 'purchase_request',
            'data'         => "SOLCOM #{$purchaseRequest->folio} reasignada en Compras de {$previousAssignedUser?->name} a {$newAssignedUser?->name}.",
        ]);

        return redirect()->route('purchase_requests.show', $purchaseRequest)
            ->with('success', "SOLCOM #{$purchaseRequest->folio} reasignada a {$newAssignedUser?->name}.");
    }

    // ── Carga de Trabajo (estadísticas de SOLCOMs por usuario) ─────────────
    public function workload(): \Illuminate\View\View
    {
        // Solo usuarios con rol "Orden de compra"
        $ordersUsers = User::role('Orden de compra')->orderBy('name')->get();
        $ordersUserIds = $ordersUsers->pluck('id');

        // SOLCOMs en Compras que aún no tienen una OC, agrupadas por responsable.
        $unlinkedRequestCounts = PurchaseRequest::selectRaw('assigned_to, count(*) as total')
            ->where('status', 'sent_to_purchasing')
            ->doesntHave('purchaseOrders')
            ->whereIn('assigned_to', $ordersUserIds)
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        // La SOLCOM se mantiene en Compras después de crear una OC. La carga real
        // se determina por el estatus de la OC: pendiente, emitida o autorizada.
        $orderCounts = PurchaseOrder::query()
            ->selectRaw('purchase_requests.assigned_to, purchase_orders.status, count(*) as total')
            ->join('purchase_requests', 'purchase_orders.purchase_request_id', '=', 'purchase_requests.id')
            ->whereIn('purchase_requests.assigned_to', $ordersUserIds)
            ->whereIn('purchase_orders.status', ['pendiente', 'emitida', 'autorizada'])
            ->groupBy('purchase_requests.assigned_to', 'purchase_orders.status')
            ->get()
            ->groupBy('assigned_to');

        $activeCounts = $ordersUserIds->mapWithKeys(function ($userId) use ($unlinkedRequestCounts, $orderCounts) {
            $ordersByStatus = $orderCounts->get($userId, collect())->pluck('total', 'status');

            return [$userId => (int) $unlinkedRequestCounts->get($userId, 0)
                + (int) $ordersByStatus->get('pendiente', 0)
                + (int) $ordersByStatus->get('emitida', 0)];
        });

        // SOLCOMs sin asignar y pendientes de OC
        $unassignedCount = PurchaseRequest::whereNull('assigned_to')
            ->where('status', 'sent_to_purchasing')
            ->doesntHave('purchaseOrders')
            ->count();

        // Listado detallado de SOLCOMs sin OC por usuario para el drill-down
        $pendingByUser = PurchaseRequest::with(['project', 'projectWork'])
            ->where('status', 'sent_to_purchasing')
            ->doesntHave('purchaseOrders')
            ->whereIn('assigned_to', $ordersUserIds)
            ->orderByDesc('folio')
            ->get()
            ->groupBy('assigned_to');

        // SOLCOMs sin asignar pendientes de OC (detalle)
        $unassignedSolcoms = PurchaseRequest::with(['project', 'projectWork'])
            ->whereNull('assigned_to')
            ->where('status', 'sent_to_purchasing')
            ->doesntHave('purchaseOrders')
            ->orderByDesc('folio')
            ->get();

        return view('purchasing.workload', compact(
            'ordersUsers',
            'unlinkedRequestCounts',
            'orderCounts',
            'activeCounts',
            'unassignedCount',
            'pendingByUser',
            'unassignedSolcoms'
        ));
    }

    // ── Pila SOLCOM (vista de Compras) ──────────────────────────────────────
    public function purchasingPile(Request $request)
    {
        $search  = $request->input('search', '');
        $section = $request->input('section', 'entrada'); // entrada | salida | todas

        $query = PurchaseRequest::with(['project', 'projectWork', 'requestedBy', 'materialRequest', 'changeNotes'])
            ->withCount('purchaseOrders')
            ->withMax('purchaseOrders', 'created_at')
            ->where('status', 'sent_to_purchasing')
            ->where('assigned_to', Auth::id())
            ->orderByDesc('folio');

        if ($section === 'entrada') {
            $query->doesntHave('purchaseOrders');
        } elseif ($section === 'salida') {
            $query->has('purchaseOrders');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%")
                  ->orWhereHas('project', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $purchaseRequests = $query->paginate(20)->withQueryString();

        $baseQuery = PurchaseRequest::where('status', 'sent_to_purchasing')
            ->where('assigned_to', Auth::id());

        $entryCount = (clone $baseQuery)->doesntHave('purchaseOrders')->count();
        $outCount   = (clone $baseQuery)->has('purchaseOrders')->count();

        return view('purchasing.solcom_pile', compact(
            'purchaseRequests',
            'search',
            'section',
            'entryCount',
            'outCount'
        ));
    }

    // ── Solicitar cambios en SOLCOM ──────────────────────────────────────────
    public function requestChanges(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate([
            'change_text' => 'required|string|max:2000',
        ]);

        PurchaseRequestChangeNote::create([
            'purchase_request_id' => $purchaseRequest->id,
            'requested_by'        => Auth::id(),
            'text'                => $data['change_text'],
        ]);

        $purchaseRequest->update(['status' => 'changes_requested']);

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseRequest->id,
            'type'         => 'purchase_request',
            'data'         => "SOLCOM #{$purchaseRequest->folio}: cambios solicitados por " . Auth::user()->name . '.',
        ]);

        return redirect()->route('purchase_requests.show', $purchaseRequest)
                         ->with('success', 'Solicitud de cambios registrada. La SOLCOM regresa al equipo de Almacén.');
    }

    // ── Marcar nota de cambio como resuelta ──────────────────────────────────
    public function resolveChangeNote(PurchaseRequest $purchaseRequest, PurchaseRequestChangeNote $changeNote)
    {
        $changeNote->update([
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
        ]);

        // Si todas las notas están resueltas, volver a estado 'pending' para poder reenviar
        $unresolvedCount = $purchaseRequest->changeNotes()->whereNull('resolved_at')->count();
        if ($unresolvedCount === 0) {
            $purchaseRequest->update(['status' => 'pending']);

            app(NotificationService::class)->send([
                'action_by'    => Auth::id(),
                'model_action' => 'update',
                'model_id'     => $purchaseRequest->id,
                'type'         => 'purchase_request',
                'data'         => "SOLCOM #{$purchaseRequest->folio}: cambios resueltos, lista para reenviar a Compras.",
            ]);
        }

        return redirect()->route('purchase_requests.show', $purchaseRequest)
                         ->with('success', 'Nota marcada como resuelta.');
    }

    // ── SOLCOM: Archivo y Papelera ──────────────────────────────────────────
    public function archive(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->update(['archived_at' => now()]);

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'archive',
            'model_id'     => $purchaseRequest->id,
            'type'         => 'purchase_request',
            'data'         => "SOLCOM #{$purchaseRequest->folio} archivada.",
        ]);

        return redirect()->route('purchase_requests.index')
            ->with('success', "SOLCOM #{$purchaseRequest->folio} archivada.");
    }

    public function unarchive(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->update(['archived_at' => null]);

        return redirect()->route('purchase_requests.archived')
            ->with('success', "SOLCOM #{$purchaseRequest->folio} restaurada al listado activo.");
    }

    public function archived(Request $request)
    {
        $search = trim($request->input('search', ''));

        $query = PurchaseRequest::with(['project', 'projectWork', 'requestedBy', 'materialRequest', 'purchaseOrders'])
            ->whereNotNull('archived_at')
            ->orderBy('archived_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhere('zone', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%")
                  ->orWhereHas('project', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $purchaseRequests = $query->paginate(15)->withQueryString();

        return view('purchase_requests.archive', compact('purchaseRequests', 'search'));
    }

    public function softDeleted(Request $request)
    {
        $search = trim($request->input('search', ''));

        $query = PurchaseRequest::onlyTrashed()
            ->with(['project', 'projectWork', 'requestedBy', 'materialRequest', 'purchaseOrders'])
            ->orderBy('deleted_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhere('zone', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%")
                  ->orWhereHas('project', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $purchaseRequests = $query->paginate(15)->withQueryString();

        return view('purchase_requests.soft_deleted', compact('purchaseRequests', 'search'));
    }

    public function restore(int $id)
    {
        $purchaseRequest = PurchaseRequest::onlyTrashed()->findOrFail($id);
        $purchaseRequest->restore();

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'restore',
            'model_id'     => $purchaseRequest->id,
            'type'         => 'purchase_request',
            'data'         => "SOLCOM #{$purchaseRequest->folio} restaurada desde papelera.",
        ]);

        return redirect()->route('purchase_requests.soft_deleted')
            ->with('success', "SOLCOM #{$purchaseRequest->folio} restaurada.");
    }

    public function forceDestroy(int $id)
    {
        $purchaseRequest = PurchaseRequest::onlyTrashed()->findOrFail($id);
        $folio = $purchaseRequest->folio;

        DB::transaction(function () use ($purchaseRequest) {
            $purchaseRequest->changeNotes()->delete();
            $purchaseRequest->items()->delete();
            $purchaseRequest->forceDelete();
        });

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'force_destroy',
            'model_id'     => 0,
            'type'         => 'purchase_request',
            'data'         => "SOLCOM #{$folio} eliminada permanentemente.",
        ]);

        return redirect()->route('purchase_requests.soft_deleted')
            ->with('success', "SOLCOM #{$folio} eliminada permanentemente.");
    }

    private function resolveItemQuantityForWorks($materialRequestItem, $selectedWorkIds): float
    {
        if ($materialRequestItem->relationLoaded('workQuantities') && $materialRequestItem->workQuantities->isNotEmpty()) {
            return (float) $materialRequestItem->workQuantities->filter(function ($workQuantity) use ($selectedWorkIds) {
                return $selectedWorkIds->contains((int) $workQuantity->project_work_id)
                    && !$workQuantity->is_committed;
            })->sum('quantity');
        }

        return (float) $materialRequestItem->quantity;
    }

    private function resolveCommittedItemQuantityForWorks($materialRequestItem, $selectedWorkIds): float
    {
        if (!$materialRequestItem->relationLoaded('workQuantities') || $materialRequestItem->workQuantities->isEmpty()) {
            return 0;
        }

        return (float) $materialRequestItem->workQuantities->filter(function ($workQuantity) use ($selectedWorkIds) {
            return $selectedWorkIds->contains((int) $workQuantity->project_work_id)
                && $workQuantity->is_committed;
        })->sum('quantity');
    }

    private function loadSourceMaterialRequestsByIds($ids): Collection
    {
        $requestedIds = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($requestedIds->isEmpty()) {
            throw ValidationException::withMessages([
                'material_request_ids' => 'Debes seleccionar al menos una SOLMAT.',
            ]);
        }

        $materialRequests = MaterialRequest::with(['project', 'projectWorks', 'items.concept.category', 'items.workQuantities.projectWork'])
            ->whereIn('id', $requestedIds)
            ->get()
            ->keyBy('id');

        if ($materialRequests->count() !== $requestedIds->count()) {
            throw ValidationException::withMessages([
                'material_request_ids' => 'Una o más SOLMAT seleccionadas no existen.',
            ]);
        }

        $orderedMaterialRequests = $requestedIds
            ->map(fn ($id) => $materialRequests->get($id))
            ->filter()
            ->values();

        $invalidStatusFolios = $orderedMaterialRequests
            ->filter(fn ($materialRequest) => !in_array((string) $materialRequest->status, ['sent_to_warehouse', 'linked'], true))
            ->pluck('folio')
            ->values();

        if ($invalidStatusFolios->isNotEmpty()) {
            throw ValidationException::withMessages([
                'material_request_ids' => 'Solo puedes usar SOLMAT en estatus Entrada o Con salida.',
            ]);
        }

        $projectIds = $orderedMaterialRequests
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($projectIds->count() > 1) {
            throw ValidationException::withMessages([
                'material_request_ids' => 'Todas las SOLMAT seleccionadas deben pertenecer al mismo proyecto.',
            ]);
        }

        return $orderedMaterialRequests;
    }

    private function aggregateMaterialRequestItems(Collection $sourceMaterialRequests, Collection $selectedWorkIds): Collection
    {
        $aggregatedItems = [];

        foreach ($sourceMaterialRequests as $materialRequest) {
            foreach ($materialRequest->items as $item) {
                $resolvedQuantity = $this->resolveItemQuantityForWorks($item, $selectedWorkIds);
                $committedQuantity = $this->resolveCommittedItemQuantityForWorks($item, $selectedWorkIds);

                if ($resolvedQuantity <= 0 && $committedQuantity <= 0) {
                    continue;
                }

                $itemKey = $item->concept_id
                    ? 'concept:' . (int) $item->concept_id
                    : 'legacy:' . $this->normalizeText($item->code) . '|' . $this->normalizeText($item->description) . '|' . $this->normalizeText($item->unit);

                if (!isset($aggregatedItems[$itemKey])) {
                    $aggregatedItems[$itemKey] = [
                        'concept_id' => $item->concept_id,
                        'code' => $item->code,
                        'description' => $item->description,
                        'unit' => $item->unit,
                        'resolved_quantity' => 0,
                        'committed_quantity' => 0,
                        'file_path' => $item->file_path,
                        'work_quantities' => [],
                        'committed_work_quantities' => [],
                        'source_folios' => [],
                        'source_material_request_ids' => [],
                    ];
                }

                $aggregatedItems[$itemKey]['resolved_quantity'] += (float) $resolvedQuantity;
                $aggregatedItems[$itemKey]['committed_quantity'] += (float) $committedQuantity;
                if (empty($aggregatedItems[$itemKey]['file_path']) && !empty($item->file_path)) {
                    $aggregatedItems[$itemKey]['file_path'] = $item->file_path;
                }

                $aggregatedItems[$itemKey]['source_folios'][(int) $materialRequest->id] = (int) $materialRequest->folio;
                $aggregatedItems[$itemKey]['source_material_request_ids'][(int) $materialRequest->id] = (int) $materialRequest->id;

                if ($item->relationLoaded('workQuantities') && $item->workQuantities->isNotEmpty()) {
                    foreach ($item->workQuantities as $workQuantity) {
                        $workId = (int) $workQuantity->project_work_id;

                        if ($selectedWorkIds->isNotEmpty() && !$selectedWorkIds->contains($workId)) {
                            continue;
                        }

                        if ($workQuantity->is_committed) {
                            if (!isset($aggregatedItems[$itemKey]['committed_work_quantities'][$workId])) {
                                $aggregatedItems[$itemKey]['committed_work_quantities'][$workId] = 0;
                            }

                            $aggregatedItems[$itemKey]['committed_work_quantities'][$workId] += (float) $workQuantity->quantity;
                            continue;
                        }

                        if (!isset($aggregatedItems[$itemKey]['work_quantities'][$workId])) {
                            $aggregatedItems[$itemKey]['work_quantities'][$workId] = 0;
                        }

                        $aggregatedItems[$itemKey]['work_quantities'][$workId] += (float) $workQuantity->quantity;
                    }
                } else {
                    $legacyWorkId = $selectedWorkIds->first();
                    if ($legacyWorkId) {
                        if (!isset($aggregatedItems[$itemKey]['work_quantities'][$legacyWorkId])) {
                            $aggregatedItems[$itemKey]['work_quantities'][$legacyWorkId] = 0;
                        }
                        $aggregatedItems[$itemKey]['work_quantities'][$legacyWorkId] += (float) $item->quantity;
                    }
                }
            }
        }

        return collect($aggregatedItems)
            ->filter(fn ($item) => (float) $item['resolved_quantity'] > 0)
            ->map(function ($item, $itemKey) {
                ksort($item['work_quantities']);
                ksort($item['committed_work_quantities']);
                sort($item['source_folios']);
                $item['source_material_request_ids'] = array_values($item['source_material_request_ids']);
                $item['item_key'] = $itemKey;

                $item['work_quantities'] = collect($item['work_quantities'])
                    ->map(fn ($quantity, $workId) => [
                        'work_id' => (string) $workId,
                        'quantity' => (float) $quantity,
                    ])
                    ->values()
                    ->all();

                $item['committed_work_quantities'] = collect($item['committed_work_quantities'])
                    ->map(fn ($quantity, $workId) => [
                        'work_id' => (string) $workId,
                        'quantity' => (float) $quantity,
                    ])
                    ->values()
                    ->all();

                return $item;
            })
            ->sortBy(fn ($item) => strtoupper((string) ($item['code'] ?? '') . '|' . (string) ($item['description'] ?? '')))
            ->values();
    }

    private function normalizeText(?string $value): string
    {
        $text = trim((string) ($value ?? ''));
        $text = preg_replace('/\s+/', ' ', $text);

        return strtolower($text ?? '');
    }

    private function materialItemGroupingKey($materialItem): string
    {
        if (!empty($materialItem->concept_id)) {
            return 'concept:' . (int) $materialItem->concept_id;
        }

        return 'legacy:' . $this->normalizeText($materialItem->code)
            . '|' . $this->normalizeText($materialItem->description)
            . '|' . $this->normalizeText($materialItem->unit);
    }

    private function purchaseItemGroupingKey($purchaseItem): string
    {
        if (!empty($purchaseItem->concept_id)) {
            return 'concept:' . (int) $purchaseItem->concept_id;
        }

        return 'legacy:' . $this->normalizeText($purchaseItem->code)
            . '|' . $this->normalizeText($purchaseItem->description)
            . '|' . $this->normalizeText($purchaseItem->unit);
    }
}
