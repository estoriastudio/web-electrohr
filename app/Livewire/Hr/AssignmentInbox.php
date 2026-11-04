<?php

namespace App\Livewire\Hr;

use App\Models\PersonnelRequest;
use App\Models\PersonnelRequestLine;
use App\Models\PositionCategory;
use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\WorkAssignment;
use App\Models\Worker;
use App\Models\WorkerGroup;
use App\Services\Hr\AssignmentService;
use App\Services\Hr\HrClock;
use App\Services\Hr\HrFieldScope;
use App\Services\Hr\HrPermissions;
use App\Services\Hr\WorkerProfileService;
use App\Services\NotificationService;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Bandeja de RH de Asignación (HR-006): Pendientes, Conflictos, Altas en obra e Historial. */
class AssignmentInbox extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url(as: 'pestana')]
    public string $tab = 'pendientes';

    #[Url(as: 'proyecto')]
    public string $project_id = '';

    #[Url(as: 'obra')]
    public string $project_work_id = '';

    #[Url(as: 'cuadrilla')]
    public string $group = '';

    #[Url(as: 'cargo')]
    public string $position = '';

    #[Url(as: 'q')]
    public string $search = '';

    public string $reason = '';

    /** Acción que espera el motivo del modal: reject_request, reject_line, void, activate. */
    public string $reasonAction = '';

    public ?int $reasonTargetId = null;

    public ?int $otherLineId = null;

    public string $otherWorkId = '';

    public ?int $reassignAssignmentId = null;

    public string $reassignWorkId = '';

    public string $reassignDate = '';

    public string $reassignReason = '';

    public ?int $mergeWorkerId = null;

    public string $mergeSearch = '';

    public function mount(): void
    {
        $this->authorizeInbox();

        if (! in_array($this->tab, ['pendientes', 'conflictos', 'altas', 'historial'], true)) {
            $this->tab = 'pendientes';
        }
    }

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    public function updatingProjectId(): void
    {
        $this->project_work_id = '';
        $this->resetPage();
    }

    public function updatingProjectWorkId(): void
    {
        $this->resetPage();
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

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function approveRequest(int $requestId, AssignmentService $assignments, NotificationService $notification): void
    {
        $this->authorizeInbox();
        $request = PersonnelRequest::with('projectWork')->where('status', 'sent')->findOrFail($requestId);

        $result = $assignments->approveRequest($request, Auth::user());

        $summary = "{$result['approved']} aprobada(s)".($result['conflicts'] ? ", {$result['conflicts']} pasaron a Conflictos" : '').'.';
        $assignments->notifyRequester($request, Auth::user(), $summary);
        $notification->send([
            'type' => 'PersonnelRequest',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $request->id,
            'data' => "dio visto bueno a la solicitud de {$request->projectWork->name}: {$summary}",
        ]);

        $this->dispatch('flash', type: $result['approved'] ? 'success' : 'info', message: "Visto bueno: {$summary}");
    }

    public function openReason(string $action, int $targetId): void
    {
        $this->authorizeInbox();
        $this->reset(['reason']);
        $this->resetErrorBag();
        $this->reasonAction = $action;
        $this->reasonTargetId = $targetId;
        $this->dispatch('modal', id: 'inboxReasonModal', action: 'show');
    }

    public function confirmReason(AssignmentService $assignments, NotificationService $notification, WorkerProfileService $profile): void
    {
        $this->authorizeInbox();
        $this->validate(['reason' => 'required|string|max:500'], ['reason.required' => 'El motivo es obligatorio.']);

        try {
            match ($this->reasonAction) {
                'reject_request' => $this->doRejectRequest($assignments, $notification),
                'reject_line' => $this->doRejectLine($assignments, $notification),
                'void' => $this->doVoid($assignments),
                'activate' => $this->doActivate($profile, $notification),
                default => throw new DomainException('La acción no es válida.'),
            };
        } catch (DomainException $exception) {
            $this->dispatch('flash', type: 'error', message: $exception->getMessage());
        }

        $this->dispatch('modal', id: 'inboxReasonModal', action: 'hide');
        $this->reset(['reason', 'reasonAction', 'reasonTargetId']);
    }

    public function resolve(int $lineId, string $action, AssignmentService $assignments, NotificationService $notification): void
    {
        $this->authorizeInbox();
        $line = PersonnelRequestLine::with(['worker', 'personnelRequest.projectWork'])->where('status', 'pending')->findOrFail($lineId);

        if ($action === 'other') {
            $this->otherLineId = $lineId;
            $this->otherWorkId = '';
            $this->dispatch('modal', id: 'inboxOtherWorkModal', action: 'show');

            return;
        }

        try {
            $message = $assignments->resolveConflict($line, $action, Auth::user());
        } catch (DomainException $exception) {
            $this->dispatch('flash', type: 'error', message: $exception->getMessage());

            return;
        }

        $this->afterConflict($line, $message, $assignments, $notification);
    }

    public function resolveOther(AssignmentService $assignments, NotificationService $notification): void
    {
        $this->authorizeInbox();
        $this->validate(['otherWorkId' => 'required|exists:project_works,id'], ['otherWorkId.required' => 'Elige la Obra.']);

        $line = PersonnelRequestLine::with(['worker', 'personnelRequest.projectWork'])->where('status', 'pending')->findOrFail($this->otherLineId);

        try {
            $message = $assignments->resolveConflict($line, 'other', Auth::user(), (int) $this->otherWorkId);
        } catch (DomainException $exception) {
            $this->dispatch('flash', type: 'error', message: $exception->getMessage());

            return;
        }

        $this->dispatch('modal', id: 'inboxOtherWorkModal', action: 'hide');
        $this->afterConflict($line, $message, $assignments, $notification);
    }

    public function openReassign(int $assignmentId): void
    {
        $this->authorizeInbox();
        $this->resetErrorBag();
        $this->reassignAssignmentId = $assignmentId;
        $this->reassignWorkId = '';
        $this->reassignDate = HrClock::today()->toDateString();
        $this->reassignReason = '';
        $this->dispatch('modal', id: 'inboxReassignModal', action: 'show');
    }

    public function reassign(AssignmentService $assignments): void
    {
        $this->authorizeInbox();
        $this->validate([
            'reassignWorkId' => 'required|exists:project_works,id',
            'reassignDate' => 'required|date',
            'reassignReason' => 'required|string|max:500',
        ], [
            'reassignWorkId.required' => 'Elige la Obra.',
            'reassignReason.required' => 'El motivo es obligatorio.',
        ]);

        $assignment = WorkAssignment::with('worker')->whereNull('voided_at')->whereNull('end_date')->findOrFail($this->reassignAssignmentId);

        try {
            $assignments->directAssign($assignment->worker, ProjectWork::findOrFail($this->reassignWorkId), $this->reassignDate, Auth::user(), $this->reassignReason);
        } catch (DomainException $exception) {
            $this->addError('reassignDate', $exception->getMessage());

            return;
        }

        $this->dispatch('modal', id: 'inboxReassignModal', action: 'hide');
        $this->dispatch('flash', type: 'success', message: 'Persona reasignada.');
    }

    public function activateWorker(int $workerId, WorkerProfileService $profile, NotificationService $notification): void
    {
        $this->authorizeInbox();
        $worker = Worker::with('file')->findOrFail($workerId);

        if ($profile->isFileComplete($worker)) {
            $this->finishActivation($worker, $profile->activate($worker, false), $notification);

            return;
        }

        $this->openReason('activate', $workerId);
    }

    public function openMerge(int $workerId): void
    {
        $this->authorizeInbox();
        $this->mergeWorkerId = $workerId;
        $this->mergeSearch = '';
        $this->dispatch('modal', id: 'inboxMergeModal', action: 'show');
    }

    public function merge(int $targetId, AssignmentService $assignments, NotificationService $notification): void
    {
        $this->authorizeInbox();
        $duplicate = Worker::findOrFail($this->mergeWorkerId);
        $target = Worker::findOrFail($targetId);

        try {
            $assignments->mergeDuplicate($duplicate, $target, Auth::user());
        } catch (DomainException $exception) {
            $this->dispatch('flash', type: 'error', message: $exception->getMessage());

            return;
        }

        $notification->send([
            'type' => 'Worker',
            'action_by' => Auth::id(),
            'model_action' => 'destroy',
            'model_id' => $duplicate->id,
            'data' => "fusionó el pre-registro duplicado de {$duplicate->first_name} {$duplicate->last_name} en {$target->first_name} {$target->last_name}.",
        ]);

        $this->mergeWorkerId = null;
        $this->dispatch('modal', id: 'inboxMergeModal', action: 'hide');
        $this->dispatch('flash', type: 'success', message: 'Duplicado fusionado.');
    }

    public function render()
    {
        $this->authorizeInbox();

        $data = match ($this->tab) {
            'conflictos' => ['conflicts' => $this->conflicts()],
            'altas' => ['registrations' => $this->registrations()],
            'historial' => ['history' => $this->history()],
            default => ['requests' => $this->pendingRequests()],
        };

        $assignments = app(AssignmentService::class);

        return view('livewire.hr.assignment-inbox', $data + [
            'projects' => Project::orderBy('name')->get(),
            'works' => ProjectWork::query()->when($this->project_id !== '', fn ($query) => $query->where('project_id', $this->project_id))->orderBy('name')->get(),
            'allWorks' => ProjectWork::with('project')->orderBy('name')->get(),
            'groups' => WorkerGroup::orderBy('name')->get(),
            'positions' => PositionCategory::orderBy('name')->get(),
            'assignmentService' => $assignments,
            'mergeCandidates' => $this->mergeWorkerId && trim($this->mergeSearch) !== ''
                ? Worker::query()->where('id', '!=', $this->mergeWorkerId)
                    ->where(fn ($query) => $query->where('first_name', 'like', '%'.trim($this->mergeSearch).'%')
                        ->orWhere('last_name', 'like', '%'.trim($this->mergeSearch).'%')
                        ->orWhere('nss', 'like', '%'.trim($this->mergeSearch).'%'))
                    ->orderBy('last_name')->limit(8)->get()
                : collect(),
            'today' => HrClock::today(),
        ]);
    }

    private function doRejectRequest(AssignmentService $assignments, NotificationService $notification): void
    {
        $request = PersonnelRequest::with('projectWork')->where('status', 'sent')->findOrFail($this->reasonTargetId);
        $count = $assignments->rejectRequest($request, Auth::user(), $this->reason);

        $assignments->notifyRequester($request, Auth::user(), "{$count} línea(s) rechazada(s): {$this->reason}");
        $notification->send([
            'type' => 'PersonnelRequest',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $request->id,
            'data' => "rechazó la solicitud de {$request->projectWork->name}: {$this->reason}",
        ]);

        $this->dispatch('flash', type: 'success', message: 'Solicitud rechazada.');
    }

    private function doRejectLine(AssignmentService $assignments, NotificationService $notification): void
    {
        $line = PersonnelRequestLine::with(['worker', 'personnelRequest.projectWork'])->where('status', 'pending')->findOrFail($this->reasonTargetId);
        $assignments->rejectLine($line, Auth::user(), $this->reason);

        $assignments->notifyRequester($line->personnelRequest, Auth::user(), "{$line->worker->first_name} {$line->worker->last_name} rechazada: {$this->reason}");
        $notification->send([
            'type' => 'PersonnelRequest',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $line->personnel_request_id,
            'data' => "rechazó a {$line->worker->first_name} {$line->worker->last_name} de la solicitud de {$line->personnelRequest->projectWork->name}.",
        ]);

        $this->dispatch('flash', type: 'success', message: 'Línea rechazada.');
    }

    private function doVoid(AssignmentService $assignments): void
    {
        $assignment = WorkAssignment::with(['worker', 'projectWork'])->findOrFail($this->reasonTargetId);
        $deleted = $assignments->void($assignment, Auth::user(), $this->reason);

        $this->dispatch('flash', type: 'success', message: "Asignación anulada; se eliminaron {$deleted} registro(s) de asistencia.");
    }

    private function doActivate(WorkerProfileService $profile, NotificationService $notification): void
    {
        $worker = Worker::with('file')->findOrFail($this->reasonTargetId);
        $this->finishActivation($worker, $profile->activate($worker, true, $this->reason), $notification);
    }

    private function finishActivation(Worker $worker, array $result, NotificationService $notification): void
    {
        if ($result['ok']) {
            $notification->send([
                'type' => 'Worker',
                'action_by' => Auth::id(),
                'model_action' => 'update',
                'model_id' => $worker->id,
                'data' => ($result['complete'] ? 'dio de alta' : 'dio de alta sin expediente completo')." al trabajador {$worker->first_name} {$worker->last_name}.",
            ]);
        }

        $this->dispatch('flash', type: $result['ok'] ? 'success' : 'error', message: $result['message']);
    }

    private function afterConflict(PersonnelRequestLine $line, string $message, AssignmentService $assignments, NotificationService $notification): void
    {
        $assignments->notifyRequester($line->personnelRequest, Auth::user(), "{$line->worker->first_name} {$line->worker->last_name}: {$message}");
        $notification->send([
            'type' => 'PersonnelRequest',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $line->personnel_request_id,
            'data' => "resolvió el conflicto de {$line->worker->first_name} {$line->worker->last_name}: {$message}",
        ]);

        $this->dispatch('flash', type: 'success', message: $message);
    }

    private function workerFilter()
    {
        $search = trim($this->search);

        return fn ($query) => $query
            ->when($this->group !== '', fn ($inner) => $inner->where('worker_group_id', $this->group))
            ->when($this->position !== '', fn ($inner) => $inner->where('position_category_id', $this->position))
            ->when($search !== '', fn ($inner) => $inner->where(fn ($match) => $match
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nss', 'like', "%{$search}%")));
    }

    private function requestScope()
    {
        return fn ($query) => $query
            ->when($this->project_work_id !== '', fn ($inner) => $inner->where('project_work_id', $this->project_work_id))
            ->when($this->project_id !== '', fn ($inner) => $inner->whereHas('projectWork', fn ($work) => $work->where('project_id', $this->project_id)));
    }

    private function pendingRequests()
    {
        $service = app(AssignmentService::class);

        $requests = PersonnelRequest::query()
            ->with(['projectWork.project', 'requestedByUser', 'lines' => fn ($query) => $query->where('status', 'pending')->with('worker.workerGroup', 'worker.positionCategory')])
            ->where('status', 'sent')
            ->whereHas('lines', fn ($lines) => $lines->where('status', 'pending')->whereHas('worker', $this->workerFilter()))
            ->where($this->requestScope())
            ->oldest('sent_at')
            ->paginate(25);

        $requests->getCollection()->each(function (PersonnelRequest $request) use ($service) {
            $request->lines->each(function (PersonnelRequestLine $line) use ($request, $service) {
                $line->setRelation('personnelRequest', $request);
                $line->conflict = $service->conflictReason($line);
            });
        });

        return $requests;
    }

    private function conflicts(): LengthAwarePaginator
    {
        $service = app(AssignmentService::class);

        $lines = PersonnelRequestLine::query()
            ->with(['worker.workerGroup', 'worker.positionCategory', 'personnelRequest.projectWork.project', 'personnelRequest.requestedByUser'])
            ->where('status', 'pending')
            ->whereHas('personnelRequest', fn ($request) => $request->where('status', 'sent')->where($this->requestScope()))
            ->whereHas('worker', $this->workerFilter())
            ->orderBy('id')
            ->get()
            ->each(fn (PersonnelRequestLine $line) => $line->conflict = $service->conflictReason($line))
            ->filter(fn (PersonnelRequestLine $line) => $line->conflict !== null)
            ->values();

        $page = $this->getPage();

        return new LengthAwarePaginator($lines->forPage($page, 25)->values(), $lines->count(), 25, $page, ['path' => request()->url()]);
    }

    private function registrations()
    {
        return Worker::query()
            ->with(['registeredByUser', 'workerGroup', 'currentAssignment.projectWork.project', 'file'])
            ->whereNotNull('registered_by_user_id')
            ->where(fn ($query) => $query->where('status', 'pre_registered')->orWhere('file_incomplete', true))
            ->where($this->workerFilter())
            ->when($this->project_work_id !== '', fn ($query) => $query->whereHas('currentAssignment', fn ($assignment) => $assignment->where('project_work_id', $this->project_work_id)))
            ->when($this->project_id !== '' && $this->project_work_id === '', fn ($query) => $query->whereHas('currentAssignment', fn ($assignment) => $assignment->where('project_id', $this->project_id)))
            ->orderBy('profile_complete_deadline')
            ->paginate(25);
    }

    private function history()
    {
        return WorkAssignment::query()
            ->with(['worker.workerGroup', 'projectWork.project', 'approvedByUser'])
            ->whereHas('worker', $this->workerFilter())
            ->when($this->project_work_id !== '', fn ($query) => $query->where('project_work_id', $this->project_work_id))
            ->when($this->project_id !== '', fn ($query) => $query->where('project_id', $this->project_id))
            ->latest('start_date')
            ->latest('id')
            ->paginate(25);
    }

    private function authorizeInbox(): void
    {
        abort_unless(HrFieldScope::isUnrestricted() && HrPermissions::can('hrInbox'), 403);
    }
}
