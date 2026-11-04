<?php

namespace App\Livewire\Hr;

use App\Models\CaptureUnlock;
use App\Models\OvertimeEntry;
use App\Models\PositionCategory;
use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\WeeklyIncentiveLetter;
use App\Models\WorkAssignment;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Models\WorkerGroup;
use App\Models\WorkSupport;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\HrAudit;
use App\Services\Hr\HrClock;
use App\Services\Hr\HrFieldScope;
use App\Services\Hr\HrPermissions;
use App\Services\Hr\OvertimeService;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Captura diaria de incidencias (HR-007) con la columna de horas extra (HR-008).
 * mode = field: Supervisor/Gerente en su Obra; mode = general: RH sobre cualquier persona.
 */
class AttendanceTable extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Locked]
    public string $mode = 'field';

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

    #[Url(as: 'fecha')]
    public string $date = '';

    /** @var array<int, int|string> */
    public array $selected = [];

    public bool $selectAll = false;

    public string $bulkIncident = '';

    public string $reason = '';

    /** Acción que espera el motivo (corrección directa de RH): type + payload. */
    #[Locked]
    public array $pending = [];

    public ?int $supportWorkerId = null;

    public string $supportTo = '';

    public string $supportFraction = 'half';

    public ?int $lateWorkerId = null;

    public string $lateHours = '';

    public string $lateReason = '';

    public function mount(string $mode = 'field', bool $yesterday = false): void
    {
        $this->mode = $mode;
        $this->authorizeMode();

        $today = HrClock::today();
        $valid = $this->date !== '' && strtotime($this->date) !== false && $this->date <= $today->toDateString();
        $this->date = $valid ? substr($this->date, 0, 10) : ($yesterday ? HrClock::yesterday()->toDateString() : $today->toDateString());

        if ($this->mode === 'field' && ($this->project_work_id === '' || ! $this->works()->contains('id', (int) $this->project_work_id))) {
            $this->project_work_id = (string) ($this->works()->first()?->id ?? '');
        }
    }

    public function updatingProjectId(): void
    {
        $this->project_work_id = $this->mode === 'field' ? $this->project_work_id : '';
        $this->resetSelection();
    }

    public function updatingProjectWorkId(): void
    {
        $this->resetSelection();
    }

    public function updatingGroup(): void
    {
        $this->resetSelection();
    }

    public function updatingPosition(): void
    {
        $this->resetSelection();
    }

    public function updatingSearch(): void
    {
        $this->resetSelection();
    }

    public function updatingDate(): void
    {
        $this->resetSelection();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value
            ? $this->baseQuery()->pluck('work_assignments.worker_id')->map(fn ($id) => (int) $id)->all()
            : [];
    }

    /* ----------------------------- Incidencias ----------------------------- */

    public function setIncident(int $workerId, string $incident): void
    {
        $this->guarded('incident', ['worker_id' => $workerId, 'incident' => $incident]);
    }

    public function applyBulk(): void
    {
        $this->authorizeMode();

        if (count($this->selected) === 0) {
            $this->dispatch('flash', type: 'error', message: 'Selecciona al menos una persona.');

            return;
        }

        if (! array_key_exists($this->bulkIncident, WorkerAttendance::INCIDENTS) || $this->bulkIncident === 'pending') {
            $this->dispatch('flash', type: 'error', message: 'Elige la incidencia que se aplicará.');

            return;
        }

        $this->guarded('bulk', ['worker_ids' => array_map('intval', $this->selected), 'incident' => $this->bulkIncident]);
    }

    public function confirmReason(): void
    {
        $this->authorizeMode();
        abort_unless($this->mode === 'general', 403);
        $this->validate(['reason' => 'required|string|max:500'], ['reason.required' => 'El motivo es obligatorio.']);

        $pending = $this->pending;
        $reason = $this->reason;
        $this->pending = [];
        $this->reason = '';
        $this->dispatch('modal', id: 'attendanceReasonModal', action: 'hide');

        if ($pending !== []) {
            $this->execute($pending['type'], $pending['payload'], $reason);
        }
    }

    /* ------------------------------- Día y HE ------------------------------ */

    public function releaseDay(AttendanceService $attendance, NotificationService $notification): void
    {
        $this->authorizeMode();
        $work = $this->currentWork();

        if (! $attendance->fieldCanEdit($work->id, $this->date)) {
            $this->dispatch('flash', type: 'error', message: 'Ese día ya no se puede liberar desde el campo.');

            return;
        }

        try {
            $attendance->closeDay($work, $this->date);
        } catch (DomainException $exception) {
            $this->dispatch('flash', type: 'error', message: $exception->getMessage());

            return;
        }

        HrAudit::log('Liberación de incidencias', 'Asistencia', $work->id, null, null, "{$work->name} · ".CarbonImmutable::parse($this->date)->format('d/m/Y'));
        $this->notifyAttendance($notification, 'update', $work->id, "liberó las incidencias de {$work->name} del ".CarbonImmutable::parse($this->date)->format('d/m/Y').'.');
        $this->dispatch('flash', type: 'success', message: 'Incidencias liberadas.');
    }

    public function setOvertime(int $workerId, $hours): void
    {
        $hours = (int) $hours;

        if ($this->mode === 'general') {
            $this->guarded('overtime', ['worker_id' => $workerId, 'hours' => $hours]);

            return;
        }

        $this->execute('overtime', ['worker_id' => $workerId, 'hours' => $hours], null);
    }

    public function releaseOvertime(OvertimeService $overtime, NotificationService $notification): void
    {
        $this->authorizeMode();
        $work = $this->currentWork();

        if (! $overtime->windowOpen($work->id, $this->date)) {
            $this->dispatch('flash', type: 'error', message: 'La captura de horas extra de ese día ya cerró.');

            return;
        }

        try {
            $overtime->release($work, $this->date);
        } catch (DomainException $exception) {
            $this->dispatch('flash', type: 'error', message: $exception->getMessage());

            return;
        }

        HrAudit::log('Liberación de horas extra', 'Horas extra', $work->id, null, null, "{$work->name} · ".CarbonImmutable::parse($this->date)->format('d/m/Y'));
        $this->notification($notification)->send([
            'type' => 'OvertimeEntry',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $work->id,
            'data' => "liberó las horas extra de {$work->name} del ".CarbonImmutable::parse($this->date)->format('d/m/Y').'.',
        ]);
        $this->dispatch('flash', type: 'success', message: 'Horas extra liberadas.');
    }

    public function openLate(int $workerId): void
    {
        $this->authorizeMode();
        $this->resetErrorBag();
        $this->lateWorkerId = $workerId;
        $this->lateHours = '';
        $this->lateReason = '';
        $this->dispatch('modal', id: 'lateOvertimeModal', action: 'show');
    }

    public function requestLate(OvertimeService $overtime, AttendanceService $attendance, NotificationService $notification): void
    {
        $this->authorizeMode();
        $this->validate([
            'lateHours' => 'required|integer|min:'.OvertimeService::MIN_HOURS.'|max:'.OvertimeService::MAX_HOURS,
            'lateReason' => 'required|string|max:500',
        ], [
            'lateHours.required' => 'Indica las horas.',
            'lateHours.integer' => 'Las horas son enteras.',
            'lateHours.min' => 'Las horas van de 1 a 16.',
            'lateHours.max' => 'Las horas van de 1 a 16.',
            'lateReason.required' => 'El motivo es obligatorio.',
        ]);

        $worker = Worker::findOrFail($this->lateWorkerId);
        $work = $this->workForWorker($worker->id);

        try {
            $request = $overtime->requestLate($worker, $work, $this->date, (int) $this->lateHours, $this->lateReason, Auth::user());
        } catch (DomainException $exception) {
            $this->addError('lateReason', $exception->getMessage());

            return;
        }

        HrAudit::log('HE fuera de plazo solicitada', 'Horas extra', $request->id, $worker, null, "{$request->hours} h", $request->reason);
        $this->notification($notification)->send([
            'type' => 'LateOvertimeRequest',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $request->id,
            'data' => "solicitó {$request->hours} h extra fuera de plazo para {$worker->first_name} {$worker->last_name} ({$work->name}).",
        ]);

        $this->dispatch('modal', id: 'lateOvertimeModal', action: 'hide');
        $this->dispatch('flash', type: 'success', message: 'Solicitud enviada a RH.');
    }

    /* ------------------------- Letra de incentivo y apoyo ------------------------- */

    public function setLetter(int $workerId, string $category, AttendanceService $attendance): void
    {
        $this->authorizeMode();

        if (! HrPermissions::can('seeIncentives')) {
            abort(403);
        }

        $worker = Worker::findOrFail($workerId);
        $work = $this->workForWorker($workerId);

        if ($this->mode === 'field' && ! $attendance->fieldCanEdit($work->id, $this->date)) {
            $this->dispatch('flash', type: 'error', message: 'Ese día es solo consulta.');

            return;
        }

        if (! $attendance->isIncentiveDay($this->date)) {
            $this->dispatch('flash', type: 'error', message: 'La letra de incentivo se define en jueves.');

            return;
        }

        try {
            $attendance->setLetter($worker, $work, $this->date, $category);
        } catch (DomainException $exception) {
            $this->dispatch('flash', type: 'error', message: $exception->getMessage());

            return;
        }

        HrAudit::log('Letra de incentivo', 'Asistencia', $worker->id, $worker, null, $category);
    }

    public function openSupport(int $workerId): void
    {
        $this->authorizeMode();
        $this->resetErrorBag();
        $this->supportWorkerId = $workerId;
        $existing = WorkSupport::query()->where('worker_id', $workerId)->whereDate('date', $this->date)->first();
        $this->supportTo = (string) ($existing?->to_project_work_id ?? '');
        $this->supportFraction = $existing?->fraction ?? 'half';
        $this->dispatch('modal', id: 'supportModal', action: 'show');
    }

    public function saveSupport(): void
    {
        $this->authorizeMode();
        $this->validate([
            'supportTo' => 'required|exists:project_works,id',
            'supportFraction' => 'required|in:half,full',
        ], ['supportTo.required' => 'Elige la Obra que absorbe el costo.']);

        $this->dispatch('modal', id: 'supportModal', action: 'hide');
        $this->guarded('support', ['worker_id' => $this->supportWorkerId, 'to' => (int) $this->supportTo, 'fraction' => $this->supportFraction]);
    }

    public function removeSupport(int $workerId): void
    {
        $this->guarded('support_clear', ['worker_id' => $workerId]);
    }

    /* --------------------------------- Render --------------------------------- */

    public function render()
    {
        $this->authorizeMode();

        if ($this->mode === 'field') {
            abort_unless($this->works()->contains('id', (int) $this->project_work_id) || $this->works()->isEmpty(), 403, 'La Obra no está dentro de tu alcance.');
        }

        $attendance = app(AttendanceService::class);
        $overtime = app(OvertimeService::class);
        $date = $this->date;
        $today = HrClock::today();
        $assignments = $this->baseQuery()
            ->with(['worker.workerGroup', 'worker.positionCategory', 'projectWork.project'])
            ->paginate(25);

        $workerIds = $assignments->pluck('worker_id');
        $records = WorkerAttendance::query()->whereIn('worker_id', $workerIds)->whereDate('date', $date)->get()->keyBy('worker_id');
        $supports = WorkSupport::query()->with('toProjectWork')->whereIn('worker_id', $workerIds)->whereDate('date', $date)->get()->keyBy('worker_id');
        $overtimeEntries = OvertimeEntry::query()->whereIn('worker_id', $workerIds)->whereDate('date', $date)->get()->keyBy('worker_id');
        $letters = WeeklyIncentiveLetter::query()->whereIn('worker_id', $workerIds)
            ->whereDate('payroll_period_start', HrClock::periodOf($date)->toDateString())->get()->keyBy('worker_id');

        $workState = [];
        foreach ($assignments->pluck('project_work_id')->unique() as $workId) {
            $workState[$workId] = [
                'closed' => $attendance->isDayClosed($workId, $date),
                'unlock' => $attendance->activeUnlock($workId, $date),
                'editable' => $this->mode === 'general' || $attendance->fieldCanEdit($workId, $date),
                'otOpen' => $overtime->windowOpen($workId, $date),
                'otClosed' => $overtime->isClosed($workId, $date),
                'needsReason' => $this->mode === 'general' && $attendance->hrRequiresReason($workId, $date),
            ];
        }

        $rows = $assignments->map(function (WorkAssignment $assignment) use ($records, $supports, $overtimeEntries, $letters, $attendance) {
            $state = $attendance->dayState($assignment->worker_id, $this->date);

            return [
                'assignment' => $assignment,
                'worker' => $assignment->worker,
                'work' => $assignment->projectWork,
                'record' => $records->get($assignment->worker_id),
                'support' => $supports->get($assignment->worker_id),
                'overtime' => $overtimeEntries->get($assignment->worker_id),
                'letter' => $letters->get($assignment->worker_id),
                'state' => $state,
                'allowed' => $attendance->allowedIncidents($state),
            ];
        });

        $currentWork = $this->mode === 'field' ? $this->works()->firstWhere('id', (int) $this->project_work_id) : null;
        $unlockDates = $currentWork
            ? CaptureUnlock::query()->usable()->where('project_work_id', $currentWork->id)->pluck('date')->map(fn ($day) => $day->toDateString())->all()
            : [];

        $currentState = $currentWork ? [
            'closed' => $attendance->isDayClosed($currentWork->id, $date),
            'unlock' => $attendance->activeUnlock($currentWork->id, $date),
            'editable' => $attendance->fieldCanEdit($currentWork->id, $date),
            'otOpen' => $overtime->windowOpen($currentWork->id, $date),
        ] : null;

        return view('livewire.hr.attendance-table', [
            'currentState' => $currentState,
            'assignments' => $assignments,
            'rows' => $rows,
            'workState' => $workState,
            'works' => $this->works(),
            'currentWork' => $currentWork,
            'projects' => Project::orderBy('name')->get(),
            'groups' => WorkerGroup::orderBy('name')->get(),
            'positions' => PositionCategory::where('active', true)->orderBy('name')->get(),
            'allWorks' => ProjectWork::with('project')->orderBy('name')->get(),
            'dateOptions' => collect([$today->toDateString(), HrClock::yesterday()->toDateString()])->merge($unlockDates)->unique()->values(),
            'isToday' => $date === $today->toDateString(),
            'showOvertime' => $date < $today->toDateString(),
            'isIncentiveDay' => $attendance->isIncentiveDay($date),
            'canSeeLetters' => HrPermissions::can('seeIncentives'),
            'otDeadline' => $overtime->deadline($date),
            'incidentCatalog' => WorkerAttendance::INCIDENTS,
            'maxHours' => OvertimeService::MAX_HOURS,
        ]);
    }

    /* --------------------------------- Interno --------------------------------- */

    /** Valida alcance y bloqueo; en captura general pide motivo si el día es pasado o está bloqueado. */
    private function guarded(string $type, array $payload): void
    {
        $this->authorizeMode();
        $attendance = app(AttendanceService::class);

        if ($this->date > HrClock::today()->toDateString()) {
            $this->dispatch('flash', type: 'error', message: 'No se puede capturar una fecha futura.');

            return;
        }

        $workIds = $this->workIdsFor($payload);

        if ($workIds === []) {
            $this->dispatch('flash', type: 'error', message: 'La persona no está en el padrón de esa fecha.');

            return;
        }

        if ($this->mode === 'field') {
            foreach ($workIds as $workId) {
                abort_unless(HrFieldScope::canOperateWork($workId), 403, 'La Obra no está dentro de tu alcance.');

                if (! $attendance->fieldCanEdit($workId, $this->date)) {
                    $this->dispatch('flash', type: 'error', message: 'Ese día es solo consulta o ya está liberado.');

                    return;
                }
            }

            $this->execute($type, $payload, null);

            return;
        }

        $needsReason = $type === 'overtime' || collect($workIds)->contains(fn ($workId) => $attendance->hrRequiresReason($workId, $this->date));

        if ($needsReason) {
            $this->pending = ['type' => $type, 'payload' => $payload];
            $this->reason = '';
            $this->resetErrorBag();
            $this->dispatch('modal', id: 'attendanceReasonModal', action: 'show');

            return;
        }

        $this->execute($type, $payload, null);
    }

    private function execute(string $type, array $payload, ?string $reason): void
    {
        $attendance = app(AttendanceService::class);
        $overtime = app(OvertimeService::class);
        $notification = app(NotificationService::class);
        $date = $this->date;
        $dateLabel = CarbonImmutable::parse($date)->format('d/m/Y');
        $forced = $reason !== null;

        try {
            if ($type === 'incident') {
                $worker = Worker::findOrFail($payload['worker_id']);
                $work = $this->workForWorker($worker->id);
                $before = WorkerAttendance::query()->where('worker_id', $worker->id)->whereDate('date', $date)->value('incident');
                $record = $attendance->capture($worker, $work, $date, $payload['incident'], $reason);

                HrAudit::log(
                    $forced ? 'Corrección directa de incidencia' : 'Captura de incidencia',
                    'Asistencia',
                    $record->id,
                    $worker,
                    $before ? WorkerAttendance::label($before) : null,
                    WorkerAttendance::label($payload['incident']),
                    $reason,
                );
                $this->notifyAttendance($notification, $before ? 'update' : 'create', $record->id, ($forced ? 'corrigió' : 'capturó')." la incidencia de {$worker->first_name} {$worker->last_name} ({$dateLabel}): ".WorkerAttendance::label($payload['incident']).'.');
                $this->dispatch('flash', type: 'success', message: 'Incidencia guardada.');
            }

            if ($type === 'bulk') {
                $byWork = collect($payload['worker_ids'])->groupBy(fn ($workerId) => $this->workForWorker((int) $workerId)->id);
                $applied = 0;
                $total = 0;
                $skipped = [];

                foreach ($byWork as $workId => $ids) {
                    $result = $attendance->captureBlock(ProjectWork::findOrFail($workId), $date, $ids->all(), $payload['incident'], $reason);
                    $applied += $result['applied'];
                    $total += $result['total'];
                    $skipped = array_merge($skipped, $result['skipped']);
                }

                HrAudit::log(
                    $forced ? 'Corrección directa en bloque' : 'Captura en bloque',
                    'Asistencia',
                    null,
                    null,
                    null,
                    WorkerAttendance::label($payload['incident'])." · {$applied} de {$total} · {$dateLabel}",
                    $reason,
                );
                $this->notifyAttendance($notification, 'update', 0, "aplicó {$this->label($payload['incident'])} en bloque ({$applied} de {$total}) el {$dateLabel}.");
                $this->selected = [];
                $this->selectAll = false;
                $this->bulkIncident = '';
                $this->dispatch('flash', type: $applied > 0 ? 'success' : 'error', message: "Captura en bloque: {$applied} de {$total}.".($skipped ? ' Omitidos: '.count($skipped).'.' : ''));
            }

            if ($type === 'overtime') {
                $worker = Worker::findOrFail($payload['worker_id']);
                $work = $this->workForWorker($worker->id);
                $before = OvertimeEntry::query()->where('worker_id', $worker->id)->whereDate('date', $date)->value('hours');
                $entry = $overtime->capture($worker, $work, $date, $payload['hours'], $forced ? 'hr' : 'field', $reason);

                HrAudit::log(
                    $forced ? 'Horas extra capturadas por RH' : 'Captura de horas extra',
                    'Horas extra',
                    $entry?->id,
                    $worker,
                    $before !== null ? "{$before} h" : null,
                    $payload['hours'] > 0 ? "{$payload['hours']} h" : null,
                    $reason,
                );
                $this->notification($notification)->send([
                    'type' => 'OvertimeEntry',
                    'action_by' => Auth::id(),
                    'model_action' => $before === null ? 'create' : ($payload['hours'] > 0 ? 'update' : 'destroy'),
                    'model_id' => $entry?->id ?? 0,
                    'data' => "registró {$payload['hours']} h extra a {$worker->first_name} {$worker->last_name} ({$dateLabel}).",
                ]);
                $this->dispatch('flash', type: 'success', message: 'Horas extra guardadas.');
            }

            if ($type === 'support') {
                $worker = Worker::findOrFail($payload['worker_id']);
                $work = $this->workForWorker($worker->id);
                $support = $attendance->setSupport($worker, $work, $date, $payload['to'], $payload['fraction']);

                HrAudit::log('Apoyo entre Obras', 'Asistencia', $support->id, $worker, null, ProjectWork::find($payload['to'])?->name.' · '.WorkSupport::FRACTIONS[$payload['fraction']], $reason);
                $this->notifyAttendance($notification, 'update', $support->id, "registró un apoyo de {$worker->first_name} {$worker->last_name} a ".ProjectWork::find($payload['to'])?->name." ({$dateLabel}).");
                $this->dispatch('flash', type: 'success', message: 'Apoyo guardado.');
            }

            if ($type === 'support_clear') {
                $worker = Worker::findOrFail($payload['worker_id']);
                $attendance->clearSupport($worker, $date, $reason ?? 'Apoyo retirado');

                HrAudit::log('Apoyo entre Obras retirado', 'Asistencia', $worker->id, $worker, 'Apoyo', null, $reason);
                $this->dispatch('flash', type: 'success', message: 'Apoyo retirado.');
            }
        } catch (DomainException $exception) {
            $this->dispatch('flash', type: 'error', message: $exception->getMessage());
        }
    }

    /** Obras del padrón de la fecha a las que pertenecen las personas del payload. */
    private function workIdsFor(array $payload): array
    {
        $ids = $payload['worker_ids'] ?? [$payload['worker_id'] ?? 0];

        $query = WorkAssignment::query()->onDate($this->date)->whereIn('worker_id', $ids);

        if ($this->mode === 'field') {
            $query->where('project_work_id', (int) $this->project_work_id);
        }

        return $query->pluck('project_work_id')->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    private function workForWorker(int $workerId): ProjectWork
    {
        $query = WorkAssignment::query()->onDate($this->date)->where('worker_id', $workerId);

        if ($this->mode === 'field') {
            $query->where('project_work_id', (int) $this->project_work_id);
        }

        $assignment = $query->first();
        abort_unless($assignment, 404, 'La persona no está en el padrón de esa fecha.');

        if ($this->mode === 'field') {
            abort_unless(HrFieldScope::canOperateWork($assignment->project_work_id), 403, 'La Obra no está dentro de tu alcance.');
        }

        return ProjectWork::findOrFail($assignment->project_work_id);
    }

    private function currentWork(): ProjectWork
    {
        abort_unless($this->mode === 'field', 403);
        $work = $this->works()->firstWhere('id', (int) $this->project_work_id);
        abort_unless($work, 403, 'La Obra no está dentro de tu alcance.');

        return $work;
    }

    private function works()
    {
        return ($this->mode === 'field'
            ? HrFieldScope::works()
            : ProjectWork::query()->when($this->project_id !== '', fn ($query) => $query->where('project_id', $this->project_id))
        )->with('project')->orderBy('name')->get();
    }

    private function baseQuery()
    {
        $search = trim($this->search);

        return WorkAssignment::query()
            ->select('work_assignments.*')
            ->onDate($this->date)
            ->join('workers', 'workers.id', '=', 'work_assignments.worker_id')
            ->whereNull('workers.deleted_at')
            ->when($this->mode === 'field', fn ($query) => $query->where('work_assignments.project_work_id', (int) $this->project_work_id))
            ->when($this->mode === 'general' && $this->project_work_id !== '', fn ($query) => $query->where('work_assignments.project_work_id', $this->project_work_id))
            ->when($this->mode === 'general' && $this->project_id !== '', fn ($query) => $query->where('work_assignments.project_id', $this->project_id))
            ->when($this->group !== '', fn ($query) => $query->where('workers.worker_group_id', $this->group))
            ->when($this->position !== '', fn ($query) => $query->where('workers.position_category_id', $this->position))
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('workers.first_name', 'like', "%{$search}%")
                ->orWhere('workers.last_name', 'like', "%{$search}%")
                ->orWhere('workers.nss', 'like', "%{$search}%")))
            ->orderBy('workers.last_name')
            ->orderBy('workers.first_name');
    }

    private function resetSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    private function label(string $incident): string
    {
        return WorkerAttendance::label($incident);
    }

    private function notifyAttendance(NotificationService $notification, string $action, int $modelId, string $data): void
    {
        $notification->send([
            'type' => 'WorkerAttendance',
            'action_by' => Auth::id(),
            'model_action' => $action,
            'model_id' => $modelId,
            'data' => $data,
        ]);
    }

    private function notification(NotificationService $notification): NotificationService
    {
        return $notification;
    }

    private function authorizeMode(): void
    {
        if ($this->mode === 'general') {
            abort_unless(HrFieldScope::isUnrestricted() && HrPermissions::can('hrInbox'), 403);

            return;
        }

        $this->mode = 'field';
        abort_unless(
            HrFieldScope::isUnrestricted() || Auth::user()?->hasAnyRole([HrPermissions::ROLE_SUPERVISOR, HrPermissions::ROLE_MANAGER]),
            403
        );
    }
}
