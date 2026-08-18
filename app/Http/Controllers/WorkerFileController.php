<?php

namespace App\Http\Controllers;

use App\Models\Worker;
use App\Models\WorkerFile;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class WorkerFileController extends Controller
{
    private const FILE_COLUMNS = [
        'ine_path',
        'birth_certificate_path',
        'address_proof_path',
        'nss_path',
        'license_path',
        'tax_status_path',
        'medical_certificate_path',
        'cv_path',
        'emergency_contact_ine_path',
    ];

    private const EXPIRATION_COLUMNS = [
        'ine_expiration_date',
        'birth_certificate_expiration_date',
        'address_proof_expiration_date',
        'nss_expiration_date',
        'license_expiration_date',
        'tax_status_expiration_date',
        'medical_certificate_expiration_date',
        'cv_expiration_date',
        'emergency_contact_ine_expiration_date',
    ];

    private const WORKER_EXPIRATION_COLUMNS = [
        'ine_expiration_date',
        'medical_certificate_expiration_date',
    ];

    public function __construct(private NotificationService $notification) {}

    public function show(Worker $worker): View
    {
        $workerFile = WorkerFile::firstOrCreate(['worker_id' => $worker->id]);

        return view('human_resources.workers.file', compact('worker', 'workerFile'));
    }

    public function edit(Worker $worker): RedirectResponse
    {
        return redirect()->route('human_resources.workers.file.show', $worker);
    }

    public function update(Request $request, Worker $worker): RedirectResponse
    {
        $data = $this->validatedData($request);
        $workerFile = WorkerFile::firstOrCreate(['worker_id' => $worker->id]);
        $replacedPaths = [];
        $workerExpirationData = [];

        foreach (self::WORKER_EXPIRATION_COLUMNS as $column) {
            $workerExpirationData[$column] = $data[$column] ?? null;
            unset($data[$column]);
        }

        foreach (self::FILE_COLUMNS as $column) {
            if (! $request->hasFile($column)) {
                continue;
            }

            $path = $request->file($column)->store("worker-files/{$worker->id}", 's3');

            if (! $path) {
                return redirect()->route('human_resources.workers.file.show', $worker)
                    ->with('error', 'No se pudo almacenar uno de los documentos.');
            }

            $replacedPaths[] = $workerFile->{$column};
            $data[$column] = $path;
        }

        $workerFile->update($data);
        $worker->update($workerExpirationData);

        foreach (array_filter($replacedPaths) as $path) {
            Storage::disk('s3')->delete($path);
        }

        $this->notification->send([
            'type' => 'WorkerFile',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $workerFile->id,
            'data' => "actualizó el expediente de {$worker->first_name} {$worker->last_name}.",
        ]);

        return redirect()->route('human_resources.workers.file.show', $worker)
            ->with('success', 'Expediente actualizado correctamente.');
    }

    public function download(Worker $worker, string $document): mixed
    {
        abort_unless(in_array($document, self::FILE_COLUMNS, true), 404);

        $workerFile = $worker->file;
        $path = $workerFile?->{$document};

        abort_unless($path && Storage::disk('s3')->exists($path), 404);

        return Storage::disk('s3')->download($path);
    }

    private function validatedData(Request $request): array
    {
        $rules = ['notes' => 'nullable|string|max:1000'];

        foreach (self::FILE_COLUMNS as $column) {
            $rules[$column] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240';
        }

        foreach (self::EXPIRATION_COLUMNS as $column) {
            $rules[$column] = 'nullable|date';
        }

        foreach (self::WORKER_EXPIRATION_COLUMNS as $column) {
            $rules[$column] = 'nullable|date';
        }

        return $request->validate($rules);
    }
}