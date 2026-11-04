<?php

namespace App\Livewire\Hr;

use App\Models\LateOvertimeRequest;
use App\Models\ProjectWork;
use App\Services\Hr\HrFieldScope;
use App\Services\Hr\HrPermissions;
use App\Services\Hr\OvertimeService;
use App\Services\NotificationService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Pestaña "HE fuera de plazo" de la bandeja de RH (HR-008). Sin importes. */
class LateOvertimeInbox extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url(as: 'estado')]
    public string $status = 'pending';

    #[Url(as: 'obra')]
    public string $project_work_id = '';

    #[Url(as: 'fecha')]
    public string $date = '';

    #[Url(as: 'q')]
    public string $search = '';

    /** @var array<int, int|string> */
    public array $selected = [];

    public string $note = '';

    public string $decision = '';

    public function mount(): void
    {
        $this->authorizeInbox();

        if ($this->status !== '' && ! array_key_exists($this->status, LateOvertimeRequest::STATUSES)) {
            $this->status = 'pending';
        }
    }

    public function updatingStatus(): void
    {
        $this->selected = [];
        $this->resetPage();
    }

    public function updatingProjectWorkId(): void
    {
        $this->resetPage();
    }

    public function updatingDate(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openDecision(string $decision): void
    {
        $this->authorizeInbox();

        if (count($this->selected) === 0) {
            $this->dispatch('flash', type: 'error', message: 'Selecciona al menos una solicitud.');

            return;
        }

        $this->decision = $decision === 'approve' ? 'approve' : 'reject';
        $this->note = '';
        $this->dispatch('modal', id: 'lateOvertimeDecisionModal', action: 'show');
    }

    public function decide(OvertimeService $overtime, NotificationService $notification): void
    {
        $this->authorizeInbox();
        $this->validate([
            'selected' => 'required|array|min:1',
            'selected.*' => 'exists:late_overtime_requests,id',
            'note' => 'nullable|string|max:500',
        ]);

        $approve = $this->decision === 'approve';
        $applied = 0;
        $failures = [];
        $byRequester = [];

        foreach (LateOvertimeRequest::with(['worker', 'projectWork', 'requestedByUser'])->whereIn('id', $this->selected)->get() as $request) {
            $label = "{$request->worker?->first_name} {$request->worker?->last_name} ({$request->date->format('d/m')})";

            try {
                $approve
                    ? $overtime->approve($request, Auth::user(), $this->note ?: null)
                    : $overtime->reject($request, Auth::user(), $this->note ?: null);
                $applied++;
                $byRequester[$request->requested_by][] = $label;

                $notification->send([
                    'type' => 'LateOvertimeRequest',
                    'action_by' => Auth::id(),
                    'model_action' => 'update',
                    'model_id' => $request->id,
                    'data' => ($approve ? 'aprobó' : 'rechazó')." las {$request->hours} h extra fuera de plazo de {$label}.",
                ]);
            } catch (DomainException $exception) {
                $failures[] = "{$label}: {$exception->getMessage()}";
            }
        }

        foreach ($byRequester as $requesterId => $labels) {
            $notification->sendToRecipients([
                'type' => 'LateOvertimeRequest',
                'action_by' => Auth::id(),
                'model_action' => 'update',
                'model_id' => 0,
                'data' => ($approve ? 'aprobó' : 'rechazó').' tus HE fuera de plazo: '.implode(', ', $labels).'.',
            ], [$requesterId]);
        }

        $this->selected = [];
        $this->decision = '';
        $this->dispatch('modal', id: 'lateOvertimeDecisionModal', action: 'hide');

        $summary = "{$applied} ".($approve ? 'aprobada(s)' : 'rechazada(s)').'.'.($failures ? ' No se pudieron resolver: '.implode(' · ', $failures) : '');
        $this->dispatch('flash', type: $failures ? ($applied ? 'info' : 'error') : 'success', message: $summary);
    }

    public function render()
    {
        $this->authorizeInbox();

        $search = trim($this->search);

        $requests = LateOvertimeRequest::query()
            ->with(['worker', 'projectWork.project', 'requestedByUser', 'resolvedByUser'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->project_work_id !== '', fn ($query) => $query->where('project_work_id', $this->project_work_id))
            ->when($this->date !== '', fn ($query) => $query->whereDate('date', $this->date))
            ->when($search !== '', fn ($query) => $query->whereHas('worker', fn ($worker) => $worker
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nss', 'like', "%{$search}%")))
            ->latest('date')
            ->latest('id')
            ->paginate(25);

        return view('livewire.hr.late-overtime-inbox', [
            'requests' => $requests,
            'works' => ProjectWork::orderBy('name')->get(),
            'statuses' => LateOvertimeRequest::STATUSES,
        ]);
    }

    private function authorizeInbox(): void
    {
        abort_unless(HrFieldScope::isUnrestricted() && HrPermissions::can('hrInbox'), 403);
    }
}
