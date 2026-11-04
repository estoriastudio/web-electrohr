<?php

namespace App\Services\Hr;

use App\Models\AttendanceDayClosure;
use App\Models\AttendanceHistory;
use App\Models\CaptureUnlock;
use App\Models\ProjectWork;
use App\Models\WeeklyIncentiveLetter;
use App\Models\WorkAssignment;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Models\WorkSupport;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Captura diaria de incidencias (HR-007). Capturar es crear o actualizar un
 * WorkerAttendance; los demás modelos guardan lo que rodea a la captura.
 */
class AttendanceService
{
    public const STATE_NORMAL = 'normal';

    public const STATE_VACATION = 'vacation';

    public const STATE_REST = 'rest';

    public const STATE_REST_REJECTED = 'rest_rejected';

    private const ALLOWED = [
        self::STATE_NORMAL => ['attendance', 'absence', 'day_off_a', 'day_off_b', 'day_off_c'],
        self::STATE_VACATION => ['vacation', 'vacation_worked'],
        self::STATE_REST => ['rest', 'rest_worked'],
        self::STATE_REST_REJECTED => [],
    ];

    /** Padrón de una Obra en una fecha: asignaciones vigentes ese día, provisionales incluidas. */
    public function padron(int $projectWorkId, CarbonInterface|string $date): Builder
    {
        return WorkAssignment::query()->onDate($date)->where('project_work_id', $projectWorkId);
    }

    public function isInPadron(Worker|int $worker, int $projectWorkId, CarbonInterface|string $date): bool
    {
        $workerId = $worker instanceof Worker ? $worker->id : $worker;

        return $this->padron($projectWorkId, $date)->where('worker_id', $workerId)->exists();
    }

    /**
     * Estado del día de la persona. Vacaciones y descansos aprobados o rechazados
     * llegan con HR-009; mientras tanto todos los días son normales.
     */
    public function dayState(int $workerId, CarbonInterface|string $date): string
    {
        return self::STATE_NORMAL;
    }

    /** @return array<int, string> */
    public function allowedIncidents(string $state): array
    {
        return self::ALLOWED[$state] ?? [];
    }

    public function isDayClosed(int $projectWorkId, CarbonInterface|string $date): bool
    {
        return AttendanceDayClosure::query()
            ->where('project_work_id', $projectWorkId)
            ->whereDate('date', $this->dateString($date))
            ->exists();
    }

    public function activeUnlock(int $projectWorkId, CarbonInterface|string $date): ?CaptureUnlock
    {
        return CaptureUnlock::query()->usable()
            ->where('project_work_id', $projectWorkId)
            ->whereDate('date', $this->dateString($date))
            ->latest('id')
            ->first();
    }

    /** El campo captura solo hoy (día no liberado) o una fecha con habilitación activa. */
    public function fieldCanEdit(int $projectWorkId, CarbonInterface|string $date): bool
    {
        $date = $this->dateString($date);

        if ($date === HrClock::today()->toDateString() && ! $this->isDayClosed($projectWorkId, $date)) {
            return true;
        }

        return $this->activeUnlock($projectWorkId, $date) !== null;
    }

    /** RH captura sin motivo solo hoy con el día sin liberar; pasado o bloqueado exige motivo. */
    public function hrRequiresReason(int $projectWorkId, CarbonInterface|string $date): bool
    {
        $date = $this->dateString($date);

        return $date !== HrClock::today()->toDateString() || $this->isDayClosed($projectWorkId, $date);
    }

    public function isIncentiveDay(CarbonInterface|string $date): bool
    {
        return HrClock::isThursday($date);
    }

