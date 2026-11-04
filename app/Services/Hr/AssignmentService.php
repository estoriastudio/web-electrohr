<?php

namespace App\Services\Hr;

use App\Models\CaptureUnlock;
use App\Models\PersonnelRequest;
use App\Models\PersonnelRequestLine;
use App\Models\ProjectWork;
use App\Models\User;
use App\Models\WorkAssignment;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Asignaciones trabajador → Obra con vigencia, solicitudes del campo y su resolución (HR-006).
 * El permiso y el alcance los valida quien llama; aquí viven las reglas del flujo.
 */
class AssignmentService
{
    public function __construct(
        private NotificationService $notification,
        private AttendanceService $attendance,
        private WorkerProfileService $profile,
    ) {}

    public function currentAssignment(Worker|int $worker): ?WorkAssignment
    {
        $workerId = $worker instanceof Worker ? $worker->id : $worker;

        return WorkAssignment::query()->current()->where('worker_id', $workerId)->latest('start_date')->first();
    }

    /**
     * Fecha mínima de una asignación: el mayor entre el inicio de la semana activa (viernes)
     * y el día siguiente al último día capturado en otra Obra.
     */
    public function minimumDate(Worker|int $worker, ?int $targetProjectWorkId = null): CarbonImmutable
    {
        $workerId = $worker instanceof Worker ? $worker->id : $worker;
        $minimum = HrClock::periodOf();

        $lastCaptured = WorkerAttendance::query()
            ->where('worker_id', $workerId)
            ->when($targetProjectWorkId, fn ($query) => $query->where('project_work_id', '!=', $targetProjectWorkId))
            ->max('date');

        if ($lastCaptured) {
            $afterCapture = CarbonImmutable::parse($lastCaptured)->addDay();
            $minimum = $afterCapture->greaterThan($minimum) ? $afterCapture : $minimum;
        }

        return $minimum;
    }

    /** Motivo del conflicto de una línea pendiente, o null si no hay. */
    public function conflictReason(PersonnelRequestLine $line): ?string
    {
        $workId = $line->personnelRequest->project_work_id;
        $current = $this->currentAssignment($line->worker_id);

        if ($current && (int) $current->project_work_id !== (int) $workId) {
            return 'Está asignado a '.($current->projectWork?->name ?? 'otra Obra');
        }

        $other = PersonnelRequestLine::query()
            ->where('worker_id', $line->worker_id)
            ->where('status', 'pending')
            ->where('id', '!=', $line->id)
            ->whereHas('personnelRequest', fn ($query) => $query->where('status', 'sent')->where('project_work_id', '!=', $workId))
            ->with('personnelRequest.projectWork')
            ->first();

        return $other ? 'Lo pidió también '.($other->personnelRequest->projectWork?->name ?? 'otra Obra') : null;
    }

    /** @return array{approved: int, conflicts: int} */
    public function approveRequest(PersonnelRequest $request, User $by): array
    {
        $approved = 0;
        $conflicts = 0;

        foreach ($request->lines()->where('status', 'pending')->with('worker')->get() as $line) {
            $line->setRelation('personnelRequest', $request);

            if ($this->conflictReason($line) !== null) {
                $conflicts++;

                continue;
            }

            $this->approveLine($line, $by);
            $approved++;
        }

        $this->closeRequestIfDone($request);

        return ['approved' => $approved, 'conflicts' => $conflicts];
    }

    /** Aprueba una línea (sin revisar conflicto: eso lo decide quien llama). */
    public function approveLine(PersonnelRequestLine $line, User $by): WorkAssignment
    {
        return DB::transaction(function () use ($line, $by) {
            $request = $line->personnelRequest;
            $worker = $line->worker;
            $work = $request->projectWork;

            $requested = $request->start_date ? CarbonImmutable::parse($request->start_date) : HrClock::today();
            $minimum = $this->minimumDate($worker, $work->id);
            $effective = $requested->greaterThan($minimum) ? $requested : $minimum;

            $current = $this->currentAssignment($worker);
            $alreadyThere = $current && (int) $current->project_work_id === (int) $work->id;
            $assignment = $alreadyThere ? $current : $this->assign($worker, $work, $effective, $by, $request);

            HrAudit::log(
                'Asignación aprobada',
                'Asignación',
                $assignment->id,
                $worker,
                $current && ! $alreadyThere ? $current->projectWork?->name : 'Sin asignación',
                $work->name.' (desde '.$assignment->start_date->format('d/m/Y').')',
                $alreadyThere ? 'La persona ya estaba en la Obra' : 'Solicitud de '.($request->requestedByUser?->name ?? 'campo'),
            );

            $line->update([
                'status' => 'approved',
                'resolved_by' => $by->id,
                'resolved_at' => now(),
                'effective_date' => $assignment->start_date,
            ]);

            $this->activateOnApproval($worker);
            $this->closeRequestIfDone($request);

            return $assignment;
        });
    }

