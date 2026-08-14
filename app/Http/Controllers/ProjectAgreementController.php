<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAgreement;
use App\Models\ProjectWork;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectAgreementController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validateAgreementData($request, $project);
        $request->validate([
            'appointments_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:102400',
        ]);

        $file = $request->file('appointments_file');
        [$filePath, $fileName, $fileMime] = $this->storeUploadedFile($file, $project);

        try {
            $agreement = $this->createAgreement($project, $data, $filePath, $fileName, $fileMime);
        } catch (\Throwable $exception) {
            Storage::disk('s3')->delete($filePath);
            throw $exception;
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Convenio "' . $agreement->agreement_number . '" registrado correctamente.');
    }

    public function initChunkUpload(Request $request, Project $project): JsonResponse
    {
        $data = $this->validateAgreementData($request, $project);
        $file = $request->validate([
            'filename' => 'required|string|max:255',
            'size' => 'required|integer|min:1|max:104857600',
        ]);

        $extension = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            return response()->json(['message' => 'Tipo de archivo no permitido.'], 422);
        }

        $chunkSize = 4 * 1024 * 1024;
        $uploadId = (string) Str::uuid();
        cache()->put($this->chunkSessionKey($uploadId), [
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'agreement_data' => $data,
            'filename' => $file['filename'],
            'size' => (int) $file['size'],
            'chunk_size' => $chunkSize,
            'total_chunks' => (int) ceil(((int) $file['size']) / $chunkSize),
            'uploaded_chunks' => [],
            'file_key' => $this->agreementFilePath($project, $extension),
        ], now()->addHour());

        return response()->json([
            'upload_id' => $uploadId,
            'chunk_size' => $chunkSize,
            'max_parallel' => 3,
        ]);
    }

    public function uploadChunk(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'upload_id' => 'required|string|max:100',
            'chunk_number' => 'required|integer|min:0',
            'chunk' => 'required|file|max:5120',
        ]);

        $session = $this->getChunkSession($validated['upload_id'], $project);
        if ((int) $validated['chunk_number'] >= (int) $session['total_chunks']) {
            return response()->json(['message' => 'Indice de chunk fuera de rango.'], 422);
        }

        $tempDir = storage_path('app/temp/project-agreement-chunks/' . $validated['upload_id']);
        if (! File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $request->file('chunk')->move($tempDir, 'chunk_' . $validated['chunk_number'] . '.part');
        $session['uploaded_chunks'][(string) $validated['chunk_number']] = true;
        cache()->put($this->chunkSessionKey($validated['upload_id']), $session, now()->addHour());

        return response()->json([
            'uploaded_chunks' => count($session['uploaded_chunks']),
            'total_chunks' => (int) $session['total_chunks'],
        ]);
    }

    public function finalizeChunkUpload(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate(['upload_id' => 'required|string|max:100']);
        $uploadId = $validated['upload_id'];
        $session = $this->getChunkSession($uploadId, $project);

        if (count($session['uploaded_chunks']) < (int) $session['total_chunks']) {
            return response()->json(['message' => 'Aun faltan chunks por subir.'], 422);
        }

        $tempDir = storage_path('app/temp/project-agreement-chunks/' . $uploadId);
        $finalDir = storage_path('app/temp/project-agreement-final');
        $finalFilePath = $finalDir . '/' . $uploadId . '_' . basename($session['filename']);

        if (! File::exists($finalDir)) {
            File::makeDirectory($finalDir, 0755, true);
        }

        $finalHandle = fopen($finalFilePath, 'wb');
        if (! $finalHandle) {
            return response()->json(['message' => 'No se pudo preparar el archivo final.'], 500);
        }

        try {
            for ($index = 0; $index < (int) $session['total_chunks']; $index++) {
                $chunkPath = $tempDir . '/chunk_' . $index . '.part';
                if (! File::exists($chunkPath)) {
                    fclose($finalHandle);
                    return response()->json(['message' => 'Falta el chunk #' . $index . '.'], 422);
                }

                $chunkHandle = fopen($chunkPath, 'rb');
                stream_copy_to_stream($chunkHandle, $finalHandle);
                fclose($chunkHandle);
            }
            fclose($finalHandle);

            $stream = fopen($finalFilePath, 'rb');
            if (! $stream || ! Storage::disk('s3')->put($session['file_key'], $stream)) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                throw new \RuntimeException('No se pudo almacenar el archivo.');
            }
            fclose($stream);

            $agreement = $this->createAgreement(
                $project,
                $session['agreement_data'],
                $session['file_key'],
                $session['filename'],
                $this->mimeTypeFromExtension($session['filename'])
            );

            $this->cleanupChunkUpload($uploadId, $finalFilePath);

            return response()->json([
                'message' => 'Convenio "' . $agreement->agreement_number . '" registrado correctamente.',
            ]);
        } catch (\Throwable $exception) {
            if (is_resource($finalHandle)) {
                fclose($finalHandle);
            }

            return response()->json(['message' => 'Error al finalizar la carga del convenio.'], 500);
        }
    }

    public function abortChunkUpload(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate(['upload_id' => 'required|string|max:100']);
        $this->getChunkSession($validated['upload_id'], $project);
        $this->cleanupChunkUpload($validated['upload_id']);

        return response()->json(['message' => 'Carga cancelada.']);
    }

    public function downloadAppointments(Project $project, ProjectAgreement $projectAgreement): RedirectResponse
    {
        if ((int) $projectAgreement->project_id !== (int) $project->id) {
            abort(404);
        }

        return redirect()->away(Storage::disk('s3')->url($projectAgreement->appointments_file_path));
    }

    private function validateAgreementData(Request $request, Project $project): array
    {
        $validated = $request->validate([
            'work_ids' => 'required|array|min:1',
            'work_ids.*' => 'required|integer|exists:project_works,id',
            'contracted_amount' => 'required|numeric|min:0',
            'current_amount' => 'required|numeric|min:0',
            'contracted_end_date' => 'required|date',
            'contracted_term_days' => 'required|integer|min:0',
            'current_end_date' => 'required|date',
            'agreement_number' => 'required|string|max:255',
            'new_amount' => 'required|numeric|min:0',
            'new_end_date' => 'required|date',
        ]);

        $workIds = collect($validated['work_ids'])
            ->map(fn ($workId) => (int) $workId)
            ->unique()
            ->values();

        if (ProjectWork::where('project_id', $project->id)->whereIn('id', $workIds)->count() !== $workIds->count()) {
            throw ValidationException::withMessages([
                'work_ids' => 'Todas las obras seleccionadas deben pertenecer al proyecto.',
            ]);
        }

        $validated['work_ids'] = $workIds->all();

        return $validated;
    }

    private function createAgreement(
        Project $project,
        array $data,
        string $filePath,
        string $fileName,
        string $fileMime
    ): ProjectAgreement {
        $workIds = $data['work_ids'];
        unset($data['work_ids']);

        $agreement = DB::transaction(function () use ($project, $data, $workIds, $filePath, $fileName, $fileMime) {
            $agreement = $project->agreements()->create(array_merge($data, [
                'appointments_file_path' => $filePath,
                'appointments_file_name' => $fileName,
                'appointments_file_mime' => $fileMime,
            ]));

            $agreement->works()->sync($workIds);
            $project->update(['current_agreement_value' => $data['new_amount']]);
            ProjectWork::where('project_id', $project->id)
                ->whereIn('id', $workIds)
                ->update(['contract_end_date' => $data['new_end_date']]);

            return $agreement;
        });

        $this->notification->send([
            'type' => 'ProjectAgreement',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $agreement->id,
            'data' => 'registró el convenio "' . $agreement->agreement_number . '" del proyecto "' . $project->name . '".',
        ]);

        return $agreement;
    }

    private function storeUploadedFile(UploadedFile $file, Project $project): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filePath = $this->agreementFilePath($project, $extension);
        $stream = fopen($file->getRealPath(), 'rb');

        if (! $stream || ! Storage::disk('s3')->put($filePath, $stream)) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new \RuntimeException('No se pudo almacenar el archivo.');
        }

        fclose($stream);

        return [$filePath, $file->getClientOriginalName(), $file->getClientMimeType()];
    }

    private function getChunkSession(string $uploadId, Project $project): array
    {
        $session = cache()->get($this->chunkSessionKey($uploadId));
        if (! $session) {
            abort(response()->json(['message' => 'Sesion de carga no encontrada o expirada.'], 422));
        }

        if ((int) $session['project_id'] !== (int) $project->id ||
            (int) $session['user_id'] !== (int) Auth::id()) {
            abort(response()->json(['message' => 'Sesion de carga invalida.'], 403));
        }

        return $session;
    }

    private function cleanupChunkUpload(string $uploadId, ?string $finalFilePath = null): void
    {
        $tempDir = storage_path('app/temp/project-agreement-chunks/' . $uploadId);
        if (File::exists($tempDir)) {
            File::deleteDirectory($tempDir);
        }

        if ($finalFilePath && File::exists($finalFilePath)) {
            File::delete($finalFilePath);
        }

        cache()->forget($this->chunkSessionKey($uploadId));
    }

    private function agreementFilePath(Project $project, string $extension): string
    {
        return 'projects/' . $project->id . '/agreements/' . now()->timestamp . '_' . Str::lower(Str::random(8)) . '.' . $extension;
    }

    private function chunkSessionKey(string $uploadId): string
    {
        return 'project_agreement_chunk_upload_' . $uploadId;
    }

    private function mimeTypeFromExtension(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}
