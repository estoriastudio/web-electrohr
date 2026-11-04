<?php

namespace App\Http\Controllers;

use App\Exports\WorkerExport;
use App\Http\Controllers\Concerns\RequiresHrOffice;
use App\Imports\WorkerPayrollImport;
use App\Imports\WorkerTerminationImport;
use App\Models\PositionCategory;
use App\Models\Worker;
use App\Models\WorkerGroup;
use App\Services\Hr\HrAudit;
use App\Services\Hr\HrPermissions;
use App\Services\Hr\WorkerProfileService;
use App\Services\NotificationService;
use App\Services\WorkerTerminationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class WorkerController extends Controller
{
    use RequiresHrOffice;

    public function __construct(
        private NotificationService $notification,
        private WorkerProfileService $profileService,
    ) {}

    public function index(Request $request): View
    {
        $search = trim($request->input('search', ''));
        $section = $request->input('section', $request->input('status', 'active'));
        $groupId = $request->input('group', '');
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
            'without_group' => Worker::whereNull('worker_group_id')->count(),
        ];

        $workers = Worker::query()
            ->with(['positionCategory', 'workerGroup'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($workerQuery) use ($search) {
                    $workerQuery->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('nss', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('nickname', 'like', "%{$search}%")
                        ->orWhereHas('positionCategory', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('workerGroup', fn ($groupQuery) => $groupQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->where('status', $section)
            ->when($groupId === 'none', fn ($query) => $query->whereNull('worker_group_id'))
            ->when($groupId !== '' && $groupId !== 'none', fn ($query) => $query->where('worker_group_id', $groupId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        $workerGroups = WorkerGroup::orderBy('name')->get();
        $positionCategories = PositionCategory::query()->where('active', true)->orderBy('name')->get();
        $canSeeAmounts = HrPermissions::can('seeAmounts');

        return view('human_resources.workers.index', compact(
            'workers',
            'workerGroups',
            'search',
            'section',
            'groupId',
            'workerStats',
            'positionCategories',
            'canSeeAmounts',
        ));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('human_resources.workers.index');
    }

    public function export()
    {
        return Excel::download(new WorkerExport(HrPermissions::can('seeAmounts')), 'trabajadores.xlsx');
    }

    public function importPayroll(Request $request): RedirectResponse
    {
        abort_unless(HrPermissions::can('seeAmounts'), 403);

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
        $this->syncProfilePhoto($request, $worker);

        $this->notify($worker, 'create', "creó al trabajador {$worker->first_name} {$worker->last_name}.");

        return redirect()->route('human_resources.workers.index')
            ->with('success', 'Trabajador creado correctamente.');
    }

    public function show(Worker $worker): View
    {
        $profile = $this->profileService->profile($worker, Auth::user());
        $receipts = $profile['receipts'];
        $canManageWorker = true;

        return view('human_resources.workers.show', compact('worker', 'profile', 'receipts', 'canManageWorker'));
    }

    public function downloadPayrollReceipt(Worker $worker, int $payrollLine): RedirectResponse
    {
        abort_unless(HrPermissions::can('seeAmounts'), 403);

        // Los recibos se generan con las filas cerradas de la nómina nueva (HR-013).
        return redirect()->route('human_resources.workers.show', $worker)
            ->with('info', 'El recibo no está disponible todavía.');
    }

    public function edit(Worker $worker): View
    {
        $positionCategories = PositionCategory::query()
            ->where(fn ($query) => $query->where('active', true)->orWhere('id', $worker->position_category_id))
            ->orderBy('name')
            ->get();
        $workerGroups = WorkerGroup::query()
            ->where(fn ($query) => $query->where('status', 'active')->orWhere('id', $worker->worker_group_id))
            ->orderBy('name')
            ->get();
        $canSeeAmounts = HrPermissions::can('seeAmounts');

        return view('human_resources.workers.edit', compact('worker', 'positionCategories', 'workerGroups', 'canSeeAmounts'));
    }

    public function update(Request $request, Worker $worker): RedirectResponse
    {
        $before = $worker->only(['weekly_salary', 'payment_type']);
        $worker->update($this->validatedData($request, $worker));
        $this->syncProfilePhoto($request, $worker);

        if (HrPermissions::can('seeAmounts') && $before != $worker->only(['weekly_salary', 'payment_type'])) {
            HrAudit::log(
                'Cambio de sueldo o esquema de pago',
                'Trabajador',
                $worker->id,
                $worker,
                $before,
                $worker->only(['weekly_salary', 'payment_type']),
                sensitive: true,
            );
        }

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

    public function activate(Request $request, Worker $worker): RedirectResponse
    {
        if ($worker->status === 'terminated') {
            return redirect()->route('human_resources.workers.show', $worker)
                ->with('error', 'No se puede activar a un trabajador dado de baja.');
        }

        $data = $request->validate([
            'without_complete_file' => 'nullable|boolean',
            'reason' => 'required_if:without_complete_file,1|nullable|string|max:500',
        ]);

        $result = $this->profileService->activate(
            $worker,
            $request->boolean('without_complete_file'),
            $data['reason'] ?? null,
        );

        if (! $result['ok']) {
            return redirect()->route('human_resources.workers.show', $worker)
                ->with('error', $result['message']);
        }

        $action = $result['complete'] ? 'dio de alta' : 'dio de alta sin expediente completo';
        $this->notify($worker, 'update', "{$action} al trabajador {$worker->first_name} {$worker->last_name}.");

        return redirect()->route('human_resources.workers.show', $worker)
            ->with('success', $result['message']);
    }

    public function reactivate(Worker $worker): RedirectResponse
    {
        if ($worker->status !== 'terminated') {
            return redirect()->route('human_resources.workers.show', $worker)
                ->with('error', 'Solo se puede reactivar a un trabajador dado de baja.');
        }

        $worker->update(['status' => 'active']);

        $this->notify($worker, 'update', "reactivó al trabajador {$worker->first_name} {$worker->last_name}.");

        return redirect()->route('human_resources.workers.show', $worker)
            ->with('success', 'Trabajador reactivado y dado de alta correctamente.');
    }

    public function profilePhoto(Worker $worker): mixed
    {
        $path = $worker->profile_photo_path;

        abort_unless($path && Storage::disk('s3')->exists($path), 404);

        return Storage::disk('s3')->response($path);
    }

    private function validatedData(Request $request, ?Worker $worker = null): array
    {
        $canSeeAmounts = HrPermissions::can('seeAmounts');

        $rules = [
            'employee_code' => [
                'nullable',
                'string',
                'max:255',
            ],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'rfc' => 'nullable|string|max:20',
            'curp' => 'nullable|string|max:18',
            'birth_date' => 'nullable|date',
            'hire_date' => 'required|date',
            'position_category_id' => 'nullable|exists:position_categories,id',
            'worker_group_id' => 'nullable|exists:worker_groups,id',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:10240',
            'is_dc5' => 'nullable|boolean',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'nss' => [
                'required',
                'string',
                'max:50',
                Rule::unique('workers', 'nss')->ignore($worker?->id),
            ],
            'notes' => 'nullable|string|max:1000',
        ];

        if ($canSeeAmounts) {
            $rules['weekly_salary'] = 'required|numeric|min:0';
            $rules['payment_type'] = 'required|in:salaried,piecework';
        }

        $data = $request->validate($rules);

        unset($data['profile_photo']);

        // Quien no ve importes no los captura: un alta nueva arranca en 0 y la edición no los toca.
        if (! $canSeeAmounts && ! $worker) {
            $data += ['weekly_salary' => 0, 'payment_type' => 'salaried'];
        }

        return $data + [
            'status' => $worker?->status ?? 'pre_registered',
            'is_dc5' => $request->boolean('is_dc5'),
        ];
    }

    private function syncProfilePhoto(Request $request, Worker $worker): void
    {
        if (! $request->hasFile('profile_photo')) {
            return;
        }

        $path = $request->file('profile_photo')->store("worker-profile-photos/{$worker->id}", 's3');

        if (! $path) {
            throw new \RuntimeException('No se pudo almacenar la fotografía de perfil.');
        }

        $previousPath = $worker->profile_photo_path;
        $worker->update(['profile_photo_path' => $path]);

        if ($previousPath) {
            Storage::disk('s3')->delete($previousPath);
        }
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
