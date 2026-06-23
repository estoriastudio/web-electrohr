<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\ProjectDocument;
use App\Imports\ProjectImport;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(private NotificationService $notification) {}
    public function index(Request $request): View
    {
        $search = trim($request->input('search', ''));

        $projects = Project::query()
            ->withCount('works')
            ->with('documents')
            ->addSelect([
                'project_value' => ProjectWork::query()
                    ->selectRaw('COALESCE(SUM(CAST(REPLACE(contract_value, ",", "") AS DECIMAL(15,2))), 0)')
                    ->whereColumn('project_id', 'projects.id'),
            ])
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('client_name', 'like', "%{$search}%"))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('projects.index', compact('projects', 'search'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('projects.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'city'        => 'nullable|string|max:255',
            'state'       => 'nullable|string|max:255',
        ]);

        $project = Project::create(array_merge($validated, ['status' => 'active']));

        $this->notification->send([
            'type'         => 'Project',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $project->id,
            'data'         => 'creó el proyecto "' . $project->name . '".',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Proyecto creado correctamente.');
    }

    public function show(Project $project): View
    {
        $project->setAttribute(
            'project_value',
            (float) $project->works()
                ->selectRaw('COALESCE(SUM(CAST(REPLACE(contract_value, ",", "") AS DECIMAL(15,2))), 0) as total')
                ->value('total')
        );
        $project->loadCount('works');
        $project->load([
            'works'     => fn ($q) => $q->withCount('purchaseOrders'),
            'documents',
        ]);

        $docCategories = ProjectDocument::CATEGORIES;

        return view('projects.show', compact('project', 'docCategories'));
    }

    public function edit(Project $project): View
    {
        return view('projects.edit', compact('project'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
            'city'        => 'nullable|string|max:255',
            'state'       => 'nullable|string|max:255',
            'status'      => 'required|in:active,inactive',
        ]);

        $project->update($validated);

        $this->notification->send([
            'type'         => 'Project',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $project->id,
            'data'         => 'actualizó el proyecto "' . $project->name . '".',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Proyecto actualizado correctamente.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $name = $project->name;

        $project->delete();

        $this->notification->send([
            'type'         => 'Project',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $project->id,
            'data'         => 'eliminó el proyecto "' . $name . '".',
        ]);

        return redirect()->route('projects.index')
            ->with('success', 'Proyecto eliminado correctamente.');
    }

    public function worksJson(Project $project): JsonResponse
    {
        $works = $project->works()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($works);
    }

    public function uploadDocument(Request $request, Project $project, string $docType): RedirectResponse
    {
        if (! array_key_exists($docType, ProjectDocument::allDocTypes())) {
            abort(404);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:102400',
        ]);

        $doc = $project->documents()->firstOrNew(['document_type' => $docType]);

        if ($doc->file_path) {
            Storage::disk('s3')->delete($doc->file_path);
        }

        $uploadedFile = $request->file('file');
        $extension    = $uploadedFile->getClientOriginalExtension();
        $s3Path       = 'projects/' . $project->id . '/docs/' . $docType . '_' . time() . '.' . $extension;

        Storage::disk('s3')->put($s3Path, file_get_contents($uploadedFile));

        $doc->file_path   = $s3Path;
        $doc->uploaded_at = now()->toDateString();
        $doc->save();

        return redirect()->route('projects.show', $project)
            ->with('success', 'Documento actualizado correctamente.');
    }

    public function initChunkUpload(Request $request, Project $project, string $docType): JsonResponse
    {
        if (! array_key_exists($docType, ProjectDocument::allDocTypes())) {
            abort(404);
        }

        $validated = $request->validate([
            'filename' => 'required|string|max:255',
            'size' => 'required|integer|min:1|max:104857600',
        ]);

        $extension = strtolower(pathinfo($validated['filename'], PATHINFO_EXTENSION));
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            return response()->json(['message' => 'Tipo de archivo no permitido.'], 422);
        }

        $chunkSize = 4 * 1024 * 1024; // 4 MB por chunk
        $uploadId = (string) Str::uuid();
        $fileKey = 'projects/' . $project->id . '/docs/' . $docType . '_' . now()->timestamp . '_' . Str::lower(Str::random(8)) . '.' . $extension;

        cache()->put('project_doc_chunk_upload_' . $uploadId, [
            'upload_id' => $uploadId,
            'project_id' => $project->id,
            'doc_type' => $docType,
            'user_id' => Auth::id(),
            'filename' => $validated['filename'],
            'size' => (int) $validated['size'],
            'chunk_size' => $chunkSize,
            'total_chunks' => (int) ceil(((int) $validated['size']) / $chunkSize),
            'uploaded_chunks' => [],
            'file_key' => $fileKey,
            'created_at' => now()->toDateTimeString(),
        ], now()->addHour());

        return response()->json([
            'upload_id' => $uploadId,
            'chunk_size' => $chunkSize,
            'total_chunks' => (int) ceil(((int) $validated['size']) / $chunkSize),
            'max_parallel' => 3,
        ]);
    }

    public function uploadChunk(Request $request, Project $project, string $docType): JsonResponse
    {
        if (! array_key_exists($docType, ProjectDocument::allDocTypes())) {
            abort(404);
        }

        $validated = $request->validate([
            'upload_id' => 'required|string|max:100',
            'chunk_number' => 'required|integer|min:0',
            'chunk' => 'required|file|max:5120',
        ]);

        $sessionKey = 'project_doc_chunk_upload_' . $validated['upload_id'];
        $session = cache()->get($sessionKey);

        if (! $session) {
            return response()->json(['message' => 'Sesion de carga no encontrada o expirada.'], 422);
        }

        if ((int) $session['project_id'] !== (int) $project->id ||
            $session['doc_type'] !== $docType ||
            (int) $session['user_id'] !== (int) Auth::id()) {
            return response()->json(['message' => 'Sesion de carga invalida.'], 403);
        }

        if ((int) $validated['chunk_number'] >= (int) $session['total_chunks']) {
            return response()->json(['message' => 'Indice de chunk fuera de rango.'], 422);
        }

        $tempDir = storage_path('app/temp/project-doc-chunks/' . $validated['upload_id']);
        if (! File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $chunkName = 'chunk_' . $validated['chunk_number'] . '.part';
        $request->file('chunk')->move($tempDir, $chunkName);

        $session['uploaded_chunks'][(string) $validated['chunk_number']] = true;
        cache()->put($sessionKey, $session, now()->addHour());

        $uploadedCount = count($session['uploaded_chunks']);
        $progress = $session['total_chunks'] > 0
            ? round(($uploadedCount / (int) $session['total_chunks']) * 100, 2)
            : 0;

        return response()->json([
            'uploaded_chunks' => $uploadedCount,
            'total_chunks' => (int) $session['total_chunks'],
            'progress' => $progress,
        ]);
    }

    public function finalizeChunkUpload(Request $request, Project $project, string $docType): JsonResponse
    {
        if (! array_key_exists($docType, ProjectDocument::allDocTypes())) {
            abort(404);
        }

        $validated = $request->validate([
            'upload_id' => 'required|string|max:100',
        ]);

        $sessionKey = 'project_doc_chunk_upload_' . $validated['upload_id'];
        $session = cache()->get($sessionKey);

        if (! $session) {
            return response()->json(['message' => 'Sesion de carga no encontrada o expirada.'], 422);
        }

        if ((int) $session['project_id'] !== (int) $project->id ||
            $session['doc_type'] !== $docType ||
            (int) $session['user_id'] !== (int) Auth::id()) {
            return response()->json(['message' => 'Sesion de carga invalida.'], 403);
        }

        $uploadId = $validated['upload_id'];
        $tempDir = storage_path('app/temp/project-doc-chunks/' . $uploadId);
        $finalDir = storage_path('app/temp/project-doc-final');
        $finalFilePath = $finalDir . '/' . $uploadId . '_' . basename($session['filename']);

        if (! File::exists($finalDir)) {
            File::makeDirectory($finalDir, 0755, true);
        }

        $expectedChunks = (int) $session['total_chunks'];
        if (count($session['uploaded_chunks']) < $expectedChunks) {
            return response()->json(['message' => 'Aun faltan chunks por subir.'], 422);
        }

        $finalHandle = fopen($finalFilePath, 'wb');
        if (! $finalHandle) {
            return response()->json(['message' => 'No se pudo preparar el archivo final.'], 500);
        }

        try {
            for ($i = 0; $i < $expectedChunks; $i++) {
                $chunkPath = $tempDir . '/chunk_' . $i . '.part';
                if (! File::exists($chunkPath)) {
                    fclose($finalHandle);
                    return response()->json(['message' => 'Falta el chunk #' . $i . '.'], 422);
                }

                $chunkHandle = fopen($chunkPath, 'rb');
                stream_copy_to_stream($chunkHandle, $finalHandle);
                fclose($chunkHandle);
            }
            fclose($finalHandle);

            $stream = fopen($finalFilePath, 'rb');
            Storage::disk('s3')->put($session['file_key'], $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $doc = $project->documents()->firstOrNew(['document_type' => $docType]);
            $previousFilePath = $doc->file_path;

            $doc->file_path = $session['file_key'];
            $doc->uploaded_at = now()->toDateString();
            $doc->save();

            if ($previousFilePath && $previousFilePath !== $session['file_key']) {
                Storage::disk('s3')->delete($previousFilePath);
            }

            $this->cleanupChunkUpload($uploadId, $finalFilePath, $sessionKey);

            return response()->json([
                'message' => 'Documento actualizado correctamente.',
                'file_url' => Storage::disk('s3')->url($session['file_key']),
            ]);
        } catch (\Throwable $e) {
            if (is_resource($finalHandle)) {
                fclose($finalHandle);
            }

            return response()->json([
                'message' => 'Error al finalizar la carga por partes.',
            ], 500);
        }
    }

    public function abortChunkUpload(Request $request, Project $project, string $docType): JsonResponse
    {
        if (! array_key_exists($docType, ProjectDocument::allDocTypes())) {
            abort(404);
        }

        $validated = $request->validate([
            'upload_id' => 'required|string|max:100',
        ]);

        $sessionKey = 'project_doc_chunk_upload_' . $validated['upload_id'];
        $session = cache()->get($sessionKey);

        if ($session &&
            (int) $session['project_id'] === (int) $project->id &&
            $session['doc_type'] === $docType &&
            (int) $session['user_id'] === (int) Auth::id()) {
            $this->cleanupChunkUpload($validated['upload_id'], null, $sessionKey);
        }

        return response()->json(['message' => 'Carga cancelada.']);
    }

    private function cleanupChunkUpload(string $uploadId, ?string $finalFilePath, string $sessionKey): void
    {
        $tempDir = storage_path('app/temp/project-doc-chunks/' . $uploadId);
        if (File::exists($tempDir)) {
            File::deleteDirectory($tempDir);
        }

        if ($finalFilePath && File::exists($finalFilePath)) {
            File::delete($finalFilePath);
        }

        cache()->forget($sessionKey);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        Excel::import(new ProjectImport, $request->file('file'));

        return redirect()->route('projects.index')
            ->with('success', 'Proyectos y obras importados correctamente.');
    }
}

