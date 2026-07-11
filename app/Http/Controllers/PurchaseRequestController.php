<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\Notification;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestChangeNote;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');

        $query = PurchaseRequest::with(['project', 'projectWork', 'requestedBy', 'materialRequest'])
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
            'project_id'          => 'required|exists:projects,id',
            'project_work_id'     => 'required|exists:project_works,id',
            'zone'                => 'required|string|max:255',
            'delivery_address'    => 'required|string|max:255',
            'short_description'   => 'required|string|max:255',
            'request_date'        => 'required|date',
            'need_date'           => 'required|date|after_or_equal:request_date',
            'assigned_to'         => 'nullable|exists:users,id',
        ]);

        $data['requested_by'] = Auth::id();
        $data['status']       = 'pending';

        $pr = PurchaseRequest::create($data);

        // Si viene vinculada a una SOLMAT: copiar sus items y marcarla como 'linked'
        if (! empty($data['material_request_id'])) {
            $mr = MaterialRequest::with('items.concept.category')->find($data['material_request_id']);

            if ($mr) {
                foreach ($mr->items as $item) {
                    PurchaseRequestItem::create([
                        'purchase_request_id' => $pr->id,
                        'concept_id'          => $item->concept_id,
                        'code'                => $item->code,
                        'description'         => $item->description,
                        'unit'                => $item->unit,
                        'requested_quantity'  => $item->quantity,
                        'purchase_quantity'   => $item->quantity,
                        'file_path'           => $item->file_path,
                    ]);
                }

                // Si no se eligió un comprador manualmente, sugerir desde la categoría
                if (empty($pr->assigned_to)) {
                    $categoryId = $mr->items
                        ->whereNotNull('concept_id')
                        ->map(fn($i) => optional($i->concept)->concept_category_id)
                        ->filter()
                        ->first();

                    if ($categoryId) {
                        $suggestedUser = \App\Models\ConceptCategory::find($categoryId)
                            ?->users()
                            ->orderBy('name')
                            ->first();

                        if ($suggestedUser) {
                            $pr->update(['assigned_to' => $suggestedUser->id]);
                        }
                    }
                }

                // Heredar observaciones de SOLMAT a SOLCOM para mantener trazabilidad.
                if (!empty($mr->observations) && empty($pr->observations)) {
                    $pr->update(['observations' => $mr->observations]);
                }

                $mr->update(['status' => 'linked']);
            }
        }

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $pr->id,
            'type'         => 'purchase_request',
            'data'         => "SOLCOM #{$pr->folio} creada.",
        ]);

        return redirect()->route('purchase_requests.show', $pr)
                         ->with('success', "Solicitud de Compra #{$pr->folio} creada correctamente.");
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load([
            'project', 'projectWork', 'requestedBy', 'assignedTo',
            'items', 'materialRequest', 'changeNotes.requestedBy', 'changeNotes.resolvedBy',
        ]);

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
            'project_work_id'   => 'required|exists:project_works,id',
            'zone'              => 'required|string|max:255',
            'delivery_address'  => 'required|string|max:255',
            'short_description' => 'required|string|max:255',
            'request_date'      => 'required|date',
            'need_date'         => 'required|date|after_or_equal:request_date',
        ]);

        $purchaseRequest->update($data);

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
            'requested_quantity' => 'required|integer|min:1',
            'purchase_quantity'  => 'required|integer|min:0',
        ]);

        $data['purchase_request_id'] = $purchaseRequest->id;

        $item = PurchaseRequestItem::create($data);

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
            'purchase_quantity' => 'required|integer|min:0',
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

        $query = MaterialRequest::with(['project', 'projectWorks', 'requestedBy', 'items'])
            ->withCount('purchaseRequests')
            ->withMax('purchaseRequests', 'created_at')
            ->orderByDesc('folio');

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

        $materialRequests = $query->paginate(20)->withQueryString();

        $entryCount = MaterialRequest::where('status', 'sent_to_warehouse')->count();
        $outCount   = MaterialRequest::where('status', 'linked')->count();

        return view('warehouse.solmat_pile', compact(
            'materialRequests',
            'search',
            'section',
            'entryCount',
            'outCount'
        ));
    }

    // ── Crear SOLCOM desde SOLMAT (formulario pre-llenado) ───────────────────
    public function createFromSolmat(MaterialRequest $materialRequest)
    {
        $materialRequest->load(['project', 'projectWorks', 'items']);
        $nextFolio = max((PurchaseRequest::withTrashed()->max('folio') ?? 17999), 17999) + 1;
        $projects  = Project::where('status', 'active')->orderBy('name')->get();

        return view('purchase_requests.create_from_solmat',
                    compact('materialRequest', 'nextFolio', 'projects'));
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

    // ── Carga de Trabajo (estadísticas de SOLCOMs por usuario) ─────────────
    public function workload(): \Illuminate\View\View
    {
        // Solo usuarios con rol "Orden de compra"
        $ordersUsers = User::role('Orden de compra')->orderBy('name')->get();
        $ordersUserIds = $ordersUsers->pluck('id');

        // SOLCOMs pendientes (sent_to_purchasing) agrupadas por assigned_to
        $pendingCounts = PurchaseRequest::selectRaw('assigned_to, count(*) as total')
            ->where('status', 'sent_to_purchasing')
            ->whereIn('assigned_to', $ordersUserIds)
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        // Conteos adicionales por usuario (histórico)
        $allCounts = PurchaseRequest::selectRaw('assigned_to, status, count(*) as total')
            ->whereIn('assigned_to', $ordersUserIds)
            ->groupBy('assigned_to', 'status')
            ->get()
            ->groupBy('assigned_to');

        // SOLCOMs sin asignar
        $unassignedCount = PurchaseRequest::whereNull('assigned_to')
            ->where('status', 'sent_to_purchasing')
            ->count();

        // Listado detallado de SOLCOMs pendientes por usuario para el drill-down
        $pendingByUser = PurchaseRequest::with(['project', 'projectWork'])
            ->where('status', 'sent_to_purchasing')
            ->whereIn('assigned_to', $ordersUserIds)
            ->orderByDesc('folio')
            ->get()
            ->groupBy('assigned_to');

        // SOLCOMs sin asignar (detalle)
        $unassignedSolcoms = PurchaseRequest::with(['project', 'projectWork'])
            ->whereNull('assigned_to')
            ->where('status', 'sent_to_purchasing')
            ->orderByDesc('folio')
            ->get();

        return view('purchasing.workload', compact(
            'ordersUsers',
            'pendingCounts',
            'allCounts',
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

        $query = PurchaseRequest::with(['project', 'projectWork', 'requestedBy', 'materialRequest'])
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
            ->with(['project', 'projectWork', 'requestedBy', 'materialRequest'])
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
}
