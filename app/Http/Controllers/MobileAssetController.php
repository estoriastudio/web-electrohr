<?php

namespace App\Http\Controllers;

use App\Exports\MobileAssetExport;
use App\Models\MobileAsset;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class MobileAssetController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = trim($request->input('search', ''));

        $mobileAssets = MobileAsset::when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', '%' . $search . '%')
                        ->orWhere('folio', 'like', '%' . $search . '%')
                        ->orWhere('brand', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('mobile_assets.index', compact('mobileAssets', 'search'));
    }

    /**
     * Creation is handled via modal on the index view.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('mobile_assets.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'folio'          => 'nullable|string|max:100',
            'brand'          => 'nullable|string|max:255',
            'asset_function' => 'nullable|string|max:255',
            'type'           => 'nullable|in:movil,maquinaria,equipo_menor',
            'plates'         => 'nullable|string|max:20',
            'status'         => 'nullable|in:active,inactive',
        ]);

        // Placas solo aplican a tipo móvil
        if (($validated['type'] ?? null) !== 'movil') {
            $validated['plates'] = null;
        }

        $asset = MobileAsset::create($validated);

        $this->notification->send([
            'type'         => 'MobileAsset',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $asset->id,
            'data'         => 'creó un nuevo bien móvil ' . $asset->name,
        ]);

        return redirect()->route('mobile_assets.show', $asset)
            ->with('success', 'Bien móvil creado correctamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MobileAsset $mobileAsset): View
    {
        $mobileAsset->loadCount('maintenanceOrders');
        $mobileAsset->load(['maintenanceOrders' => fn ($q) => $q->orderByDesc('created_at')]);

        return view('mobile_assets.show', compact('mobileAsset'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MobileAsset $mobileAsset): View
    {
        return view('mobile_assets.edit', compact('mobileAsset'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MobileAsset $mobileAsset): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'folio'          => 'nullable|string|max:100',
            'brand'          => 'nullable|string|max:255',
            'asset_function' => 'nullable|string|max:255',
            'type'           => 'nullable|in:movil,maquinaria,equipo_menor',
            'plates'         => 'nullable|string|max:20',
            'status'         => 'nullable|in:active,inactive',
        ]);

        if (($validated['type'] ?? null) !== 'movil') {
            $validated['plates'] = null;
        }

        $mobileAsset->update($validated);

        $this->notification->send([
            'type'         => 'MobileAsset',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $mobileAsset->id,
            'data'         => 'actualizó la información del bien móvil ' . $mobileAsset->name,
        ]);

        return redirect()->route('mobile_assets.show', $mobileAsset)
            ->with('success', 'Bien móvil actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MobileAsset $mobileAsset): RedirectResponse
    {
        $name = $mobileAsset->name;

        $mobileAsset->delete();

        $this->notification->send([
            'type'         => 'MobileAsset',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $mobileAsset->id,
            'data'         => 'eliminó el bien móvil ' . $name,
        ]);

        return redirect()->route('mobile_assets.index')
            ->with('success', 'Bien móvil eliminado correctamente.');
    }

    /**
     * Export mobile assets to Excel.
     */
    public function export()
    {
        return Excel::download(new MobileAssetExport, 'bienes-mobiles.xlsx');
    }
}