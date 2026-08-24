<?php

namespace App\Http\Controllers;

use App\Exports\MobileAssetExport;
use App\Imports\MobileAssetImport;
use App\Models\MobileAsset;
use App\Models\MobileAssetDocument;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class MobileAssetController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search          = trim($request->input('search', ''));
        $type            = $request->input('type', 'parque_vehicular');
        $docStatus       = $request->input('doc_status', '');
        $documentMissing = trim($request->input('document_missing', ''));
        $galleryMissing  = $request->boolean('gallery_missing');
        $documentTypes   = MobileAssetDocument::DOCS_BY_TYPE[$type] ?? [];

        if (! in_array($documentMissing, $documentTypes, true)) {
            $documentMissing = '';
        }

        $coverageQuery = MobileAsset::query()->where('type', $type);
        $assetCount    = (clone $coverageQuery)->count();
        $documentCoverage = [];

        foreach ($documentTypes as $documentType) {
            $missingCount = (clone $coverageQuery)
                ->whereDoesntHave('documents', function ($query) use ($documentType) {
                    $query->where('document_type', $documentType)
                        ->whereNotNull('file_path');
                })
                ->count();

            $documentCoverage[$documentType] = [
                'uploaded' => $assetCount - $missingCount,
                'missing'  => $missingCount,
            ];
        }

        $galleryMissingCount = (clone $coverageQuery)
            ->where(function ($query) {
                $query->whereNull('photo1')
                    ->orWhereNull('photo2')
                    ->orWhereNull('photo3');
            })
            ->count();

        $uploadedPhotoCount = (clone $coverageQuery)->whereNotNull('photo1')->count()
            + (clone $coverageQuery)->whereNotNull('photo2')->count()
            + (clone $coverageQuery)->whereNotNull('photo3')->count();

        $galleryCoverage = [
            'uploaded' => $uploadedPhotoCount,
            'total'    => $assetCount * 3,
            'missing'  => $galleryMissingCount,
        ];

        $mobileAssets = MobileAsset::with('documents')
            ->where('type', $type)
            ->when($search, function ($q) use ($search) {
                $q->where('folio', $search);
            })
            ->when($documentMissing, function ($query) use ($documentMissing) {
                $query->whereDoesntHave('documents', function ($documentQuery) use ($documentMissing) {
                    $documentQuery->where('document_type', $documentMissing)
                        ->whereNotNull('file_path');
                });
            })
            ->when($galleryMissing, function ($query) {
                $query->where(function ($photoQuery) {
                    $photoQuery->whereNull('photo1')
                        ->orWhereNull('photo2')
                        ->orWhereNull('photo3');
                });
            })
            ->orderByRaw('folio IS NULL')
            ->orderByRaw('CAST(folio AS UNSIGNED)')
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        // Filtro por semáforo (post-query usando lógica del modelo)
        if ($docStatus) {
            $mobileAssets->setCollection(
                $mobileAssets->getCollection()->filter(
                    fn ($asset) => $asset->getWorstDocumentStatus() === $docStatus
                )->values()
            );
        }

        return view('mobile_assets.index', compact(
            'mobileAssets',
            'search',
            'type',
            'docStatus',
            'documentMissing',
            'galleryMissing',
            'documentTypes',
            'documentCoverage',
            'galleryCoverage',
            'assetCount',
        ));
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
            'policy'         => 'nullable|string|max:100',
            'card_number'    => 'nullable|string|max:100',
            'milage'         => 'nullable|string|max:50',
            'brand'          => 'nullable|string|max:255',
            'model'          => 'nullable|string|max:255',
            'year'           => 'nullable|string|max:10',
            'serial'         => 'nullable|string|max:255',
            'color'          => 'nullable|string|max:100',
            'operator'       => 'nullable|string|max:255',
            'asset_function' => 'nullable|string|max:255',
            'type'           => 'nullable|in:parque_vehicular,maquinaria_pesada,semiremolque',
            'plates'         => 'nullable|string|max:20',
            'status'         => 'nullable|in:activo,vendido,obsoleto,reparacion',
        ]);

        if (($validated['type'] ?? null) !== 'parque_vehicular') {
            $validated['plates'] = null;
        }

        if (isset($validated['milage']) && $validated['milage'] !== null && $validated['milage'] !== '') {
            $cleaned = preg_replace('/[^0-9]/', '', $validated['milage']);
            $validated['milage'] = $cleaned !== '' ? $cleaned : null;
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
        $mobileAsset->load([
            'documents',
            'maintenanceLogs',
            'maintenanceOrders' => fn ($q) => $q->orderByDesc('created_at'),
        ]);
        $mobileAsset->loadCount('maintenanceOrders');

        $applicableDocTypes = $mobileAsset->getApplicableDocumentTypes();
        $docLabels          = MobileAssetDocument::labelsEs();
        $noExpiryDocs       = MobileAssetDocument::NO_EXPIRY_DOCS;
        $nextMaintenanceFolio = $this->previewNextMaintenanceFolio($mobileAsset);
        $hasDownloadableDocuments = $mobileAsset->documents
            ->whereIn('document_type', $applicableDocTypes)
            ->whereNotNull('file_path')
            ->isNotEmpty();

        return view('mobile_assets.show', compact(
            'mobileAsset',
            'applicableDocTypes',
            'docLabels',
            'noExpiryDocs',
            'nextMaintenanceFolio',
            'hasDownloadableDocuments'
        ));
    }

    private function previewNextMaintenanceFolio(MobileAsset $mobileAsset): string
    {
        $prefix = $this->buildMaintenancePrefix($mobileAsset->name ?? 'VEH');
        $nextNumber = 1;

        foreach ($mobileAsset->maintenanceLogs as $log) {
            if (! is_string($log->folio)) {
                continue;
            }

            if (preg_match('/-(\d+)$/', $log->folio, $matches) === 1) {
                $currentNumber = (int) $matches[1];
                if ($currentNumber >= $nextNumber) {
                    $nextNumber = $currentNumber + 1;
                }
            }
        }

        return sprintf('%s-%03d', $prefix, $nextNumber);
    }

    private function buildMaintenancePrefix(string $name): string
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

    /**
     * Download all available applicable documents in a single ZIP file.
     */
    public function downloadDocumentsZip(MobileAsset $mobileAsset): RedirectResponse|BinaryFileResponse
    {
        $applicableTypes = $mobileAsset->getApplicableDocumentTypes();
        $docLabels = MobileAssetDocument::labelsEs();

        $documents = $mobileAsset->documents()
            ->whereIn('document_type', $applicableTypes)
            ->whereNotNull('file_path')
            ->get();

        if ($documents->isEmpty()) {
            return redirect()->route('mobile_assets.show', $mobileAsset)
                ->with('error', 'Este bien no tiene documentos para descargar.');
        }

        $tmpDir = storage_path('app/tmp');
        if (! File::isDirectory($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }

        $safeAssetName = Str::slug($mobileAsset->name ?: 'vehiculo');
        $zipPath = $tmpDir . '/documentos_' . $safeAssetName . '_' . $mobileAsset->id . '_' . time() . '.zip';

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            return redirect()->route('mobile_assets.show', $mobileAsset)
                ->with('error', 'No se pudo generar el archivo ZIP.');
        }

        foreach ($documents as $doc) {
            $disk = Storage::disk('s3');

            if (! $disk->exists($doc->file_path)) {
                continue;
            }

            $contents = $disk->get($doc->file_path);
            $extension = pathinfo($doc->file_path, PATHINFO_EXTENSION);
            $label = $docLabels[$doc->document_type] ?? $doc->document_type;
            $fileName = Str::slug($label) . ($extension ? '.' . strtolower($extension) : '');

            $zip->addFromString($fileName, $contents);
        }

        $zip->close();

        if (! file_exists($zipPath) || filesize($zipPath) === 0) {
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }

            return redirect()->route('mobile_assets.show', $mobileAsset)
                ->with('error', 'No se encontraron archivos válidos para incluir en el ZIP.');
        }

        $downloadName = sprintf('documentacion-%s-%s.zip', $safeAssetName, now()->format('Ymd_His'));

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
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
            'policy'         => 'nullable|string|max:100',
            'card_number'    => 'nullable|string|max:100',
            'milage'         => 'nullable|string|max:50',
            'brand'          => 'nullable|string|max:255',
            'model'          => 'nullable|string|max:255',
            'year'           => 'nullable|string|max:10',
            'serial'         => 'nullable|string|max:255',
            'color'          => 'nullable|string|max:100',
            'operator'       => 'nullable|string|max:255',
            'asset_function' => 'nullable|string|max:255',
            'type'           => 'nullable|in:parque_vehicular,maquinaria_pesada,semiremolque',
            'plates'         => 'nullable|string|max:20',
            'status'         => 'nullable|in:activo,vendido,obsoleto,reparacion',
        ]);

        if (($validated['type'] ?? null) !== 'parque_vehicular') {
            $validated['plates'] = null;
        }

        if (isset($validated['milage']) && $validated['milage'] !== null && $validated['milage'] !== '') {
            $cleaned = preg_replace('/[^0-9]/', '', $validated['milage']);
            $validated['milage'] = $cleaned !== '' ? $cleaned : null;
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
        $id   = $mobileAsset->id;

        // Limpiar archivos S3 del bien
        foreach ([1, 2, 3] as $slot) {
            $field = 'photo' . $slot;
            if ($mobileAsset->$field) {
                Storage::disk('s3')->delete($mobileAsset->$field);
            }
        }

        foreach ($mobileAsset->documents as $doc) {
            if ($doc->file_path) {
                Storage::disk('s3')->delete($doc->file_path);
            }
        }

        foreach ($mobileAsset->maintenanceLogs as $log) {
            if ($log->inspection_file) {
                Storage::disk('s3')->delete($log->inspection_file);
            }
        }

        $mobileAsset->delete();

        $this->notification->send([
            'type'         => 'MobileAsset',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $id,
            'data'         => 'eliminó el bien móvil ' . $name,
        ]);

        return redirect()->route('mobile_assets.index')
            ->with('success', 'Bien móvil eliminado correctamente.');
    }

    /**
     * Upload or replace a photo slot (1, 2, 3).
     */
    public function uploadPhoto(Request $request, MobileAsset $mobileAsset, int $slot): JsonResponse
    {
        if (! in_array($slot, [1, 2, 3])) {
            return response()->json(['error' => 'Slot inválido.'], 404);
        }

        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $field = 'photo' . $slot;

        // Eliminar foto anterior si existe
        if ($mobileAsset->$field) {
            Storage::disk('s3')->delete($mobileAsset->$field);
        }

        $uploadedFile = $request->file('file');
        $extension    = $uploadedFile->getClientOriginalExtension();
        $s3Path       = 'mobile_assets/' . $mobileAsset->id . '/photo' . $slot . '_' . time() . '.' . $extension;

        $stored = Storage::disk('s3')->put($s3Path, file_get_contents($uploadedFile));

        if (! $stored) {
            return response()->json(['error' => 'No se pudo subir la imagen a S3.'], 500);
        }

        $mobileAsset->update([$field => $s3Path]);

        return response()->json([
            'url'  => Storage::disk('s3')->url($s3Path),
            'path' => $s3Path,
            'slot' => $slot,
        ]);
    }

    /**
     * Delete a photo slot.
     */
    public function deletePhoto(MobileAsset $mobileAsset, int $slot): RedirectResponse
    {
        if (! in_array($slot, [1, 2, 3])) {
            abort(404);
        }

        $field = 'photo' . $slot;

        if ($mobileAsset->$field) {
            Storage::disk('s3')->delete($mobileAsset->$field);
            $mobileAsset->update([$field => null]);
        }

        return redirect()->route('mobile_assets.show', $mobileAsset)
            ->with('success', 'Fotografía eliminada.');
    }

    /**
     * Upload or update a document in the checklist.
     */
    public function updateDocument(Request $request, MobileAsset $mobileAsset, string $docType): RedirectResponse
    {
        $validTypes = array_keys(MobileAssetDocument::labelsEs());

        if (! in_array($docType, $validTypes)) {
            abort(404);
        }

        $noExpiry = in_array($docType, MobileAssetDocument::NO_EXPIRY_DOCS);

        $request->validate([
            'file'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'expiry_date' => 'nullable|date',
        ]);

        $doc = $mobileAsset->documents()->firstOrNew(['document_type' => $docType]);

        if ($request->hasFile('file')) {
            if ($doc->file_path) {
                Storage::disk('s3')->delete($doc->file_path);
            }

            $uploadedFile = $request->file('file');
            $extension    = $uploadedFile->getClientOriginalExtension();
            $s3Path       = 'mobile_assets/' . $mobileAsset->id . '/docs/' . $docType . '_' . time() . '.' . $extension;

            Storage::disk('s3')->put($s3Path, file_get_contents($uploadedFile));

            $doc->file_path   = $s3Path;
            $doc->uploaded_at = now()->toDateString();
        }

        if (! $noExpiry && $request->filled('expiry_date')) {
            $doc->expiry_date = $request->input('expiry_date');
        }

        $doc->save();

        return redirect()->route('mobile_assets.show', $mobileAsset)
            ->with('success', 'Documento actualizado correctamente.');
    }

    /**
     * Export mobile assets to Excel.
     */
    public function export()
    {
        return Excel::download(new MobileAssetExport, 'bienes-mobiles.xlsx');
    }

    /**
     * Import mobile assets from Excel.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        DB::connection()->disableQueryLog();

        Excel::import(new MobileAssetImport, $request->file('file'));

        return redirect()->route('mobile_assets.index')
            ->with('success', 'Bienes móviles importados correctamente.');
    }
}