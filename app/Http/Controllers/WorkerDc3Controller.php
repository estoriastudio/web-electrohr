<?php

namespace App\Http\Controllers;

use App\Models\Worker;
use App\Models\WorkerDc3;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WorkerDc3Controller extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function store(Request $request, Worker $worker): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $path = $request->file('file')->store("worker-files/{$worker->id}/dc3", 's3');

        if (! $path) {
            return redirect()->route('human_resources.workers.file.show', $worker)
                ->with('error', 'No se pudo almacenar el archivo DC3.');
        }

        $dc3 = $worker->dc3s()->create([
            'label' => $data['label'],
            'file_path' => $path,
        ]);

        $this->notification->send([
            'type' => 'WorkerDc3',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $dc3->id,
            'data' => "agregó {$dc3->label} al expediente de {$worker->first_name} {$worker->last_name}.",
        ]);

        return redirect()->route('human_resources.workers.file.show', $worker)
            ->with('success', 'Certificación DC3 agregada correctamente.');
    }

    public function download(Worker $worker, WorkerDc3 $dc3): mixed
    {
        $this->ensureWorkerAccess($worker);
        $this->ensureBelongsToWorker($worker, $dc3);

        abort_unless(Storage::disk('s3')->exists($dc3->file_path), 404);

        return Storage::disk('s3')->download($dc3->file_path);
    }

    public function destroy(Worker $worker, WorkerDc3 $dc3): RedirectResponse
    {
        $this->ensureBelongsToWorker($worker, $dc3);

        Storage::disk('s3')->delete($dc3->file_path);
        $dc3->delete();

        $this->notification->send([
            'type' => 'WorkerDc3',
            'action_by' => Auth::id(),
            'model_action' => 'destroy',
            'model_id' => $dc3->id,
            'data' => "eliminó {$dc3->label} del expediente de {$worker->first_name} {$worker->last_name}.",
        ]);

        return redirect()->route('human_resources.workers.file.show', $worker)
            ->with('success', 'Certificación DC3 eliminada correctamente.');
    }

    private function ensureBelongsToWorker(Worker $worker, WorkerDc3 $dc3): void
    {
        abort_unless($dc3->worker_id === $worker->id, 404);
    }

    private function ensureWorkerAccess(Worker $worker): void
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['admin', 'Recursos Humanos'])) {
            return;
        }

        abort_unless($worker->isAssignedToResponsibleWork($user), 403);
    }
}