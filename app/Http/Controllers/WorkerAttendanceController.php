<?php

namespace App\Http\Controllers;

use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Models\WorkerGroup;
use App\Models\WorkerGroupMember;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkerAttendanceController extends Controller
{
    private const ABSENCE_REASONS = [
        'absence' => 'Inasistencia',
        'rest' => 'Descanso',
        'incapacity' => 'Incapacidad',
        'permission' => 'Permiso',
    ];

    public function __construct(private NotificationService $notification) {}

    public function index(Request $request): View
    {
        $request->validate(['date' => 'nullable|date']);
        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()))->startOfDay()->locale('es');
        $date = $selectedDate->toDateString();

        $workerGroups = WorkerGroup::query()
            ->with('projectWork')
            ->withCount([
                'members as assigned_count' => function ($query) use ($date) {
                    $query->where('worker_group_members.joined_at', '<=', $date)
                        ->where(function ($membershipQuery) use ($date) {
                            $membershipQuery->whereNull('worker_group_members.left_at')
                                ->orWhereDate('worker_group_members.left_at', '>=', $date);
                        });
                },
                'attendances as recorded_count' => fn ($query) => $query->whereDate('date', $date),
                'attendances as present_count' => fn ($query) => $query->whereDate('date', $date)->where('attended', true),
                'attendances as absent_count' => fn ($query) => $query->whereDate('date', $date)->where('attended', false),
                'attendances as incapacity_count' => fn ($query) => $query
                    ->whereDate('date', $date)
                    ->where('attended', false)
                    ->where('absence_reason', 'incapacity'),
            ])
            ->orderBy('name')
            ->get()
            ->each(function (WorkerGroup $workerGroup) {
                $workerGroup->setAttribute('pending_count', max(0, $workerGroup->assigned_count - $workerGroup->recorded_count));
            });

        $summary = [
            'groups' => $workerGroups->count(),
            'assigned' => $workerGroups->sum('assigned_count'),
            'present' => $workerGroups->sum('present_count'),
            'absent' => $workerGroups->sum('absent_count'),
            'incapacities' => $workerGroups->sum('incapacity_count'),
            'pending' => $workerGroups->sum('pending_count'),
        ];
        $summary['attendance_rate'] = $summary['assigned'] > 0
            ? round(($summary['present'] / $summary['assigned']) * 100)
            : 0;
        $previousDate = $selectedDate->copy()->subDay()->toDateString();
        $nextDate = $selectedDate->copy()->addDay()->toDateString();
        $canGoNext = $selectedDate->lt(today()->startOfDay());

        return view('human_resources.attendances.index', compact(
            'workerGroups',
            'summary',
            'selectedDate',
            'date',
            'previousDate',
            'nextDate',
            'canGoNext',
        ));
    }

    public function groupAttendance(Request $request, WorkerGroup $workerGroup): View
    {
        $request->validate(['date' => 'nullable|date']);
        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()))->startOfDay()->locale('es');
        $date = $selectedDate->toDateString();
        $workerGroup->load('projectWork');

        $members = $workerGroup->members()
            ->with('positionCategory')
            ->wherePivot('joined_at', '<=', $date)
            ->where(function ($membershipQuery) use ($date) {
                $membershipQuery->whereNull('worker_group_members.left_at')
                    ->orWhereDate('worker_group_members.left_at', '>=', $date);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $attendances = WorkerAttendance::query()
            ->where('worker_group_id', $workerGroup->id)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('worker_id');
        $previousDate = $selectedDate->copy()->subDay()->toDateString();
        $nextDate = $selectedDate->copy()->addDay()->toDateString();
        $canGoNext = $selectedDate->lt(today()->startOfDay());

        return view('human_resources.worker-groups.attendance', compact(
            'workerGroup',
            'members',
            'attendances',
            'selectedDate',
            'date',
            'previousDate',
            'nextDate',
            'canGoNext',
        ));
    }

    public function markGroupAttendance(Request $request, WorkerGroup $workerGroup, Worker $worker): JsonResponse
    {
        $data = $request->validate([
            'date' => 'required|date',
            'attended' => 'required|boolean',
            'absence_reason' => ['nullable', Rule::in(array_keys(self::ABSENCE_REASONS))],
            'absence_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);
        $isAttending = (bool) $data['attended'];

        if (! $isAttending && blank($data['absence_reason'] ?? null)) {
            throw ValidationException::withMessages([
                'absence_reason' => 'Selecciona el motivo de la inasistencia.',
            ]);
        }

        if (! $isAttending && ! $request->hasFile('absence_document')) {
            throw ValidationException::withMessages([
                'absence_document' => 'Adjunta un comprobante para registrar la inasistencia.',
            ]);
        }

        $membershipData = [
            'worker_id' => $worker->id,
            'worker_group_id' => $workerGroup->id,
            'date' => $data['date'],
        ];
        $this->ensureActiveMembership($membershipData);

        $attendance = WorkerAttendance::query()
            ->where('worker_id', $worker->id)
            ->whereDate('date', $data['date'])
            ->first();

        if ($attendance && $attendance->worker_group_id !== $workerGroup->id) {
            return response()->json([
                'message' => 'Ya existe una asistencia para este trabajador en otra cuadrilla durante esta fecha.',
            ], 422);
        }

        if ($attendance) {
            $this->ensureUnlocked($attendance);
        }

        $isNew = ! $attendance;
        $attendance ??= new WorkerAttendance([
            'worker_id' => $worker->id,
            'worker_group_id' => $workerGroup->id,
            'date' => $data['date'],
        ]);
        $attendanceDate = Carbon::parse($data['date']);
        $attendance->fill([
            'worker_group_id' => $workerGroup->id,
            'date' => $data['date'],
            'week_number' => $attendanceDate->isoWeek(),
            'year' => $attendanceDate->isoWeekYear(),
            'role' => $attendance->role ?? $worker->positionCategory?->name,
            'attended' => $isAttending,
            'overtime_hours' => $attendance->overtime_hours ?? 0,
        ]);

        $previousDocumentPath = $attendance->absence_document_path;
        $absenceDocumentPath = null;

        if ($isAttending) {
            $attendance->absence_reason = null;
            $attendance->absence_document_path = null;
        } else {
            $absenceDocumentPath = $this->storeAbsenceDocument($request, $worker->id);
            $attendance->absence_reason = $data['absence_reason'];
            $attendance->absence_document_path = $absenceDocumentPath;
        }

        $attendance->save();

        if ($previousDocumentPath && $previousDocumentPath !== $absenceDocumentPath) {
            Storage::disk('s3')->delete($previousDocumentPath);
        }

        $statusLabel = $attendance->attended ? 'asistencia' : mb_strtolower($this->absenceReasonLabel($attendance->absence_reason));
        $this->notify($attendance, $isNew ? 'create' : 'update', "registró {$statusLabel} de {$worker->first_name} {$worker->last_name}.");

        return response()->json([
            'message' => ucfirst($statusLabel) . ' registrada correctamente.',
            'attendance' => [
                'worker_id' => $worker->id,
                'attended' => $attendance->attended,
                'status_label' => $attendance->attended ? 'Show' : $this->absenceReasonLabel($attendance->absence_reason),
                'absence_document_path' => $attendance->absence_document_path,
            ],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('human_resources.worker-attendances.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureNoDuplicate($data);
        $this->ensureActiveMembership($data);

        if (! $data['attended']) {
            $data['absence_document_path'] = $this->storeAbsenceDocument($request, $data['worker_id']);
        }

        $attendance = WorkerAttendance::create($data);

        $this->notify($attendance, 'create', "registró la asistencia de {$attendance->worker->first_name} {$attendance->worker->last_name}.");

        return redirect()->route('human_resources.worker-attendances.index')
            ->with('success', 'Asistencia registrada correctamente.');
    }

    public function show(WorkerAttendance $workerAttendance): View
    {
        $workerAttendance->load(['worker', 'workerGroup.projectWork']);

        return view('human_resources.attendances.show', compact('workerAttendance'));
    }

    public function edit(WorkerAttendance $workerAttendance): View
    {
        $workerGroups = WorkerGroup::with('projectWork')->where('status', 'active')->orderBy('name')->get();
        $workers = Worker::whereIn('status', ['pre_registered', 'active'])->orderBy('last_name')->orderBy('first_name')->get();

        return view('human_resources.attendances.edit', compact('workerAttendance', 'workerGroups', 'workers'));
    }

    public function update(Request $request, WorkerAttendance $workerAttendance): RedirectResponse
    {
        $this->ensureUnlocked($workerAttendance);
        $data = $this->validatedData($request, $workerAttendance);
        $this->ensureNoDuplicate($data, $workerAttendance);
        $this->ensureActiveMembership($data);

        $previousDocumentPath = $workerAttendance->absence_document_path;

        if ($data['attended']) {
            $data['absence_document_path'] = null;
        } elseif ($request->hasFile('absence_document')) {
            $data['absence_document_path'] = $this->storeAbsenceDocument($request, $data['worker_id']);
        }

        $workerAttendance->update($data);

        if ($previousDocumentPath && $previousDocumentPath !== $workerAttendance->absence_document_path) {
            Storage::disk('s3')->delete($previousDocumentPath);
        }

        $this->notify($workerAttendance, 'update', "actualizó la asistencia de {$workerAttendance->worker->first_name} {$workerAttendance->worker->last_name}.");

        return redirect()->route('human_resources.worker-attendances.show', $workerAttendance)
            ->with('success', 'Asistencia actualizada correctamente.');
    }

    public function destroy(WorkerAttendance $workerAttendance): RedirectResponse
    {
        $this->ensureUnlocked($workerAttendance);
        $workerAttendance->loadMissing('worker');
        $name = "{$workerAttendance->worker->first_name} {$workerAttendance->worker->last_name}";
        if ($workerAttendance->absence_document_path) {
            Storage::disk('s3')->delete($workerAttendance->absence_document_path);
        }
        $workerAttendance->delete();

        $this->notify($workerAttendance, 'destroy', "eliminó la asistencia de {$name}.");

        return redirect()->route('human_resources.worker-attendances.index')
            ->with('success', 'Asistencia eliminada correctamente.');
    }

    public function downloadAbsenceDocument(WorkerAttendance $workerAttendance): mixed
    {
        $path = $workerAttendance->absence_document_path;

        abort_unless($path && Storage::disk('s3')->exists($path), 404);

        return Storage::disk('s3')->download($path);
    }

    private function validatedData(Request $request, ?WorkerAttendance $attendance = null): array
    {
        $data = $request->validate([
            'worker_id' => 'required|exists:workers,id',
            'worker_group_id' => 'required|exists:worker_groups,id',
            'date' => 'required|date',
            'role' => 'nullable|string|max:255',
            'attended' => 'nullable|boolean',
            'absence_reason' => ['nullable', Rule::in(array_keys(self::ABSENCE_REASONS))],
            'absence_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'overtime_hours' => 'nullable|numeric|min:0|max:99.99',
            'notes' => 'nullable|string|max:1000',
        ]);
        $attendanceDate = Carbon::parse($data['date']);

        $data['week_number'] = $attendanceDate->isoWeek();
        $data['year'] = $attendanceDate->isoWeekYear();
        $data['attended'] = $request->has('attended') ? $request->boolean('attended') : true;
        $data['overtime_hours'] = $data['overtime_hours'] ?? 0;

        if ($data['attended']) {
            $data['absence_reason'] = null;
        } elseif (blank($data['absence_reason'] ?? null)) {
            throw ValidationException::withMessages([
                'absence_reason' => 'Selecciona el motivo de la inasistencia.',
            ]);
        } elseif (! $request->hasFile('absence_document') && ! $attendance?->absence_document_path) {
            throw ValidationException::withMessages([
                'absence_document' => 'Adjunta un comprobante para registrar la inasistencia.',
            ]);
        }

        unset($data['absence_document']);

        return $data;
    }

    private function storeAbsenceDocument(Request $request, int $workerId): string
    {
        $path = $request->file('absence_document')->store("worker-attendance-documents/{$workerId}", 's3');

        if (! $path) {
            throw ValidationException::withMessages([
                'absence_document' => 'No se pudo almacenar el comprobante de la inasistencia.',
            ]);
        }

        return $path;
    }

    private function absenceReasonLabel(?string $reason): string
    {
        return self::ABSENCE_REASONS[$reason] ?? 'No show';
    }

    private function ensureNoDuplicate(array $data, ?WorkerAttendance $attendance = null): void
    {
        $duplicate = WorkerAttendance::query()
            ->where('worker_id', $data['worker_id'])
            ->whereDate('date', $data['date'])
            ->when($attendance, fn ($query) => $query->whereKeyNot($attendance->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'date' => 'Ya existe una asistencia registrada para este trabajador en esa fecha.',
            ]);
        }
    }

    private function ensureActiveMembership(array $data): void
    {
        $isMember = WorkerGroupMember::query()
            ->where('worker_id', $data['worker_id'])
            ->where('worker_group_id', $data['worker_group_id'])
            ->whereDate('joined_at', '<=', $data['date'])
            ->where(function ($query) use ($data) {
                $query->whereNull('left_at')->orWhereDate('left_at', '>=', $data['date']);
            })
            ->exists();

        if (! $isMember) {
            throw ValidationException::withMessages([
                'worker_group_id' => 'El trabajador no pertenecía a la cuadrilla en la fecha indicada.',
            ]);
        }
    }

    private function ensureUnlocked(WorkerAttendance $attendance): void
    {
        if ($attendance->payroll_line_id !== null) {
            throw ValidationException::withMessages([
                'attendance' => 'La asistencia ya forma parte de una nómina y no puede modificarse desde este módulo.',
            ]);
        }
    }

    private function notify(WorkerAttendance $attendance, string $action, string $data): void
    {
        $this->notification->send([
            'type' => 'WorkerAttendance',
            'action_by' => Auth::id(),
            'model_action' => $action,
            'model_id' => $attendance->id,
            'data' => $data,
        ]);
    }
}
