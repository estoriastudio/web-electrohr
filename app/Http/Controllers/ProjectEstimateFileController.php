<?php

namespace App\Http\Controllers;

use App\Models\ProjectEstimate;
use App\Models\ProjectEstimateFile;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectEstimateFileController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function store(Request $request, ProjectEstimate $estimate): RedirectResponse
    {
        $validated = $request->validateWithBag('estimateFiles', [
            'file_type' => ['required', Rule::in(array_keys(ProjectEstimateFile::TYPES))],
        ]);

        $extensions = implode(',', ProjectEstimateFile::TYPES[$validated['file_type']]['extensions']);

        $request->validateWithBag('estimateFiles', [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:'.$extensions, 'max:10240'],
        ], [
            'files.required' => 'No se recibieron archivos. Verifica que el tamaño total del lote no exceda el límite del servidor.',
            'files.*.mimes' => 'Uno de los archivos no tiene un formato permitido para este tipo ('.$extensions.').',
            'files.*.max' => 'Cada archivo debe pesar máximo 10 MB.',
            'files.*.uploaded' => 'No se pudo cargar uno de los archivos. Verifica que no exceda el tamaño permitido.',
        ]);

        $uploadedPaths = [];

        try {
            DB::transaction(function () use ($request, $estimate, $validated, &$uploadedPaths) {
                foreach ($request->file('files') as $file) {
                    $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
                    $path = Storage::disk('s3')->putFileAs(
                        "projects/{$estimate->project_id}/estimates/{$estimate->id}",
                        $file,
                        Str::uuid().'.'.$extension
                    );

                    if (! $path) {
                        throw new \RuntimeException('No se pudo guardar el archivo en S3.');
                    }

                    $uploadedPaths[] = $path;

                    $estimate->files()->create([
                        'file_type' => $validated['file_type'],
                        'file_path' => $path,
                        'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                        'uploaded_by' => Auth::id(),
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            $this->deleteStoredPaths($uploadedPaths);
            report($exception);

            return back()->withInput()->withErrors([
                'files' => 'No se pudieron almacenar los archivos en S3. Intenta nuevamente.',
            ], 'estimateFiles');
        }

        $count = count($uploadedPaths);

        $this->notification->send([
            'type' => 'ProjectEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $estimate->id,
            'data' => 'cargó '.$count.($count === 1 ? ' archivo' : ' archivos').' ('.ProjectEstimateFile::TYPES[$validated['file_type']]['label'].') en la estimación '.$estimate->estimate_number.' del proyecto "'.$estimate->project->name.'".',
        ]);

        return redirect()->route('projects.show', $estimate->project)
            ->with('success', $count === 1 ? 'Archivo cargado correctamente.' : "{$count} archivos cargados correctamente.");
    }

    public function download(ProjectEstimateFile $estimateFile): mixed
    {
        abort_unless(Storage::disk('s3')->exists($estimateFile->file_path), 404);

        return Storage::disk('s3')->download($estimateFile->file_path, $estimateFile->original_name);
    }

    public function destroy(ProjectEstimateFile $estimateFile): RedirectResponse
    {
        $estimate = $estimateFile->estimate;
        $path = $estimateFile->file_path;
        $label = $estimateFile->original_name;

        $estimateFile->delete();
        $this->deleteStoredPaths([$path]);

        $this->notification->send([
            'type' => 'ProjectEstimate',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $estimate->id,
            'data' => 'eliminó el archivo '.$label.' de la estimación '.$estimate->estimate_number.' del proyecto "'.$estimate->project->name.'".',
        ]);

        return redirect()->route('projects.show', $estimate->project)
            ->with('success', 'Archivo de la estimación eliminado correctamente.');
    }

    private function deleteStoredPaths(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            if (Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        }
    }
}
