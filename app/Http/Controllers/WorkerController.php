<?php

namespace App\Http\Controllers;

use App\Exports\WorkerExport;
use App\Imports\WorkerPayrollImport;
use App\Imports\WorkerTerminationImport;
use App\Models\Worker;
use App\Models\ProjectWork;
use App\Services\NotificationService;
use App\Services\WorkerTerminationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class WorkerController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index(Request $request): View
    {
        $search = trim($request->input('search', ''));
        $section = $request->input('section', $request->input('status', 'active'));
        $projectWorkId = $request->input('project_work_id', '');
        $sections = ['active', 'pre_registered', 'terminated'];

        if (! in_array($section, $sections, true)) {
            $section = 'active';
        }

        $statusCounts = Worker::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $workerStats = [
            'total' => $statusCounts->sum(),
            'active' => (int) ($statusCounts['active'] ?? 0),
            'pre_registered' => (int) ($statusCounts['pre_registered'] ?? 0),
            'terminated' => (int) ($statusCounts['terminated'] ?? 0),
            'without_project' => Worker::whereNull('project_work_id')->count(),
        ];

        $workers = Worker::query()
            ->with([
                'projectWork',
                'groups' => fn ($query) => $query->wherePivotNull('left_at')->with('projectWork'),
            ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($workerQuery) use ($search) {
                    $workerQuery->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('nickname', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%");

                    $workerQuery->orWhereHas('groups', function ($groupQuery) use ($search) {
                        $groupQuery->whereNull('worker_group_members.left_at')
                            ->where('worker_groups.name', 'like', "%{$search}%");
                    });
                });
            })
            ->where('status', $section)
            ->when($projectWorkId, fn ($query) => $query->where('project_work_id', $projectWorkId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        $projectWorks = ProjectWork::orderBy('name')->get();

        return view('human_resources.workers.index', compact(
            'workers',
            'projectWorks',
            'search',
            'section',
            'projectWorkId',
            'workerStats',
        ));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('human_resources.workers.index');
    }

    public function export()
    {
        return Excel::download(new WorkerExport, 'trabajadores.xlsx');
    }

    public function importPayroll(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);
        DB::connection()->disableQueryLog();

        $import = new WorkerPayrollImport;
        Excel::import($import, $request->file('file'));
        $summary = $import->summary();

        $this->notification->send([
            'type' => 'Worker',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => 'import-payroll',
            'data' => "importó Nómina: {$summary['created']} creados, {$summary['updated']} actualizados y {$summary['skipped']} omitidos.",
        ]);

        return redirect()->route('human_resources.workers.index')
            ->with('success', "Nómina importada: {$summary['created']} creados, {$summary['updated']} actualizados y {$summary['skipped']} omitidos.");
    }

    public function importTerminations(Request $request, WorkerTerminationService $terminationService): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);
        DB::connection()->disableQueryLog();

        $import = new WorkerTerminationImport($terminationService);
        Excel::import($import, $request->file('file'));
        $summary = $import->summary();

        $this->notification->send([
            'type' => 'WorkerTermination',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => 'import-terminations',
            'data' => "importó Bajas: {$summary['created']} registradas y {$summary['skipped']} omitidas.",
        ]);

        return redirect()->route('human_resources.worker-terminations.index')
            ->with('success', "Bajas importadas: {$summary['created']} registradas y {$summary['skipped']} omitidas.");
    }

    public function store(Request $request): RedirectResponse
    {
        $worker = Worker::create($this->validatedData($request));

        $this->notify($worker, 'create', "creó al trabajador {$worker->first_name} {$worker->last_name}.");

        return redirect()->route('human_resources.workers.index')
            ->with('success', 'Trabajador creado correctamente.');
    }

    public function show(Worker $worker): View
    {
        $worker->load([
            'projectWork',
            'file',
            'groups.projectWork',
            'terminations.projectWork',
            'vacations' => fn ($query) => $query->orderByDesc('start_date'),
            'attendances.workerGroup.projectWork',
        ]);

        return view('human_resources.workers.show', compact('worker'));
    }

    public function edit(Worker $worker): View
    {
        $projectWorks = ProjectWork::orderBy('name')->get();

        return view('human_resources.workers.edit', compact('worker', 'projectWorks'));
    }

    public function update(Request $request, Worker $worker): RedirectResponse
    {
        $worker->update($this->validatedData($request, $worker));

        $this->notify($worker, 'update', "actualizó la información del trabajador {$worker->first_name} {$worker->last_name}.");

        return redirect()->route('human_resources.workers.show', $worker)
            ->with('success', 'Trabajador actualizado correctamente.');
    }

    public function destroy(Worker $worker): RedirectResponse
    {
        $name = "{$worker->first_name} {$worker->last_name}";
        $worker->delete();

        $this->notify($worker, 'destroy', "eliminó al trabajador {$name}.");

        return redirect()->route('human_resources.workers.index')
            ->with('success', 'Trabajador eliminado correctamente.');
    }

    public function preRegister(Worker $worker): RedirectResponse
    {
        $worker->update(['status' => 'pre_registered']);

        $this->notify($worker, 'update', "cambió a pre-registro al trabajador {$worker->first_name} {$worker->last_name}.");

        return redirect()->route('human_resources.workers.show', $worker)
            ->with('success', 'Trabajador marcado como pre-registrado.');
    }

    public function activate(Worker $worker): RedirectResponse
    {
        if ($worker->status === 'terminated') {
            return redirect()->route('human_resources.workers.show', $worker)
                ->with('error', 'No se puede activar a un trabajador dado de baja.');
        }

        $worker->update(['status' => 'active']);

        $this->notify($worker, 'update', "dio de alta al trabajador {$worker->first_name} {$worker->last_name}.");

        return redirect()->route('human_resources.workers.show', $worker)
            ->with('success', 'Trabajador dado de alta correctamente.');
    }

    private function validatedData(Request $request, ?Worker $worker = null): array
    {
        return $request->validate([
            'employee_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('workers', 'employee_code')->ignore($worker?->id),
            ],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'rfc' => 'nullable|string|max:20',
            'curp' => 'nullable|string|max:18',
            'birth_date' => 'nullable|date',
            'hire_date' => 'required|date',
            'job_title' => 'nullable|string|max:255',
            'project_work_id' => 'nullable|exists:project_works,id',
            'weekly_salary' => 'required|numeric|min:0',
            'ine_expiration_date' => 'nullable|date',
            'medical_certificate_expiration_date' => 'nullable|date',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:50',
            'nss' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]) + ['status' => $worker?->status ?? 'pre_registered'];
    }

    private function notify(object $model, string $action, string $data): void
    {
        $this->notification->send([
            'type' => class_basename($model),
            'action_by' => Auth::id(),
            'model_action' => $action,
            'model_id' => $model->id,
            'data' => $data,
        ]);
    }
}
