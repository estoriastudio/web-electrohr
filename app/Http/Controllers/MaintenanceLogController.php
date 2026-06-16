<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceLog;
use App\Models\MobileAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaintenanceLogController extends Controller
{
    public function store(Request $request, MobileAsset $mobileAsset): RedirectResponse
    {
        $validated = $request->validate([
            'folio'                 => 'nullable|string|max:100',
            'maintenance_date'      => 'required|date',
            'next_maintenance_date' => 'nullable|date|after_or_equal:maintenance_date',
            'notes'                 => 'nullable|string|max:2000',
            'inspection_file'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->hasFile('inspection_file')) {
            $uploadedFile = $request->file('inspection_file');
            $extension    = $uploadedFile->getClientOriginalExtension();
            $s3Path       = 'mobile_assets/' . $mobileAsset->id . '/maintenance/inspeccion_' . time() . '.' . $extension;
            Storage::disk('s3')->put($s3Path, file_get_contents($uploadedFile));
            $validated['inspection_file'] = $s3Path;
        }

        $mobileAsset->maintenanceLogs()->create($validated);

        return redirect()->route('mobile_assets.show', $mobileAsset)
            ->with('success', 'Entrada de bitácora registrada correctamente.');
    }

    public function destroy(MobileAsset $mobileAsset, MaintenanceLog $maintenanceLog): RedirectResponse
    {
        abort_if($maintenanceLog->mobile_asset_id !== $mobileAsset->id, 404);

        if ($maintenanceLog->inspection_file) {
            Storage::disk('s3')->delete($maintenanceLog->inspection_file);
        }

        $maintenanceLog->delete();

        return redirect()->route('mobile_assets.show', $mobileAsset)
            ->with('success', 'Entrada de bitácora eliminada.');
    }
}
