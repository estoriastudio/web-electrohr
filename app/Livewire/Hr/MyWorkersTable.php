<?php

namespace App\Livewire\Hr;

use App\Models\Worker;
use App\Models\WorkerGroup;
use App\Services\Hr\HrFieldScope;
use App\Services\Hr\HrPermissions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Mis trabajadores (HR-011): personas con asignación vigente en las Obras del usuario de campo. */
class MyWorkersTable extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'cuadrilla')]
    public string $group = '';

    #[Url(as: 'obra')]
    public string $project_work_id = '';

    #[Url(as: 'estado')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorizeField();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingGroup(): void
    {
        $this->resetPage();
    }

    public function updatingProjectWorkId(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->authorizeField();

        $user = Auth::user();
        $this->validate(['search' => 'nullable|string|max:100']);

        $search = trim($this->search);
        $works = HrFieldScope::works($user)->with('project')->orderBy('name')->get();

        $workers = Worker::query()
            ->select(['id', 'employee_code', 'first_name', 'last_name', 'nss', 'status', 'position_category_id', 'worker_group_id', 'file_incomplete'])
            ->with(['positionCategory', 'workerGroup', 'currentAssignment.projectWork.project'])
            ->inFieldScope($user)
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nss', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")))
            ->when($this->group !== '', fn ($query) => $query->where('worker_group_id', $this->group))
            ->when(in_array($this->status, ['active', 'pre_registered', 'terminated'], true), fn ($query) => $query->where('status', $this->status))
            ->when($this->project_work_id !== '' && $works->contains('id', (int) $this->project_work_id), fn ($query) => $query
                ->whereHas('assignments', fn ($assignment) => $assignment->current()->where('project_work_id', $this->project_work_id)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25);

        return view('livewire.hr.my-workers-table', [
            'workers' => $workers,
            'works' => $works,
            'groups' => WorkerGroup::orderBy('name')->get(),
        ]);
    }

    private function authorizeField(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole([HrPermissions::ROLE_SUPERVISOR, HrPermissions::ROLE_MANAGER, HrPermissions::ROLE_ADMIN]),
            403
        );
    }
}