    public function rejectLine(PersonnelRequestLine $line, User $by, string $reason): void
    {
        $line->update([
            'status' => 'rejected',
            'resolved_by' => $by->id,
            'resolved_at' => now(),
            'note' => $reason,
        ]);

        HrAudit::log(
            'Línea de solicitud rechazada',
            'Asignación',
            $line->personnel_request_id,
            $line->worker_id,
            $line->personnelRequest?->projectWork?->name,
            'Rechazada',
            $reason,
        );

        $this->closeRequestIfDone($line->personnelRequest);
    }

    public function rejectRequest(PersonnelRequest $request, User $by, string $reason): int
    {
        $lines = $request->lines()->where('status', 'pending')->get();

        foreach ($lines as $line) {
            $line->setRelation('personnelRequest', $request);
            $this->rejectLine($line, $by, $reason);
        }

        return $lines->count();
    }

    /**
     * Resuelve un conflicto: here = asignar a la Obra de la solicitud, stay = dejar donde está,
     * other = asignar a una tercera Obra.
     */
    public function resolveConflict(PersonnelRequestLine $line, string $action, User $by, ?int $otherProjectWorkId = null): string
    {
        return DB::transaction(function () use ($line, $action, $by, $otherProjectWorkId) {
            $worker = $line->worker;

            if ($action === 'stay') {
                $this->rejectLine($line, $by, 'Se dejó donde está');

                return 'La persona se queda en su Obra actual.';
            }

            if ($action === 'here') {
                $this->approveLine($line, $by);
                $this->rejectOtherPendingLines($worker, $line, $by, 'Se asignó a otra Obra');

                return 'Asignado a '.$line->personnelRequest->projectWork->name.'.';
            }

            if ($action === 'other') {
                $other = ProjectWork::findOrFail($otherProjectWorkId);
                $effective = $this->minimumDate($worker, $other->id);
                $this->assign($worker, $other, $effective, $by, $line->personnelRequest);
                $this->activateOnApproval($worker);
                $this->rejectLine($line, $by, "Asignado a {$other->name}");
                $this->rejectOtherPendingLines($worker, $line, $by, "Se asignó a {$other->name}");

                return "Asignado a {$other->name}.";
            }

            throw new DomainException('La acción no es válida.');
        });
    }

    /** Asignación directa de RH (Incongruencias o Reasignar), con fecha efectiva y motivo en bitácora. */
    public function directAssign(Worker $worker, ProjectWork $work, CarbonInterface|string $date, User $by, string $reason): WorkAssignment
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $minimum = $this->minimumDate($worker, $work->id);

        if ($date->lessThan($minimum)) {
            throw new DomainException('La fecha no puede ser anterior al '.$minimum->format('d/m/Y').'.');
        }

        if ($date->greaterThan(HrClock::today())) {
            throw new DomainException('La fecha efectiva no puede ser futura.');
        }

