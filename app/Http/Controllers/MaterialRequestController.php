<?php

namespace App\Http\Controllers;

use App\Models\ConceptCategory;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Project;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MaterialRequestController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');

        $query = MaterialRequest::with(['project', 'projectWorks', 'requestedBy'])
            ->orderByDesc('folio');

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
        $nextFolio        = (MaterialRequest::max('folio') ?? 0) + 1;
        $projects         = Project::where('status', 'active')->orderBy('name')->get();
        $categories       = ConceptCategory::where('type', 'materiales')->orderBy('name')->get();

        return view('material_requests.index', compact('materialRequests', 'nextFolio', 'projects', 'search', 'status', 'categories'));
    }

    public function create()
    {
        return redirect()->route('material_requests.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'folio'               => 'required|integer|unique:material_requests,folio',
            'code'                => 'nullable|string|max:100',
            'project_id'          => 'required|exists:projects,id',
            'project_work_ids'    => 'required|array|min:1',
            'project_work_ids.*'  => 'exists:project_works,id',
            'zone'                => 'required|string|max:255',
            'delivery_address'    => 'required|string|max:255',
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

        $data['requested_by'] = Auth::id();
        $data['status']       = 'pending';

        $mr = MaterialRequest::create($data);
        $mr->projectWorks()->sync($workIds);

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
        $materialRequest->load(['project', 'projectWorks', 'requestedBy', 'items', 'purchaseRequests']);

        return view('material_requests.show', compact('materialRequest'));
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
            'quantity'    => 'required|integer|min:1',
            'spec_file'   => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
        ]);

        $data['material_request_id'] = $materialRequest->id;

        if ($request->hasFile('spec_file')) {
            $path = $request->file('spec_file')->store('solmat_specs', 'public');
            $data['file_path'] = $path;
        }

        $item = MaterialRequestItem::create($data);

        if ($request->wantsJson()) {
            return response()->json([
                'id'          => $item->id,
                'code'        => $item->code,
                'description' => $item->description,
                'unit'        => $item->unit,
                'quantity'    => $item->quantity,
                'file_path'   => $item->file_path,
                'file_url'    => $item->file_path ? Storage::url($item->file_path) : null,
            ]);
        }

        return redirect()->route('material_requests.show', $materialRequest)
                         ->with('success', 'Concepto agregado.');
    }

    public function destroyItem(Request $request, MaterialRequest $materialRequest, MaterialRequestItem $item)
    {
        if ($item->file_path) {
            Storage::disk('public')->delete($item->file_path);
        }

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

    public function sendToWarehouse(MaterialRequest $materialRequest)
    {
        $materialRequest->update(['status' => 'sent_to_warehouse']);

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
