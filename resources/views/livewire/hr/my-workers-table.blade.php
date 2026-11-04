<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
            <div>
                <h4 class="card-title mb-0">Mis trabajadores</h4>
                <span class="text-muted fs-12">Personas con asignación vigente en tus Obras (y bajas de los últimos 7 días).</span>
            </div>
        </div>

        <div class="card-body border-bottom">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" maxlength="100" class="form-control form-control-sm @error('search') is-invalid @enderror"
                           wire:model.live.debounce.300ms="search" placeholder="Nombre, NSS o número de empleado">
                    @error('search')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" wire:model.live="project_work_id">
                        <option value="">Todas mis Obras</option>
                        @foreach ($works as $work)
                            <option value="{{ $work->id }}">{{ $work->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" wire:model.live="group">
                        <option value="">Todas las cuadrillas</option>
                        @foreach ($groups as $workerGroup)
                            <option value="{{ $workerGroup->id }}">{{ $workerGroup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" wire:model.live="status">
                        <option value="">Todos los estados</option>
                        <option value="active">Activos</option>
                        <option value="pre_registered">Pre-registro</option>
                        <option value="terminated">Baja reciente</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                    <thead class="bg-light-subtle">
                        <tr>
                            <th>Persona</th>
                            <th>Cuadrilla</th>
                            <th>Cargo</th>
                            <th>Obra actual</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workers as $worker)
                            @php $currentWork = $worker->currentAssignment?->projectWork; @endphp
                            <tr wire:key="my-worker-{{ $worker->id }}">
                                <td>
                                    <a href="{{ route('rh.field.workers.show', $worker->id) }}" class="text-dark fw-medium">{{ $worker->last_name }}, {{ $worker->first_name }}</a>
                                    <div class="text-muted fs-12">NSS {{ $worker->nss }}@if ($worker->employee_code) · No. {{ $worker->employee_code }}@endif</div>
                                </td>
                                <td>{{ $worker->workerGroup?->name ?? '—' }}</td>
                                <td>{{ $worker->positionCategory?->name ?? '—' }}</td>
                                <td>
                                    @if ($currentWork)
                                        {{ $currentWork->name }}<div class="text-muted fs-12">{{ $currentWork->project?->name }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($worker->status === 'terminated')
                                        <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Baja reciente</span>
                                    @elseif ($worker->status === 'pre_registered')
                                        <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Pre-registro</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activo</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('rh.field.workers.show', $worker->id) }}" class="btn btn-light btn-sm"><i class="ri-eye-line align-middle fs-18"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No hay personas asignadas a tus Obras con esos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($workers->hasPages())
            <div class="card-footer border-top">{{ $workers->links() }}</div>
        @endif
    </div>
</div>
