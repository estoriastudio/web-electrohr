<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Project;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaterialRequestController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');

        $query = MaterialRequest::with(['project', 'projectWork', 'requestedBy'])
            ->orderByDesc('folio');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhere('zone', 'like', "%{$search}%")
                  ->orWhere('supply_category', 'like', "%{$search}%")
                  ->orWhereHas('project', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $materialRequests = $query->paginate(15)->withQueryString();
        $nextFolio        = (MaterialRequest::max('folio') ?? 0) + 1;
        $projects         = Project::where('status', 'active')->orderBy('name')->get();

        return view('material_requests.index', compact('materialRequests', 'nextFolio', 'projects', 'search', 'status'));
    }

    public function create()
    {
        return redirect()->route('material_requests.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'folio'            => 'required|integer|unique:material_requests,folio',
            'code'             => 'nullable|string|max:100',
            'project_id'       => 'required|exists:projects,id',
            'project_work_id'  => 'required|exists:project_works,id',
            'zone'             => 'required|string|max:255',
            'delivery_address' => 'required|string|max:255',
            'request_date'     => 'required|date',
            'need_date'        => 'required|date|after_or_equal:request_date',
            'supply_category'  => 'required|string|max:255',
        ]);

        $data['requested_by'] = Auth::id();
        $data['status']       = 'pending';

        $mr = MaterialRequest::create($data);

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
        $materialRequest->load(['project', 'projectWork', 'requestedBy', 'items', 'purchaseRequests']);

        return view('material_requests.show', compact('materialRequest'));
    }

    public function edit(MaterialRequest $materialRequest)
    {
        $projects = Project::where('status', 'active')->orderBy('name')->get();

        return view('material_requests.edit', compact('materialRequest', 'projects'));
    }

    public function update(Request $request, MaterialRequest $materialRequest)
    {
        $data = $request->validate([
            'code'             => 'nullable|string|max:100',
            'project_id'       => 'required|exists:projects,id',
            'project_work_id'  => 'required|exists:project_works,id',
            'zone'             => 'required|string|max:255',
            'delivery_address' => 'required|string|max:255',
            'request_date'     => 'required|date',
            'need_date'        => 'required|date|after_or_equal:request_date',
            'supply_category'  => 'required|string|max:255',
        ]);

        $materialRequest->update($data);

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

    public function destroy(MaterialRequest $materialRequest)
    {
        $folio = $materialRequest->folio;
        $materialRequest->delete();

        app(NotificationService::class)->send([
            'action_by'    => Auth::id(),
            'model_action' => 'delete',
            'model_id'     => 0,
            'type'         => 'material_request',
            'data'         => "SOLMAT #{$folio} eliminada.",
        ]);

        return redirect()->route('material_requests.index')
                         ->with('success', "Solicitud de Material #{$folio} eliminada.");
    }

    public function storeItem(Request $request, MaterialRequest $materialRequest)
    {
        $data = $request->validate([
            'concept_id'  => 'nullable|exists:concepts,id',
            'code'        => 'required|string|max:100',
            'description' => 'required|string|max:500',
            'unit'        => 'required|string|max:50',
            'quantity'    => 'required|numeric|min:0.01',
        ]);

        $data['material_request_id'] = $materialRequest->id;

        $item = MaterialRequestItem::create($data);

        if ($request->wantsJson()) {
            return response()->json([
                'id'          => $item->id,
                'code'        => $item->code,
                'description' => $item->description,
                'unit'        => $item->unit,
                'quantity'    => $item->quantity,
            ]);
        }

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', 'Concepto agregado.');
    }

    public function destroyItem(Request $request, MaterialRequest $materialRequest, MaterialRequestItem $item)
    {
        $item->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', 'Concepto eliminado.');
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

    public function jsonByFolio(Request $request)
    {
        $folio = $request->input('folio');

        $mr = MaterialRequest::with(['project', 'projectWork', 'items'])
            ->where('folio', $folio)
            ->first();

        if (! $mr) {
            return response()->json(['error' => 'No se encontró la Solicitud de Material con ese folio.'], 404);
        }

        return response()->json([
            'id'               => $mr->id,
            'folio'            => $mr->folio,
            'project_id'       => $mr->project_id,
            'project_name'     => $mr->project?->name,
            'project_work_id'  => $mr->project_work_id,
            'project_work_name'=> $mr->projectWork?->name,
            'zone'             => $mr->zone,
            'delivery_address' => $mr->delivery_address,
            'supply_category'  => $mr->supply_category,
            'items'            => $mr->items->map(fn($i) => [
                'id'          => $i->id,
                'code'        => $i->code,
                'description' => $i->description,
                'unit'        => $i->unit,
                'quantity'    => $i->quantity,
            ]),
        ]);
    }
}
