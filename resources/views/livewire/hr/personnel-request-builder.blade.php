<div>
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h4 class="card-title mb-0">Personal</h4>
                        <span class="text-muted fs-12">Elige personas (incluso de otro Proyecto); RH valida y reasigna.</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#registerWorkerModal">
                        <i class="ri-user-add-line me-1"></i> Dar de alta en obra
                    </button>
                </div>

                <div class="card-body border-bottom">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Nombre o NSS">
                        </div>
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" wire:model.live="group">
                                <option value="">Todas las cuadrillas</option>
                                @foreach ($groups as $workerGroup)
                                    <option value="{{ $workerGroup->id }}">{{ $workerGroup->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" wire:model.live="position">
                                <option value="">Todos los cargos</option>
                                @foreach ($positions as $positionCategory)
                                    <option value="{{ $positionCategory->id }}">{{ $positionCategory->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                            <thead class="bg-light-subtle">
                                <tr>
                                    <th style="width: 20px;"></th>
                                    <th>Persona</th>
                                    <th>Cuadrilla</th>
                                    <th>Cargo</th>
                                    <th>Obra actual</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($workers as $worker)
                                    @php $currentWork = $worker->currentAssignment?->projectWork; @endphp
                                    <tr wire:key="worker-{{ $worker->id }}">
                                        <td>
                                            <input type="checkbox" class="form-check-input" value="{{ $worker->id }}" wire:model.live="selected"
                                                   @disabled($currentWork && $work && $currentWork->id === $work->id)>
                                        </td>
                                        <td>
                                            <span class="fw-medium">{{ $worker->last_name }}, {{ $worker->first_name }}</span>
                                            <div class="text-muted fs-12">NSS {{ $worker->nss }}
                                                @if ($worker->status === 'pre_registered')
                                                    <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Pre-registro</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>{{ $worker->workerGroup?->name ?? '—' }}</td>
                                        <td>{{ $worker->positionCategory?->name ?? '—' }}</td>
                                        <td>
                                            @if ($currentWork)
                                                {{ $currentWork->name }}
                                                <div class="text-muted fs-12">{{ $currentWork->project?->name }}</div>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Sin asignación</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">No hay personal con esos filtros.</td></tr>
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

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header border-bottom">
                    <h4 class="card-title mb-0">Solicitud por Obra</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="pr_work">Obra <span class="text-danger">*</span></label>
                        <select id="pr_work" class="form-select @error('project_work_id') is-invalid @enderror" wire:model.live="project_work_id">
                            @foreach ($works as $scopedWork)
                                <option value="{{ $scopedWork->id }}">{{ $scopedWork->name }} — {{ $scopedWork->project?->name }}</option>
                            @endforeach
                        </select>
                        @error('project_work_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="pr_start">A partir de</label>
                        <input id="pr_start" type="date" class="form-control @error('start_date') is-invalid @enderror" wire:model="start_date" min="{{ $minimumDate }}" max="{{ now()->toDateString() }}">
                        @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="text-muted fs-12 mt-1">Vacío = hoy. RH aplica la fecha mínima de cada persona.</div>
                    </div>

                    <p class="mb-2"><strong>{{ count($selected) }}</strong> persona(s) elegida(s)</p>
                    @error('selected')<div class="text-danger fs-12 mb-2">{{ $message }}</div>@enderror

                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" wire:click="send" wire:loading.attr="disabled" @disabled(! $work)>
                            <span wire:loading wire:target="send" class="spinner-border spinner-border-sm me-1"></span>
                            <i class="ri-send-plane-line me-1"></i> Enviar a RH
                        </button>
                        <button type="button" class="btn btn-light" wire:click="clearSelection" @disabled(count($selected) === 0)>Limpiar selección</button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header border-bottom">
                    <h4 class="card-title mb-0">Mis solicitudes</h4>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse ($requests as $request)
                            @php
                                $pending = $request->lines->where('status', 'pending')->count();
                                $approved = $request->lines->where('status', 'approved')->count();
                                $rejected = $request->lines->where('status', 'rejected')->count();
                            @endphp
                            <div class="list-group-item" wire:key="request-{{ $request->id }}">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-medium">{{ $request->projectWork?->name }}</span>
                                    @if ($request->status === 'closed')
                                        <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Cerrada</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Enviada a RH</span>
                                    @endif
                                </div>
                                <div class="text-muted fs-12">
                                    {{ $request->sent_at?->format('d/m/Y H:i') }} ·
                                    {{ $pending }} pendiente(s), {{ $approved }} aprobada(s), {{ $rejected }} rechazada(s)
                                </div>
                                @foreach ($request->lines->where('status', 'rejected') as $line)
                                    <div class="text-danger fs-12">{{ $line->worker?->first_name }} {{ $line->worker?->last_name }}: {{ $line->note }}</div>
                                @endforeach
                            </div>
                        @empty
                            <div class="list-group-item text-muted text-center py-4">Aún no has enviado solicitudes.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="registerWorkerModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dar de alta en obra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Se crea un pre-registro con asignación provisional a {{ $work?->name ?? 'la Obra elegida' }}. RH tiene 7 días para completar el expediente.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="reg_first_name">Nombre(s) <span class="text-danger">*</span></label>
                            <input id="reg_first_name" type="text" class="form-control @error('reg_first_name') is-invalid @enderror" wire:model="reg_first_name">
                            @error('reg_first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="reg_last_name">Apellidos <span class="text-danger">*</span></label>
                            <input id="reg_last_name" type="text" class="form-control @error('reg_last_name') is-invalid @enderror" wire:model="reg_last_name">
                            @error('reg_last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="reg_nss">NSS (11 dígitos) <span class="text-danger">*</span></label>
                            <input id="reg_nss" type="text" inputmode="numeric" maxlength="11" class="form-control @error('reg_nss') is-invalid @enderror" wire:model="reg_nss">
                            @error('reg_nss')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="reg_curp">CURP (18 caracteres) <span class="text-danger">*</span></label>
                            <input id="reg_curp" type="text" maxlength="18" class="form-control text-uppercase @error('reg_curp') is-invalid @enderror" wire:model="reg_curp">
                            @error('reg_curp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="register" wire:loading.attr="disabled">
                        <span wire:loading wire:target="register" class="spinner-border spinner-border-sm me-1"></span>
                        Dar de alta
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
