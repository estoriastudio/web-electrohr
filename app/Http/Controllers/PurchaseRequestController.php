<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');

        $query = PurchaseRequest::with(['project', 'projectWork', 'requestedBy', 'materialRequest'])
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
        $nextFolio        = (PurchaseRequest::max('folio') ?? 0) + 1;
        $projects         = Project::where('status', 'active')->orderBy('name')->get();

        return view('purchase_requests.index', compact('purchaseRequests', 'nextFolio', 'projects', 'search', 'status'));
    }

    public function create()
    {
        return redirect()->route('purchase_requests.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'folio'               => 'required|integer|unique:purchase_requests,folio',
            'code'                => 'nullable|string|max:100',
            'material_request_id' => 'nullable|exists:material_requests,id',
            'project_id'          => 'required|exists:projects,id',
            'project_work_id'     => 'required|exists:project_works,id',
            'zone'                => 'required|string|max:255',
            'delivery_address'    => 'required|string|max:255',
            'short_description'   => 'required|string|max:255',
            'request_date'        => 'required|date',
            'need_date'           => 'required|date|after_or_equal:request_date',
        ]);

        $data['requested_by'] = Auth::id();
        $data['status']       = 'pending';

        $pr = PurchaseRequest::create($data);

        // Si viene vinculada a una SOLMAT: copiar sus items y marcarla como 'linked'
        if (! empty($data['material_request_id'])) {
            $mr = MaterialRequest::with('items')->find($data['material_request_id']);

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
                    ]);
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
        $purchaseRequest->load(['project', 'projectWork', 'requestedBy', 'items', 'materialRequest']);

        return view('purchase_requests.show', compact('purchaseRequest'));
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
        $folio = $purchaseRequest->folio;
        $purchaseRequest->delete();

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'delete',
            'model_id'     => 0,
            'type'         => 'purchase_request',
            'data'         => "SOLCOM #{$folio} eliminada.",
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
}
