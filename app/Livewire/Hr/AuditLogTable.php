<?php

namespace App\Livewire\Hr;

use App\Models\HrAuditLog;
use App\Services\Hr\HrPermissions;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogTable extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url(as: 'trabajador')]
    public string $worker = '';

    #[Url(as: 'entidad')]
    public string $entity = '';

    #[Url(as: 'desde')]
    public string $date_from = '';

    #[Url(as: 'hasta')]
    public string $date_to = '';

    public function mount(): void
    {
        abort_unless(HrPermissions::can('audit'), 403);
    }

    public function updatingWorker(): void
    {
        $this->resetPage();
    }

    public function updatingEntity(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['worker', 'entity', 'date_from', 'date_to']);
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(HrPermissions::can('audit'), 403);

        $worker = trim($this->worker);

        $logs = HrAuditLog::query()
            ->visibleTo(auth()->user())
            ->with(['user', 'worker'])
            ->when($worker !== '', fn ($query) => $query->whereHas('worker', fn ($workerQuery) => $workerQuery
                ->where('first_name', 'like', "%{$worker}%")
                ->orWhere('last_name', 'like', "%{$worker}%")
                ->orWhere('nss', 'like', "%{$worker}%")
                ->orWhere('employee_code', 'like', "%{$worker}%")))
            ->when($this->entity !== '', fn ($query) => $query->where('entity', $this->entity))
            ->when($this->date_from !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->date_from))
            ->when($this->date_to !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->date_to))
            ->latest()
            ->latest('id')
            ->paginate(25);

        $entities = HrAuditLog::query()
            ->visibleTo(auth()->user())
            ->select('entity')
            ->distinct()
            ->orderBy('entity')
            ->pluck('entity');

        return view('livewire.hr.audit-log-table', compact('logs', 'entities'));
    }
}
