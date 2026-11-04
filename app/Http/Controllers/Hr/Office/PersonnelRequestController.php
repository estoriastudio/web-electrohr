<?php

namespace App\Http\Controllers\Hr\Office;

use App\Http\Controllers\Controller;
use App\Models\PersonnelRequestLine;
use App\Models\ProjectWork;
use App\Models\Worker;
use App\Services\Hr\AssignmentService;
use App\Services\Hr\HrFieldScope;
use App\Services\Hr\HrPermissions;
use App\Services\NotificationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PersonnelRequestController extends Controller
{
    public function __construct(
        private AssignmentService $assignments,
        private NotificationService $notification,
    ) {}

    /** Bandeja de RH (Livewire): Pendientes, Conflictos, Altas en obra e Historial. */
    public function index(): View
    {
        $this->authorizeInbox();

        return view('hr.office.assignments.index');
    }

    /** Incongruencias calculadas: Activo sin asignación y Solicitado por más de una Obra. */
    public function inconsistencies(): View
    {
        $this->authorizeInbox();

        $data = $this->assignments->inconsistencies();
        $projectWorks = ProjectWork::with('project')->orderBy('name')->get();

        return view('hr.office.assignments.inconsistencies', $data + [
            'projectWorks' => $projectWorks,
            'minimumDates' => $data['unassigned']->mapWithKeys(fn (Worker $worker) => [$worker->id => $this->assignments->minimumDate($worker)->toDateString()]),
        ]);
    }

    /** Asignar: asignación directa con fecha efectiva y motivo (queda en bitácora). */
    public function assign(Request $request): RedirectResponse
    {
        $this->authorizeInbox();

        $data = $request->validate([
            'worker_id' => 'required|exists:workers,id',
            'project_work_id' => 'required|exists:project_works,id',
            'effective_date' => 'required|date',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->assignments->directAssign(
                Worker::findOrFail($data['worker_id']),
                ProjectWork::findOrFail($data['project_work_id']),
                $data['effective_date'],
                Auth::user(),
                $data['reason'],
            );
        } catch (DomainException $exception) {
            return redirect()->route('rh.office.assignments.inconsistencies')->with('error', $exception->getMessage());
        }

        return redirect()->route('rh.office.assignments.inconsistencies')->with('success', 'Asignación registrada correctamente.');
    }

    /** Asignar aquí: resuelve el conflicto a favor de la Obra de la solicitud. */
    public function assignHere(PersonnelRequestLine $line): RedirectResponse
    {
        $this->authorizeInbox();

        if ($line->status !== 'pending') {
            return redirect()->route('rh.office.assignments.inconsistencies')->with('info', 'La línea ya estaba resuelta.');
        }

        $line->load(['worker', 'personnelRequest.projectWork']);

        try {
            $message = $this->assignments->resolveConflict($line, 'here', Auth::user());
        } catch (DomainException $exception) {
            return redirect()->route('rh.office.assignments.inconsistencies')->with('error', $exception->getMessage());
        }

        $this->assignments->notifyRequester($line->personnelRequest, Auth::user(), "{$line->worker->first_name} {$line->worker->last_name}: {$message}");
        $this->notification->send([
            'type' => 'PersonnelRequest',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $line->personnel_request_id,
            'data' => "resolvió el conflicto de {$line->worker->first_name} {$line->worker->last_name} a favor de {$line->personnelRequest->projectWork->name}.",
        ]);

        return redirect()->route('rh.office.assignments.inconsistencies')->with('success', $message);
    }

    private function authorizeInbox(): void
    {
        abort_unless(HrFieldScope::isUnrestricted() && HrPermissions::can('hrInbox'), 403);
    }
}