    /**
     * Crea o actualiza la incidencia de una persona en una fecha. Lanza DomainException
     * con el motivo si no se puede. El permiso y el bloqueo del día los valida quien llama.
     */
    public function capture(Worker $worker, ProjectWork $work, CarbonInterface|string $date, string $incident, ?string $reason = null): WorkerAttendance
    {
        $date = $this->dateString($date);

        if (! array_key_exists($incident, WorkerAttendance::INCIDENTS) || $incident === 'pending') {
            throw new DomainException('La incidencia no es válida.');
        }

        if (! $this->isInPadron($worker, $work->id, $date)) {
            throw new DomainException("{$worker->first_name} {$worker->last_name} no está asignado a {$work->name} en esa fecha.");
        }

        $state = $this->dayState($worker->id, $date);
        if (! in_array($incident, $this->allowedIncidents($state), true)) {
            throw new DomainException("La incidencia no está permitida para {$worker->first_name} {$worker->last_name} en ese día.");
        }

        $existing = WorkerAttendance::query()->where('worker_id', $worker->id)->whereDate('date', $date)->first();

        if ($existing && (int) $existing->project_work_id !== (int) $work->id) {
            $other = ProjectWork::find($existing->project_work_id)?->name ?? 'otra Obra';
            throw new DomainException("Ya lo registró {$other}. Si es un error, solicita corrección.");
        }

        return DB::transaction(function () use ($worker, $work, $date, $incident, $reason, $existing) {
            $before = $existing?->incident;

            $attendance = $existing ?? new WorkerAttendance(['worker_id' => $worker->id, 'date' => $date]);
            $attendance->fill([
                'project_work_id' => $work->id,
                'worker_group_id' => $worker->worker_group_id,
                'incident' => $incident,
                'captured_by' => Auth::id(),
                'captured_at' => now(),
            ])->save();

            if ($before !== $incident) {
                $this->history($attendance, 'Incidencia', $before ? WorkerAttendance::label($before) : null, WorkerAttendance::label($incident), $reason);
            }

            if (! in_array($incident, WorkerAttendance::WORKED, true)) {
                $this->clearSupport($worker, $date, $reason ?? 'La incidencia ya no es trabajada');
            }

            return $attendance;
        });
    }

    /**
     * Aplica una incidencia a varias personas. Las que no la admiten (vacaciones,
     * descanso rechazado…) se omiten.
     *
     * @return array{applied: int, total: int, skipped: array<int, string>}
     */
    public function captureBlock(ProjectWork $work, CarbonInterface|string $date, array $workerIds, string $incident, ?string $reason = null): array
    {
        $workers = Worker::query()->whereIn('id', $workerIds)->get();
        $applied = 0;
        $skipped = [];

        foreach ($workers as $worker) {
            try {
                $this->capture($worker, $work, $date, $incident, $reason);
                $applied++;
            } catch (DomainException $exception) {
                $skipped[] = $exception->getMessage();
            }
        }

        return ['applied' => $applied, 'total' => $workers->count(), 'skipped' => $skipped];
    }

    /** Anula (elimina) los registros de una persona en una Obra y deja el rastro en el historial. */
    public function annulRecords(Worker $worker, int $projectWorkId, CarbonInterface|string $from, CarbonInterface|string|null $to, string $reason): int
    {
        $records = WorkerAttendance::query()
            ->where('worker_id', $worker->id)
            ->where('project_work_id', $projectWorkId)
            ->whereDate('date', '>=', $this->dateString($from))
            ->when($to, fn ($query) => $query->whereDate('date', '<=', $this->dateString($to)))
            ->get();

        foreach ($records as $record) {
            $this->history($record, 'Anulación', WorkerAttendance::label($record->incident), null, $reason);
            $record->delete();
        }

        return $records->count();
    }

    public function setLetter(Worker $worker, ProjectWork $work, CarbonInterface|string $date, string $category): WeeklyIncentiveLetter
    {
        if (! in_array($category, WeeklyIncentiveLetter::CATEGORIES, true)) {
            throw new DomainException('La letra de incentivo no es válida.');
        }

        return WeeklyIncentiveLetter::updateOrCreate(
            ['worker_id' => $worker->id, 'payroll_period_start' => HrClock::periodOf($date)->toDateString()],
            ['project_work_id' => $work->id, 'category' => $category, 'set_by' => Auth::id()],
        );
    }