        return DB::transaction(function () use ($worker, $work, $date, $by, $reason) {
            $previous = $this->currentAssignment($worker);
            $assignment = $this->assign($worker, $work, $date, $by);
            $this->activateOnApproval($worker);

            HrAudit::log(
                $previous ? 'Reasignación a Obra' : 'Asignación a Obra',
                'Asignación',
                $assignment->id,
                $worker,
                $previous?->projectWork?->name ?? 'Sin asignación',
                $work->name.' (desde '.$date->format('d/m/Y').')',
                $reason,
            );

            $this->notification->send([
                'type' => 'WorkAssignment',
                'action_by' => $by->id,
                'model_action' => 'create',
                'model_id' => $assignment->id,
                'data' => "asignó a {$worker->first_name} {$worker->last_name} a {$work->name}.",
            ]);

            return $assignment;
        });
    }

    /** Anula una asignación aprobada por error: borra la asistencia capturada con ella y deja Sin asignación. */
    public function void(WorkAssignment $assignment, User $by, string $reason): int
    {
        if ($assignment->voided_at) {
            throw new DomainException('La asignación ya está anulada.');
        }

        return DB::transaction(function () use ($assignment, $by, $reason) {
            $deleted = $this->attendance->annulRecords(
                $assignment->worker,
                $assignment->project_work_id,
                $assignment->start_date,
                $assignment->end_date,
                "Anulación de asignación: {$reason}",
            );

            $assignment->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason]);

            HrAudit::log(
                'Anulación de asignación',
                'Asignación',
                $assignment->id,
                $assignment->worker_id,
                $assignment->projectWork?->name,
                'Sin asignación',
                $reason,
            );

            $this->notification->send([
                'type' => 'WorkAssignment',
                'action_by' => $by->id,
                'model_action' => 'destroy',
                'model_id' => $assignment->id,
                'data' => "anuló la asignación de {$assignment->worker->first_name} {$assignment->worker->last_name} a {$assignment->projectWork?->name}.",
            ]);

            return $deleted;
        });
    }

    /** Mensaje si el NSS o la CURP ya están registrados, diciendo de quién son; null si están libres. */
    public function duplicateMessage(string $nss, string $curp): ?string
    {
        $byNss = Worker::withTrashed()->where('nss', $nss)->first();
        if ($byNss) {
            return 'El NSS ya está registrado a nombre de '.$byNss->first_name.' '.$byNss->last_name.'.';
        }

        $byCurp = Worker::withTrashed()->where('curp', $curp)->first();
        if ($byCurp) {
            return 'La CURP ya está registrada a nombre de '.$byCurp->first_name.' '.$byCurp->last_name.'.';
        }

        return null;
    }

    /** Alta en obra: pre-registro con asignación provisional y plazo de 7 días para el expediente. */
    public function registerInField(User $by, ProjectWork $work, array $data): Worker
    {
        $message = $this->duplicateMessage($data['nss'], $data['curp']);
        if ($message) {
            throw new DomainException($message);
        }

        return DB::transaction(function () use ($by, $work, $data) {
            $today = HrClock::today();

            $worker = Worker::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'nss' => $data['nss'],
                'curp' => strtoupper($data['curp']),
                'weekly_salary' => 0,
                'payment_type' => 'salaried',
                'status' => 'pre_registered',
                'registered_by_user_id' => $by->id,
                'field_registered_at' => HrClock::now(),
                'profile_complete_deadline' => $today->addDays(7)->toDateString(),
            ]);

            $this->assign($worker, $work, $today, $by, null, provisional: true);

            HrAudit::log('Alta en obra', 'Trabajador', $worker->id, $worker, null, "Pre-registro en {$work->name}");

            return $worker;
        });
    }

    /** Fusiona un pre-registro duplicado en el trabajador correcto y lo da de baja lógica. */
    public function mergeDuplicate(Worker $duplicate, Worker $target, User $by): void
    {
        if ($duplicate->id === $target->id) {
            throw new DomainException('No se puede fusionar a la misma persona.');
        }

        if ($duplicate->status !== 'pre_registered') {
            throw new DomainException('Solo se puede fusionar un pre-registro.');
        }

        DB::transaction(function () use ($duplicate, $target) {
            WorkAssignment::query()->where('worker_id', $duplicate->id)->update(['worker_id' => $target->id]);

            // Si la persona correcta ya tenía otra vigente, la del duplicado deja de serlo.
            $current = WorkAssignment::query()->current()->where('worker_id', $target->id)->orderByDesc('start_date')->get();
            foreach ($current->skip(1) as $extra) {
                $extra->update(['end_date' => $extra->start_date, 'closed_reason' => 'Fusión de duplicado']);
            }

            foreach (PersonnelRequestLine::query()->where('worker_id', $duplicate->id)->get() as $line) {
                $exists = PersonnelRequestLine::query()
                    ->where('worker_id', $target->id)
                    ->where('personnel_request_id', $line->personnel_request_id)
                    ->exists();
                $exists ? $line->delete() : $line->update(['worker_id' => $target->id]);
            }

            foreach (['worker_attendances', 'overtime_entries', 'work_supports'] as $table) {
                $taken = DB::table($table)->where('worker_id', $target->id)->pluck('date');
                DB::table($table)->where('worker_id', $duplicate->id)->whereIn('date', $taken)->delete();
                DB::table($table)->where('worker_id', $duplicate->id)->update(['worker_id' => $target->id]);
            }

            HrAudit::log(
                'Fusión de duplicado',
                'Trabajador',
                $target->id,
                $target,
                "{$duplicate->first_name} {$duplicate->last_name} (NSS {$duplicate->nss})",
                "{$target->first_name} {$target->last_name}",
                'Pre-registro duplicado fusionado',
            );

            $duplicate->delete();
        });
    }

    /** Activos sin asignación vigente y personas solicitadas por más de una Obra (calculadas, sin tabla). */
    public function inconsistencies(): array
    {
        $unassigned = Worker::query()
            ->with(['positionCategory', 'workerGroup'])
            ->where('status', 'active')
            ->whereDoesntHave('assignments', fn ($query) => $query->current())
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $multiple = PersonnelRequestLine::query()
            ->with(['worker', 'personnelRequest.projectWork.project'])
            ->where('status', 'pending')
            ->whereHas('personnelRequest', fn ($query) => $query->where('status', 'sent'))
            ->get()
            ->groupBy('worker_id')
            ->filter(fn (Collection $lines) => $lines->pluck('personnelRequest.project_work_id')->unique()->count() > 1);

        return compact('unassigned', 'multiple');
    }

    /** Avisa al solicitante, agrupado, qué se resolvió de su solicitud. */
    public function notifyRequester(PersonnelRequest $request, User $by, string $summary): void
    {
        $this->notification->sendToRecipients([
            'type' => 'PersonnelRequest',
            'action_by' => $by->id,
            'model_action' => 'update',
            'model_id' => $request->id,
            'data' => "resolvió tu solicitud de personal para {$request->projectWork?->name}: {$summary}",
        ], [$request->requested_by]);
    }

    public function closeRequestIfDone(PersonnelRequest $request): void
    {
        $request->refresh();

        if ($request->status === 'sent'
            && $request->canTransitionTo('closed')
            && ! $request->lines()->where('status', 'pending')->exists()) {
            $request->update(['status' => 'closed']);
        }
    }

    /**
     * Crea la asignación vigente: cierra la anterior un día antes de la efectiva y, si la
     * fecha es pasada, abre captura 24 h en la Obra receptora (HR-007).
     */
    public function assign(Worker $worker, ProjectWork $work, CarbonInterface $effective, User $by, ?PersonnelRequest $request = null, bool $provisional = false): WorkAssignment
    {
        $current = $this->currentAssignment($worker);

        if ($current && (int) $current->project_work_id === (int) $work->id) {
            return $current;
        }

        if ($current) {
            if ($current->start_date->greaterThanOrEqualTo($effective)) {
                $current->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => 'Reemplazada el mismo día', 'end_date' => $current->start_date]);
            } else {
                $current->update(['end_date' => $effective->subDay()->toDateString(), 'closed_reason' => 'Reasignación']);
            }
        }

        $assignment = WorkAssignment::create([
            'worker_id' => $worker->id,
            'project_id' => $work->project_id,
            'project_work_id' => $work->id,
            'worker_group_id' => $worker->worker_group_id,
            'start_date' => $effective->toDateString(),
            'personnel_request_id' => $request?->id,
            'replaced_assignment_id' => $current?->id,
            'provisional' => $provisional,
            'approved_by' => $provisional ? null : $by->id,
            'approved_at' => $provisional ? null : now(),
        ]);

        $this->unlockRetroactiveDays($work, $effective, $by);

        return $assignment;
    }

    private function unlockRetroactiveDays(ProjectWork $work, CarbonInterface $effective, User $by): void
    {
        $day = CarbonImmutable::parse($effective)->startOfDay();
        $yesterday = HrClock::yesterday();

        for ($count = 0; $day->lessThanOrEqualTo($yesterday) && $count < 31; $count++, $day = $day->addDay()) {
            CaptureUnlock::create([
                'project_work_id' => $work->id,
                'date' => $day->toDateString(),
                'reason' => 'Asignación con fecha pasada',
                'granted_by' => $by->id,
                'expires_at' => now()->addDay(),
                'active' => true,
            ]);
        }
    }

    /** Aprobar activa el pre-registro; sin expediente completo queda con la marca de expediente incompleto. */
    private function activateOnApproval(Worker $worker): void
    {
        if ($worker->status !== 'pre_registered') {
            return;
        }

        $this->profile->activate($worker, true, 'Aprobación de asignación a Obra');
    }

    private function rejectOtherPendingLines(Worker $worker, PersonnelRequestLine $except, User $by, string $note): void
    {
        $others = PersonnelRequestLine::query()
            ->with('personnelRequest.projectWork')
            ->where('worker_id', $worker->id)
            ->where('status', 'pending')
            ->where('id', '!=', $except->id)
            ->get();

        foreach ($others as $other) {
            $this->rejectLine($other, $by, $note);
            $this->notifyRequester($other->personnelRequest, $by, "{$worker->first_name} {$worker->last_name}: {$note}.");
        }
    }
}
