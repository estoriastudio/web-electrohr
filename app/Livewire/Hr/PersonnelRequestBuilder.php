<?php

namespace App\Livewire\Hr;

use App\Models\PersonnelRequest;
use App\Models\PositionCategory;
use App\Models\Worker;
use App\Models\WorkerGroup;
use App\Services\Hr\AssignmentService;
use App\Services\Hr\HrAudit;
use App\Services\Hr\HrFieldScope;
use App\Services\Hr\HrPermissions;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Asignación del campo (HR-006): solicitud de personal por Obra y alta en obra. */
class PersonnelRequestBuilder extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url(as: 'obra')]
    public string $project_work_id = '';

    #[Url(as: 'cuadrilla')]
    public string $group = '';

    #[Url(as: 'cargo')]
    public string $position = '';

    #[Url(as: 'q')]
    public string $search = '';

    /** @var array<int, int|string> */
    public array $selected = [];

    public string $start_date = '';

    public string $reg_first_name = '';

    public string $reg_last_name = '';

    public string $reg_nss = '';

    public string $reg_curp = '';

    public function mount(): void
    {
        $this->authorizeField();

        if ($this->project_work_id === '' || ! $this->scopedWorks()->contains('id', (int) $this->project_work_id)) {
            $this->project_work_id = (string) ($this->scopedWorks()->first()?->id ?? '');
        }
    }

    public function updatingGroup(): void
    {
        $this->resetPage();
    }

    public function updatingPosition(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProjectWorkId(): void
    {
        $this->selected = [];
        $this->resetPage();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function send(AssignmentService $assignments, NotificationService $notification): void
    {
        $this->authorizeField();
        $work = $this->currentWork();

        $this->validate([
            'project_work_id' => 'required|exists:project_works,id',
            'selected' => 'required|array|min:1',
            'selected.*' => 'exists:workers,id',
            'start_date' => 'nullable|date',
        ], [
            'selected.required' => 'Elige al menos una persona.',
            'selected.min' => 'Elige al menos una persona.',
        ]);

        $workers = Worker::query()->whereIn('id', $this->selected)->where('status', '!=', 'terminated')->get();
        $alreadyThere = $workers->filter(fn (Worker $worker) => (int) $assignments->currentAssignment($worker)?->project_work_id === (int) $work->id);
        $workers = $workers->diff($alreadyThere);

        if ($workers->isEmpty()) {
            $this->dispatch('flash', type: 'info', message: 'Las personas elegidas ya están en esta Obra.');

            return;
        }

        if ($this->start_date !== '') {
            $tooEarly = $workers->filter(fn (Worker $worker) => CarbonImmutable::parse($this->start_date)->lessThan($assignments->minimumDate($worker, $work->id)));

            if ($tooEarly->isNotEmpty()) {
                $this->addError('start_date', 'La fecha es anterior a la mínima de: '.$tooEarly->map(fn ($worker) => "{$worker->first_name} {$worker->last_name} ({$assignments->minimumDate($worker, $work->id)->format('d/m/Y')})")->implode(', ').'.');

                return;
            }
        }

        $request = DB::transaction(function () use ($work, $workers) {
            $request = PersonnelRequest::create([
                'project_work_id' => $work->id,
                'requested_by' => Auth::id(),
                'start_date' => $this->start_date ?: null,
                'status' => 'draft',
            ]);

            foreach ($workers as $worker) {
                $request->lines()->create(['worker_id' => $worker->id]);
            }

            abort_unless($request->canTransitionTo('sent'), 422);
            $request->update(['status' => 'sent', 'sent_at' => now()]);

            return $request;
        });

        HrAudit::log('Solicitud de personal enviada', 'Asignación', $request->id, null, null, "{$workers->count()} persona(s) para {$work->name}");

        $notification->send([
            'type' => 'PersonnelRequest',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $request->id,
            'data' => "envió una solicitud de {$workers->count()} persona(s) para {$work->name}.",
        ]);

        $this->selected = [];
        $this->start_date = '';
        $this->resetErrorBag();
        $this->dispatch('flash', type: 'success', message: 'Solicitud enviada a RH.');
    }

    public function register(AssignmentService $assignments, NotificationService $notification): void
    {
        $this->authorizeField();
        $work = $this->currentWork();

        $this->resetErrorBag();
        $data = $this->validate([
            'reg_first_name' => 'required|string|max:255',
            'reg_last_name' => 'required|string|max:255',
            'reg_nss' => 'required|digits:11',
            'reg_curp' => 'required|string|size:18',
        ], [
            'reg_first_name.required' => 'El nombre es obligatorio.',
            'reg_last_name.required' => 'Los apellidos son obligatorios.',
            'reg_nss.required' => 'Sin NSS no se da el alta.',
            'reg_nss.digits' => 'El NSS debe tener 11 dígitos.',
            'reg_curp.required' => 'Sin CURP no se da el alta.',
            'reg_curp.size' => 'La CURP debe tener 18 caracteres.',
        ]);

        try {
            $worker = $assignments->registerInField(Auth::user(), $work, [
                'first_name' => $data['reg_first_name'],
                'last_name' => $data['reg_last_name'],
                'nss' => $data['reg_nss'],
                'curp' => $data['reg_curp'],
            ]);
        } catch (DomainException $exception) {
            $this->addError(str_contains($exception->getMessage(), 'CURP') ? 'reg_curp' : 'reg_nss', $exception->getMessage());

            return;
        }

        $notification->send([
            'type' => 'Worker',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $worker->id,
            'data' => "dio de alta en obra a {$worker->first_name} {$worker->last_name} en {$work->name}.",
        ]);

        $this->reset(['reg_first_name', 'reg_last_name', 'reg_nss', 'reg_curp']);
        $this->dispatch('modal', id: 'registerWorkerModal', action: 'hide');
        $this->dispatch('flash', type: 'success', message: "{$worker->first_name} {$worker->last_name} quedó pre-registrado con asignación provisional.");
    }

    public function render()
    {
        $this->authorizeField();

        $work = $this->scopedWorks()->firstWhere('id', (int) $this->project_work_id);
        $assignments = app(AssignmentService::class);

        $workers = Worker::query()
            ->with(['positionCategory', 'workerGroup', 'currentAssignment.projectWork.project'])
            ->where('status', '!=', 'terminated')
            ->when($this->group !== '', fn ($query) => $query->where('worker_group_id', $this->group))
            ->when($this->position !== '', fn ($query) => $query->where('position_category_id', $this->position))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('first_name', 'like', '%'.trim($this->search).'%')
                ->orWhere('last_name', 'like', '%'.trim($this->search).'%')
                ->orWhere('nss', 'like', '%'.trim($this->search).'%')))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25);

        $requests = PersonnelRequest::query()
            ->with(['projectWork', 'lines.worker'])
            ->when(! HrFieldScope::isUnrestricted(), fn ($query) => $query->where('requested_by', Auth::id()))
            ->latest()
            ->limit(15)
            ->get();

        return view('livewire.hr.personnel-request-builder', [
            'works' => $this->scopedWorks(),
            'work' => $work,
            'workers' => $workers,
            'groups' => WorkerGroup::orderBy('name')->get(),
            'positions' => PositionCategory::where('active', true)->orderBy('name')->get(),
            'requests' => $requests,
            'minimumDate' => $assignments->minimumDate(0)->toDateString(),
        ]);
    }

    private function scopedWorks()
    {
        return HrFieldScope::works()->with('project')->orderBy('name')->get();
    }

    private function currentWork()
    {
        $work = $this->scopedWorks()->firstWhere('id', (int) $this->project_work_id);
        abort_unless($work, 403, 'La Obra no está dentro de tu alcance.');

        return $work;
    }

    private function authorizeField(): void
    {
        abort_unless(
            HrFieldScope::isUnrestricted() || Auth::user()?->hasAnyRole([HrPermissions::ROLE_SUPERVISOR, HrPermissions::ROLE_MANAGER]),
            403
        );
    }
}
