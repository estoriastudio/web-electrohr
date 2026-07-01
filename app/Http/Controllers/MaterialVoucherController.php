<?php

namespace App\Http\Controllers;

use App\Models\MaterialVoucherItem;
use App\Models\MaterialVoucher;
use App\Models\Project;
use App\Models\Supplier;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaterialVoucherController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));

        $query = MaterialVoucher::query()
            ->with(['supplier', 'project', 'projectWork'])
            ->withCount('items')
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                      $supplierQuery->where('rfc_name', 'like', "%{$search}%")
                          ->orWhere('commercial_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($status !== '' && in_array($status, MaterialVoucher::STATUSES, true)) {
            $query->where('status', $status);
        }

        $materialVouchers = $query->paginate(25)->withQueryString();

        $suppliers = Supplier::query()
            ->orderByRaw('COALESCE(NULLIF(commercial_name, \'\'), rfc_name) ASC')
            ->get();

        $projects = Project::query()
            ->where('status', 'active')
            ->with('works')
            ->orderBy('name')
            ->get();

        return view('material_vouchers.index', compact(
            'materialVouchers',
            'suppliers',
            'projects',
            'search',
            'status'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('material_vouchers.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id'       => 'required|exists:suppliers,id',
            'project_id'        => 'nullable|exists:projects,id',
            'project_work_id'   => 'nullable|exists:project_works,id',
            'voucher_date'      => 'required|date',
            'initial_note'      => 'nullable|string|max:1000',
        ]);

        $supplier = Supplier::findOrFail($data['supplier_id']);
        $prefix = $this->buildSupplierPrefix($supplier);
        $folioData = $this->generateUniqueFolio($prefix);

        $observations = [];
        if (!empty($data['initial_note'])) {
            $observations[] = [
                'user_id'    => Auth::id(),
                'user_name'  => Auth::user()?->name,
                'text'       => $data['initial_note'],
                'created_at' => now()->toDateTimeString(),
            ];
        }

        $voucher = MaterialVoucher::create([
            'folio'                     => $folioData['folio'],
            'folio_prefix'              => $prefix,
            'folio_number'              => $folioData['number'],
            'supplier_id'               => $data['supplier_id'],
            'project_id'                => $data['project_id'] ?? null,
            'project_work_id'           => $data['project_work_id'] ?? null,
            'voucher_date'              => $data['voucher_date'],
            'status'                    => 'emitido',
            'authorized_by'             => null,
            'authorized_at'             => null,
            'authorized_signature_name' => null,
            'authorized_signature'      => null,
            'observations'              => $observations ?: null,
            'created_by'                => Auth::id(),
        ]);

        $this->notification->send([
            'type'         => 'MaterialVoucher',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $voucher->id,
            'data'         => 'creó un nuevo vale de material ' . $voucher->folio,
        ]);

        return redirect()->route('material_vouchers.show', $voucher)
            ->with('success', 'Vale creado correctamente con folio ' . $voucher->folio . '.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MaterialVoucher $materialVoucher): View
    {
        $materialVoucher->load(['supplier', 'project', 'projectWork', 'items', 'createdBy', 'authorizedBy']);

        return view('material_vouchers.show', compact('materialVoucher'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MaterialVoucher $materialVoucher): View
    {
        $suppliers = Supplier::query()
            ->orderByRaw('COALESCE(NULLIF(commercial_name, \'\'), rfc_name) ASC')
            ->get();

        $projects = Project::query()
            ->where('status', 'active')
            ->with('works')
            ->orderBy('name')
            ->get();

        return view('material_vouchers.edit', compact('materialVoucher', 'suppliers', 'projects'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MaterialVoucher $materialVoucher): RedirectResponse
    {
        if (!$this->isEditable($materialVoucher)) {
            return redirect()->route('material_vouchers.show', $materialVoucher)
                ->with('error', 'El vale ya fue autorizado y no permite modificaciones.');
        }

        $data = $request->validate([
            'supplier_id'               => 'required|exists:suppliers,id',
            'project_id'                => 'nullable|exists:projects,id',
            'project_work_id'           => 'nullable|exists:project_works,id',
            'voucher_date'              => 'required|date',
            'status'                    => ['required', Rule::in(MaterialVoucher::STATUSES)],
            'authorized_signature_name' => 'nullable|string|max:255',
        ]);

        $materialVoucher->update($data);

        $this->notification->send([
            'type'         => 'MaterialVoucher',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $materialVoucher->id,
            'data'         => 'actualizó la información del vale de material ' . $materialVoucher->folio,
        ]);

        return redirect()->route('material_vouchers.show', $materialVoucher)
            ->with('success', 'Vale actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MaterialVoucher $materialVoucher): RedirectResponse
    {
        if (!$this->isEditable($materialVoucher)) {
            return redirect()->route('material_vouchers.show', $materialVoucher)
                ->with('error', 'El vale ya fue autorizado y no permite modificaciones.');
        }

        $folio = $materialVoucher->folio;

        $materialVoucher->delete();

        $this->notification->send([
            'type'         => 'MaterialVoucher',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $materialVoucher->id,
            'data'         => 'eliminó el vale de material ' . $folio,
        ]);

        return redirect()->route('material_vouchers.index')
            ->with('success', 'Vale eliminado correctamente.');
    }

    public function storeItem(Request $request, MaterialVoucher $materialVoucher): RedirectResponse|JsonResponse
    {
        if (!$this->isEditable($materialVoucher)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Solo puedes agregar renglones cuando el vale está en estatus Emitido.',
                ], 422);
            }

            return redirect()->route('material_vouchers.show', $materialVoucher)
                ->with('error', 'Solo puedes agregar renglones cuando el vale está en estatus Emitido.');
        }

        $data = $request->validate([
            'quantity'    => 'required|numeric|min:0.01|max:99999999.99',
            'unit'        => 'required|string|max:50',
            'description' => 'required|string|max:500',
        ]);

        $item = $materialVoucher->items()->create($data);

        if ($request->wantsJson()) {
            return response()->json([
                'id'          => $item->id,
                'quantity'    => (float) $item->quantity,
                'unit'        => $item->unit,
                'description' => $item->description,
            ]);
        }

        return redirect()->route('material_vouchers.show', $materialVoucher)
            ->with('success', 'Renglón agregado correctamente.');
    }

    public function destroyItem(Request $request, MaterialVoucher $materialVoucher, MaterialVoucherItem $item): RedirectResponse|JsonResponse
    {
        if (!$this->isEditable($materialVoucher)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'El vale ya fue autorizado y no permite modificaciones.',
                ], 422);
            }

            return redirect()->route('material_vouchers.show', $materialVoucher)
                ->with('error', 'El vale ya fue autorizado y no permite modificaciones.');
        }

        if ((int) $item->material_voucher_id !== (int) $materialVoucher->id) {
            abort(404);
        }

        $item->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('material_vouchers.show', $materialVoucher)
            ->with('success', 'Renglón eliminado correctamente.');
    }

    public function storeObservation(Request $request, MaterialVoucher $materialVoucher): RedirectResponse
    {
        if (!$this->isEditable($materialVoucher)) {
            return redirect()->route('material_vouchers.show', $materialVoucher)
                ->with('error', 'El vale ya fue autorizado y no permite modificaciones.');
        }

        $data = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $observations   = $materialVoucher->observations ?? [];
        $observations[] = [
            'user_id'    => Auth::id(),
            'user_name'  => Auth::user()?->name,
            'text'       => $data['text'],
            'created_at' => now()->toDateTimeString(),
        ];

        $materialVoucher->update(['observations' => $observations]);

        return redirect()->route('material_vouchers.show', $materialVoucher)
            ->with('success', 'Observación agregada correctamente.');
    }

    public function authorizeVoucher(Request $request, MaterialVoucher $materialVoucher): RedirectResponse
    {
        $data = $request->validate([
            'signature_name' => 'nullable|string|max:255',
            'signature_data' => 'required|string|starts_with:data:image/png;base64,',
        ]);

        if ($materialVoucher->status !== 'emitido') {
            return redirect()->route('material_vouchers.show', $materialVoucher)
                ->with('error', 'Solo se pueden autorizar vales en estatus Emitido.');
        }

        $materialVoucher->update([
            'status'                    => 'autorizado',
            'authorized_by'             => Auth::id(),
            'authorized_at'             => now(),
            'authorized_signature_name' => $data['signature_name'] ?: (Auth::user()?->name ?? null),
            'authorized_signature'      => $data['signature_data'],
        ]);

        $this->notification->send([
            'type'         => 'MaterialVoucher',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $materialVoucher->id,
            'data'         => 'autorizó el vale de material ' . $materialVoucher->folio,
        ]);

        return redirect()->route('material_vouchers.show', $materialVoucher)
            ->with('success', 'Vale autorizado correctamente.');
    }

    public function updateStatus(Request $request, MaterialVoucher $materialVoucher): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(MaterialVoucher::STATUSES)],
        ]);

        $currentStatus = $materialVoucher->status;
        $targetStatus = $data['status'];

        $allowedTransitions = [
            'emitido'    => ['autorizado'],
            'autorizado' => ['completado'],
            'completado' => ['facturado'],
            'facturado'  => ['pagado'],
            'pagado'     => [],
        ];

        if (!in_array($targetStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            return redirect()->route('material_vouchers.show', $materialVoucher)
                ->with('error', 'Transición de estatus inválida.');
        }

        if ($targetStatus === 'autorizado' && !$materialVoucher->authorized_by) {
            $materialVoucher->update([
                'status'                    => 'autorizado',
                'authorized_by'             => Auth::id(),
                'authorized_at'             => now(),
                'authorized_signature_name' => $materialVoucher->authorized_signature_name ?: (Auth::user()?->name ?? null),
                'authorized_signature'      => $materialVoucher->authorized_signature ?: null,
            ]);
        } else {
            $materialVoucher->update(['status' => $targetStatus]);
        }

        $this->notification->send([
            'type'         => 'MaterialVoucher',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $materialVoucher->id,
            'data'         => 'actualizó el estatus del vale ' . $materialVoucher->folio . ' a ' . $targetStatus,
        ]);

        return redirect()->route('material_vouchers.show', $materialVoucher)
            ->with('success', 'Estatus actualizado correctamente.');
    }

    public function downloadPdf(MaterialVoucher $materialVoucher): Response
    {
        $materialVoucher->load(['supplier', 'items', 'authorizedBy']);

        $pdf = Pdf::loadView('material_vouchers.pdf', compact('materialVoucher'))
            ->setPaper([0, 0, 396, 612], 'portrait');

        return $pdf->download('VALE-' . $materialVoucher->folio . '.pdf');
    }

    private function buildSupplierPrefix(Supplier $supplier): string
    {
        $source = $supplier->commercial_name ?: ($supplier->rfc_name ?: 'VALE');
        $normalized = Str::upper(trim((string) Str::ascii($source)));
        $words = preg_split('/\s+/', preg_replace('/\s+/', ' ', $normalized), -1, PREG_SPLIT_NO_EMPTY);

        $prefix = '';
        foreach ($words as $word) {
            if (preg_match('/[A-Z0-9]/', $word, $matches)) {
                $prefix .= $matches[0];
            }
        }

        if ($prefix === '') {
            $fallback = preg_replace('/[^A-Z0-9]/', '', $normalized);
            $prefix = substr($fallback, 0, 3);
        }

        return $prefix !== '' ? substr($prefix, 0, 8) : 'VALE';
    }

    private function generateUniqueFolio(string $prefix): array
    {
        return DB::transaction(function () use ($prefix) {
            $sequence = DB::table('material_voucher_folio_sequences')
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            $lastNumber = $sequence
                ? (int) $sequence->last_number
                : (int) (DB::table('material_vouchers')->where('folio_prefix', $prefix)->max('folio_number') ?? 0);

            $nextNumber = $lastNumber + 1;

            // Blindaje extra ante colisiones manuales en BD.
            while (DB::table('material_vouchers')
                ->where('folio', sprintf('%s-%03d', $prefix, $nextNumber))
                ->lockForUpdate()
                ->exists()) {
                $nextNumber++;
            }

            if ($sequence) {
                DB::table('material_voucher_folio_sequences')
                    ->where('id', $sequence->id)
                    ->update([
                        'last_number' => $nextNumber,
                        'updated_at'  => now(),
                    ]);
            } else {
                DB::table('material_voucher_folio_sequences')->insert([
                    'prefix'      => $prefix,
                    'last_number' => $nextNumber,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            return [
                'number' => $nextNumber,
                'folio'  => sprintf('%s-%03d', $prefix, $nextNumber),
            ];
        }, 3);
    }

    private function isEditable(MaterialVoucher $materialVoucher): bool
    {
        return auth()->user()?->hasRole('admin') || $materialVoucher->status === 'emitido';
    }
}
