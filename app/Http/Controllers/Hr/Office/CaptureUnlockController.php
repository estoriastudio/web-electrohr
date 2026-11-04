<?php

namespace App\Http\Controllers\Hr\Office;

use App\Http\Controllers\Controller;
use App\Models\CaptureUnlock;
use App\Models\ProjectWork;
use App\Models\WorkAssignment;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\HrAudit;
use App\Services\Hr\HrClock;
use App\Services\Hr\HrPermissions;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CaptureUnlockController extends Controller
{
    public function __construct(
        private NotificationService $notification,
        private AttendanceService $attendance,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeUnlock();

        $unlocks = CaptureUnlock::query()
            ->usable()
            ->with(['projectWork.project', 'grantedByUser'])
            ->orderBy('expires_at')
            ->paginate(25);

        $projectWorks = ProjectWork::with('project')->orderBy('name')->get();

        return view('hr.office.unlocks.index', [
            'unlocks' => $unlocks,
            'projectWorks' => $projectWorks,
            'unreleasedDays' => $this->unreleasedDays(),
            'prefillWork' => $request->integer('obra') ?: null,
            'prefillDate' => $request->input('fecha'),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('rh.office.unlocks.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeUnlock();

        $data = $request->validate([
            'project_work_id' => 'required|exists:project_works,id',
            'date' => 'required|date|before:today',
            'reason' => 'required|string|max:500',
        ]);

        $unlock = CaptureUnlock::create($data + [
            'granted_by' => Auth::id(),
            'expires_at' => now()->addDay(),
            'active' => true,
        ]);
        $work = $unlock->projectWork;

        HrAudit::log('Habilitación de captura 24 h', 'Habilitación', $unlock->id, null, null, "{$work->name} · {$unlock->date->format('d/m/Y')}", $data['reason']);

        $this->notification->send([
            'type' => 'CaptureUnlock',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $unlock->id,
            'data' => "habilitó la captura de {$work->name} del {$unlock->date->format('d/m/Y')} por 24 h.",
        ]);

        return redirect()->route('rh.office.unlocks.index')->with('success', 'Captura habilitada por 24 horas.');
    }

    /** Retirar: la habilitación deja de estar activa. */
    public function destroy(CaptureUnlock $unlock): RedirectResponse
    {
        $this->authorizeUnlock();

        $unlock->update(['active' => false]);

        HrAudit::log('Habilitación de captura retirada', 'Habilitación', $unlock->id, null, 'Activa', 'Retirada');

        $this->notification->send([
            'type' => 'CaptureUnlock',
            'action_by' => Auth::id(),
            'model_action' => 'destroy',
            'model_id' => $unlock->id,
            'data' => "retiró la habilitación de captura de {$unlock->projectWork->name} del {$unlock->date->format('d/m/Y')}.",
        ]);

        return redirect()->route('rh.office.unlocks.index')->with('success', 'Habilitación retirada.');
    }

    /** Días del periodo en curso (hasta ayer) en que una Obra con padrón no liberó su captura. */
    private function unreleasedDays(): array
    {
        $days = [];

        for ($day = HrClock::periodOf(); $day->lessThan(HrClock::today()); $day = $day->addDay()) {
            $date = $day->toDateString();
            $workIds = WorkAssignment::query()->onDate($date)->distinct()->pluck('project_work_id');

            foreach (ProjectWork::with('project')->whereIn('id', $workIds)->orderBy('name')->get() as $work) {
                if (! $this->attendance->isDayClosed($work->id, $date) && ! $this->attendance->activeUnlock($work->id, $date)) {
                    $days[] = ['work' => $work, 'date' => $day];
                }
            }
        }

        return $days;
    }

    private function authorizeUnlock(): void
    {
        abort_unless(HrPermissions::can('grantUnlock'), 403);
    }
}