    public function setSupport(Worker $worker, ProjectWork $work, CarbonInterface|string $date, int $toProjectWorkId, string $fraction): WorkSupport
    {
        $date = $this->dateString($date);

        if (! array_key_exists($fraction, WorkSupport::FRACTIONS)) {
            throw new DomainException('La fracción del apoyo no es válida.');
        }

        if ($toProjectWorkId === (int) $work->id) {
            throw new DomainException('La Obra de cargo debe ser distinta de la Obra actual.');
        }

        $attendance = WorkerAttendance::query()->where('worker_id', $worker->id)->whereDate('date', $date)->first();

        if (! $attendance || ! $attendance->isWorked()) {
            throw new DomainException('El apoyo solo aplica si la incidencia cuenta como trabajada.');
        }

        ProjectWork::findOrFail($toProjectWorkId);

        return WorkSupport::updateOrCreate(
            ['worker_id' => $worker->id, 'date' => $date],
            ['from_project_work_id' => $work->id, 'to_project_work_id' => $toProjectWorkId, 'fraction' => $fraction, 'set_by' => Auth::id()],
        );
    }

    public function clearSupport(Worker $worker, CarbonInterface|string $date, ?string $reason = null): void
    {
        $support = WorkSupport::query()->where('worker_id', $worker->id)->whereDate('date', $this->dateString($date))->first();

        if (! $support) {
            return;
        }

        $attendance = WorkerAttendance::query()->where('worker_id', $worker->id)->whereDate('date', $this->dateString($date))->first();
        $support->delete();

        if ($attendance) {
            $this->history($attendance, 'Apoyo', 'Apoyo a otra Obra', null, $reason);
        }
    }

    /** Personas del padrón sin incidencia capturada (excluye los días virtuales de vacaciones/descanso). */
    public function pendingWorkerIds(int $projectWorkId, CarbonInterface|string $date): Collection
    {
        $date = $this->dateString($date);
        $captured = WorkerAttendance::query()
            ->where('project_work_id', $projectWorkId)
            ->whereDate('date', $date)
            ->where('incident', '!=', 'pending')
            ->pluck('worker_id');

        return $this->padron($projectWorkId, $date)->pluck('worker_id')
            ->diff($captured)
            ->filter(fn ($workerId) => $this->dayState($workerId, $date) === self::STATE_NORMAL)
            ->values();
    }

    /** Personas del padrón sin letra de incentivo en el periodo de la fecha. */
    public function missingLetterWorkerIds(int $projectWorkId, CarbonInterface|string $date): Collection
    {
        $date = $this->dateString($date);
        $withLetter = WeeklyIncentiveLetter::query()
            ->whereDate('payroll_period_start', HrClock::periodOf($date)->toDateString())
            ->pluck('worker_id');

        return $this->padron($projectWorkId, $date)->pluck('worker_id')->diff($withLetter)->values();
    }

    /** Libera el día: no avanza con Pendientes ni, en día de incentivo, con personas sin letra. */
    public function closeDay(ProjectWork $work, CarbonInterface|string $date): AttendanceDayClosure
    {
        $date = $this->dateString($date);

        if ($this->isDayClosed($work->id, $date)) {
            throw new DomainException('El día ya está liberado.');
        }

        $pending = $this->pendingWorkerIds($work->id, $date)->count();
        if ($pending > 0) {
            throw new DomainException("Hay {$pending} persona(s) con incidencia pendiente.");
        }

        if ($this->isIncentiveDay($date)) {
            $missing = $this->missingLetterWorkerIds($work->id, $date)->count();
            if ($missing > 0) {
                throw new DomainException("Hay {$missing} persona(s) sin letra de incentivo.");
            }
        }

        return AttendanceDayClosure::create(['project_work_id' => $work->id, 'date' => $date, 'closed_by' => Auth::id()]);
    }

    public function history(WorkerAttendance $attendance, string $field, ?string $before, ?string $after, ?string $reason = null): AttendanceHistory
    {
        return AttendanceHistory::create([
            'worker_attendance_id' => $attendance->exists ? $attendance->id : null,
            'worker_id' => $attendance->worker_id,
            'date' => $attendance->date,
            'project_work_id' => $attendance->project_work_id,
            'user_id' => Auth::id(),
            'field' => $field,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ]);
    }

    private function dateString(CarbonInterface|string $date): string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : substr($date, 0, 10);
    }
}
