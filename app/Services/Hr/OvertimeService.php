<?php

namespace App\Services\Hr;

use App\Models\HrSetting;
use App\Models\LateOvertimeRequest;
use App\Models\OvertimeClosure;
use App\Models\OvertimeEntry;
use App\Models\ProjectWork;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkSupport;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Horas extra (HR-008): se capturan al día siguiente hasta ot_deadline_hour (America/Mexico_City),
 * sin importes, y las tardías pasan por una solicitud a RH. Las horas son enteras de 1 a 16.
 */
class OvertimeService
{
    public const MIN_HOURS = 1;

    public const MAX_HOURS = 16;

    public function __construct(private AttendanceService $attendance) {}

    public function deadlineHour(): int
    {
        return (int) HrSetting::valueOf('ot_deadline_hour', 10);
    }

    /** Límite de captura de las HE de una fecha: el día siguiente a ot_deadline_hour. */
    public function deadline(CarbonInterface|string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, config('app.timezone'))->startOfDay()->addDay()->setTime($this->deadlineHour(), 0);
    }

    public function isClosed(int $projectWorkId, CarbonInterface|string $date): bool
    {
        return OvertimeClosure::query()
            ->where('project_work_id', $projectWorkId)
            ->whereDate('date', $this->dateString($date))
            ->exists();
    }

    /** Ventana abierta: solo las HE de ayer, hasta el límite de hoy o hasta que se liberen. */
    public function windowOpen(int $projectWorkId, CarbonInterface|string $date): bool
    {
        $date = $this->dateString($date);

        return $date === HrClock::yesterday()->toDateString()
            && HrClock::now()->lessThan($this->deadline($date))
            && ! $this->isClosed($projectWorkId, $date);
    }

    /** Captura HE (hours = 0 las borra). El campo exige ventana abierta; RH las captura con motivo. */
    public function capture(Worker $worker, ProjectWork $work, CarbonInterface|string $date, int $hours, string $source, ?string $reason = null): ?OvertimeEntry
    {
        $date = $this->dateString($date);

        if ($hours !== 0 && ($hours < self::MIN_HOURS || $hours > self::MAX_HOURS)) {
            throw new DomainException('Las horas extra son enteras de '.self::MIN_HOURS.' a '.self::MAX_HOURS.'.');
        }

        if (! in_array($source, ['field', 'hr'], true)) {
            throw new DomainException('El origen de las horas extra no es válido.');
        }

        if ($source === 'field' && ! $this->windowOpen($work->id, $date)) {
            throw new DomainException('La captura de horas extra de ese día ya cerró.');
        }

        if ($source === 'hr' && blank($reason)) {
            throw new DomainException('RH debe indicar el motivo de las horas extra.');
        }

        if (! $this->attendance->isInPadron($worker, $work->id, $date)) {
            throw new DomainException("{$worker->first_name} {$worker->last_name} no está en el padrón de {$work->name} ese día.");
        }

        $entry = OvertimeEntry::query()->where('worker_id', $worker->id)->whereDate('date', $date)->first();

        if ($hours === 0) {
            $entry?->delete();

            return null;
        }

        return OvertimeEntry::updateOrCreate(
            ['worker_id' => $worker->id, 'date' => $date],
            [
                'project_work_id' => $work->id,
                'charge_project_work_id' => $this->chargeWorkId($worker, $work, $date),
                'hours' => $hours,
                'source' => $source,
                'set_by' => Auth::id(),
            ],
        );
    }

    /** "Liberar horas extra": cierra la captura de la Obra y fecha antes de la hora límite. */
    public function release(ProjectWork $work, CarbonInterface|string $date): OvertimeClosure
    {
        $date = $this->dateString($date);

        if ($this->isClosed($work->id, $date)) {
            throw new DomainException('Las horas extra de ese día ya están liberadas.');
        }

        return OvertimeClosure::create(['project_work_id' => $work->id, 'date' => $date, 'closed_by' => Auth::id()]);
    }

    /** Solicitud de HE fuera de plazo, persona por persona. */
    public function requestLate(Worker $worker, ProjectWork $work, CarbonInterface|string $date, int $hours, string $reason, User $by): LateOvertimeRequest
    {
        $date = $this->dateString($date);

        if ($hours < self::MIN_HOURS || $hours > self::MAX_HOURS) {
            throw new DomainException('Las horas extra son enteras de '.self::MIN_HOURS.' a '.self::MAX_HOURS.'.');
        }

        if (blank($reason)) {
            throw new DomainException('El motivo es obligatorio.');
        }

        if ($date >= HrClock::today()->toDateString()) {
            throw new DomainException('La fecha debe ser anterior a hoy.');
        }

        if ($this->windowOpen($work->id, $date)) {
            throw new DomainException('La ventana de captura sigue abierta: captura las horas extra directamente.');
        }

        if (! $this->attendance->isInPadron($worker, $work->id, $date)) {
            throw new DomainException("{$worker->first_name} {$worker->last_name} no estaba en el padrón de {$work->name} ese día.");
        }

        $pending = LateOvertimeRequest::query()
            ->where('worker_id', $worker->id)
            ->whereDate('date', $date)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw new DomainException('Ya hay una solicitud pendiente para esa persona y día.');
        }

        return LateOvertimeRequest::create([
            'worker_id' => $worker->id,
            'project_work_id' => $work->id,
            'date' => $date,
            'hours' => $hours,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $by->id,
        ]);
    }

    public function approve(LateOvertimeRequest $request, User $by, ?string $note = null): OvertimeEntry
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $by, $note) {
            $worker = $request->worker;
            $work = $request->projectWork;
            $date = $request->date->toDateString();
            $before = OvertimeEntry::query()->where('worker_id', $worker->id)->whereDate('date', $date)->value('hours');

            $entry = OvertimeEntry::updateOrCreate(
                ['worker_id' => $worker->id, 'date' => $date],
                [
                    'project_work_id' => $work->id,
                    'charge_project_work_id' => $this->chargeWorkId($worker, $work, $date),
                    'hours' => $request->hours,
                    'source' => 'late',
                    'set_by' => $by->id,
                ],
            );

            $request->update(['status' => 'approved', 'resolved_by' => $by->id, 'resolved_at' => now(), 'note' => $note]);

            HrAudit::log(
                'HE fuera de plazo aprobada',
                'Horas extra',
                $request->id,
                $worker,
                $before !== null ? "{$before} h" : null,
                "{$request->hours} h",
                "Pidió {$request->requestedByUser?->name}: {$request->reason}".($note ? " · Aprobó {$by->name}: {$note}" : " · Aprobó {$by->name}"),
            );

            return $entry;
        });
    }

    public function reject(LateOvertimeRequest $request, User $by, ?string $note = null): void
    {
        $this->assertPending($request);

        $request->update(['status' => 'rejected', 'resolved_by' => $by->id, 'resolved_at' => now(), 'note' => $note]);

        HrAudit::log(
            'HE fuera de plazo rechazada',
            'Horas extra',
            $request->id,
            $request->worker_id,
            "{$request->hours} h",
            null,
            "Pidió {$request->requestedByUser?->name}: {$request->reason}".($note ? " · Rechazó {$by->name}: {$note}" : " · Rechazó {$by->name}"),
        );
    }

    private function assertPending(LateOvertimeRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw new DomainException('La solicitud ya fue resuelta.');
        }
    }

    /** Obra de cargo: la que absorbe el costo si la persona dio apoyo ese día; si no, donde trabajó. */
    private function chargeWorkId(Worker $worker, ProjectWork $work, string $date): int
    {
        $support = WorkSupport::query()->where('worker_id', $worker->id)->whereDate('date', $date)->first();

        return (int) ($support?->to_project_work_id ?? $work->id);
    }

    private function dateString(CarbonInterface|string $date): string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : substr($date, 0, 10);
    }
}
