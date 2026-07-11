<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceLog;
use App\Models\MobileAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MaintenanceLogController extends Controller
{
    public function store(Request $request, MobileAsset $mobileAsset): RedirectResponse
    {
        $validated = $request->validate([
            'maintenance_date'      => 'required|date',
            'next_maintenance_date' => 'nullable|date|after_or_equal:maintenance_date',
            'mileage_due'           => 'nullable|integer|min:0',
            'notes'                 => 'nullable|string|max:2000',
            'inspection_file'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $validated['folio'] = $this->generateNextFolio($mobileAsset);

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

    private function generateNextFolio(MobileAsset $mobileAsset): string
    {
        $prefix = $this->buildAssetPrefix($mobileAsset->name ?? 'VEH');

        return DB::transaction(function () use ($mobileAsset, $prefix) {
            $nextNumber = 1;

            $existingFolios = MaintenanceLog::query()
                ->where('mobile_asset_id', $mobileAsset->id)
                ->lockForUpdate()
                ->pluck('folio');

            foreach ($existingFolios as $folio) {
                if (! is_string($folio)) {
                    continue;
                }

                if (preg_match('/-(\d+)$/', $folio, $matches) === 1) {
                    $currentNumber = (int) $matches[1];
                    if ($currentNumber >= $nextNumber) {
                        $nextNumber = $currentNumber + 1;
                    }
                }
            }

            while (MaintenanceLog::query()
                ->where('mobile_asset_id', $mobileAsset->id)
                ->where('folio', sprintf('%s-%03d', $prefix, $nextNumber))
                ->lockForUpdate()
                ->exists()) {
                $nextNumber++;
            }

            return sprintf('%s-%03d', $prefix, $nextNumber);
        }, 3);
    }

    private function buildAssetPrefix(string $name): string
    {
        $normalized = Str::upper(trim((string) Str::ascii($name)));
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

        return $prefix !== '' ? substr($prefix, 0, 8) : 'VEH';
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
