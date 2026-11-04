<div>
    @include('hr.office.partials.inbox-tabs', ['active' => 'assignments'])

    <ul class="nav nav-tabs mb-3">
        @foreach (['pendientes' => ['ri-time-line', 'Pendientes'], 'conflictos' => ['ri-error-warning-line', 'Conflictos'], 'altas' => ['ri-user-add-line', 'Altas en obra'], 'historial' => ['ri-history-line', 'Historial']] as $key => [$icon, $label])
            <li class="nav-item">
                <button type="button" class="nav-link {{ $tab === $key ? 'active' : '' }}" wire:click="setTab('{{ $key }}')">
                    <i class="{{ $icon }} me-1"></i> {{ $label }}
                </button>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="card-body border-bottom">
            <div class="row g-2">
                <div class="col-md-2">
                    <select class="form-select form-select-sm" wire:model.live="project_id">
                        <option value="">Todos los Proyectos</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" wire:model.live="project_work_id">
                        <option value="">Todas las Obras</option>
                        @foreach ($works as $work)
                            <option value="{{ $work->id }}">{{ $work->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" wire:model.live="group">
                        <option value="">Todas las cuadrillas</option>
                        @foreach ($groups as $workerGroup)
                            <option value="{{ $workerGroup->id }}">{{ $workerGroup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" wire:model.live="position">
                        <option value="">Todos los cargos</option>
                        @foreach ($positions as $positionCategory)
                            <option value="{{ $positionCategory->id }}">{{ $positionCategory->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Nombre o NSS">
                </div>
            </div>
        </div>

        @if ($tab === 'pendientes')
            <div class="card-body p-0">
                @forelse ($requests as $request)
                    <div class="border-bottom p-3" wire:key="request-{{ $request->id }}">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div>
                                <span class="fw-semibold">{{ $request->projectWork?->name }}</span>
                                <span class="text-muted">· {{ $request->projectWork?->project?->name }}</span>
                                <div class="text-muted fs-12">
                                    Pidió {{ $request->requestedByUser?->name }} · {{ $request->sent_at?->format('d/m/Y H:i') }}
                                    · A partir de {{ $request->start_date?->format('d/m/Y') ?? 'hoy' }}
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-success" wire:click="approveRequest({{ $request->id }})"
                                        wire:confirm="¿Dar visto bueno a las líneas sin conflicto de esta solicitud?">
                                    <i class="ri-check-double-line me-1"></i> Visto bueno
                                </button>
                                <button type="button" class="btn btn-sm btn-soft-danger" wire:click="openReason('reject_request', {{ $request->id }})">
                                    <i class="ri-close-line me-1"></i> Rechazar
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                                <thead class="bg-light-subtle">
                                    <tr><th>Persona</th><th>Cuadrilla</th><th>Cargo</th><th>Situación</th><th>Acción</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($request->lines as $line)
                                        <tr wire:key="line-{{ $line->id }}">
                                            <td>{{ $line->worker?->last_name }}, {{ $line->worker?->first_name }}<div class="text-muted fs-12">NSS {{ $line->worker?->nss }}</div></td>
                                            <td>{{ $line->worker?->workerGroup?->name ?? '—' }}</td>
                                            <td>{{ $line->worker?->positionCategory?->name ?? '—' }}</td>
                                            <td>
                                                @if ($line->conflict)
                                                    <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Conflicto</span>
                                                    <span class="text-muted fs-12">{{ $line->conflict }}</span>
                                                @else
                                                    <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Sin conflicto</span>
                                                @endif
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-soft-danger btn-sm" wire:click="openReason('reject_line', {{ $line->id }})" title="Rechazar">
                                                    <i class="ri-close-line align-middle fs-18"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No hay solicitudes pendientes.</div>
                @endforelse
            </div>
            @if ($requests->hasPages())<div class="card-footer border-top">{{ $requests->links() }}</div>@endif
        @endif

        @if ($tab === 'conflictos')
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr><th>Persona</th><th>Pide</th><th>Conflicto</th><th>Resolver</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($conflicts as $line)
                                <tr wire:key="conflict-{{ $line->id }}">
                                    <td>{{ $line->worker?->last_name }}, {{ $line->worker?->first_name }}<div class="text-muted fs-12">NSS {{ $line->worker?->nss }}</div></td>
                                    <td>{{ $line->personnelRequest->projectWork?->name }}<div class="text-muted fs-12">{{ $line->personnelRequest->requestedByUser?->name }}</div></td>
                                    <td><span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">{{ $line->conflict }}</span></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-soft-primary" wire:click="resolve({{ $line->id }}, 'here')" wire:confirm="¿Asignar aquí y rechazar las demás solicitudes de la persona?">Asignar aquí</button>
                                            <button type="button" class="btn btn-sm btn-light" wire:click="resolve({{ $line->id }}, 'stay')">Dejar donde está</button>
                                            <button type="button" class="btn btn-sm btn-light" wire:click="resolve({{ $line->id }}, 'other')">Otra Obra…</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No hay conflictos por resolver.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($conflicts->hasPages())<div class="card-footer border-top">{{ $conflicts->links() }}</div>@endif
        @endif

        @if ($tab === 'altas')
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr><th>Persona</th><th>Dio el alta</th><th>Obra</th><th>Plazo del expediente</th><th>Estado</th><th>Acción</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($registrations as $worker)
                                @php $overdue = $worker->profile_complete_deadline && $worker->profile_complete_deadline->lt($today); @endphp
                                <tr wire:key="reg-{{ $worker->id }}">
                                    <td>
                                        <a href="{{ route('human_resources.workers.show', $worker) }}" class="text-dark fw-medium">{{ $worker->last_name }}, {{ $worker->first_name }}</a>
                                        <div class="text-muted fs-12">NSS {{ $worker->nss }}</div>
                                    </td>
                                    <td>{{ $worker->registeredByUser?->name }}<div class="text-muted fs-12">{{ $worker->field_registered_at?->format('d/m/Y') }}</div></td>
                                    <td>{{ $worker->currentAssignment?->projectWork?->name ?? '—' }}</td>
                                    <td class="{{ $overdue ? 'text-danger fw-semibold' : '' }}">
                                        {{ $worker->profile_complete_deadline?->format('d/m/Y') ?? '—' }}
                                        @if ($overdue)<span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Vencido</span>@endif
                                    </td>
                                    <td>
                                        @if ($worker->status === 'pre_registered')
                                            <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Pre-registro</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Expediente incompleto</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            @if ($worker->status === 'pre_registered')
                                                <button type="button" class="btn btn-sm btn-soft-primary" wire:click="activateWorker({{ $worker->id }})">Activar</button>
                                                <button type="button" class="btn btn-sm btn-light" wire:click="openMerge({{ $worker->id }})">Fusionar duplicado</button>
                                            @endif
                                            <a href="{{ route('human_resources.workers.file.show', $worker) }}" class="btn btn-sm btn-light">Expediente</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No hay altas en obra por revisar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($registrations->hasPages())<div class="card-footer border-top">{{ $registrations->links() }}</div>@endif
        @endif

        @if ($tab === 'historial')
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr><th>Persona</th><th>Obra</th><th>Desde</th><th>Hasta</th><th>Estado</th><th>Acción</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($history as $assignment)
                                <tr wire:key="assignment-{{ $assignment->id }}">
                                    <td>{{ $assignment->worker?->last_name }}, {{ $assignment->worker?->first_name }}<div class="text-muted fs-12">{{ $assignment->worker?->workerGroup?->name }}</div></td>
                                    <td>{{ $assignment->projectWork?->name }}<div class="text-muted fs-12">{{ $assignment->projectWork?->project?->name }}</div></td>
                                    <td>{{ $assignment->start_date->format('d/m/Y') }}</td>
                                    <td>{{ $assignment->end_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td>
                                        @if ($assignment->voided_at)
                                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12" title="{{ $assignment->void_reason }}">Anulada</span>
                                        @elseif ($assignment->end_date === null)
                                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Vigente</span>
                                            @if ($assignment->provisional)<span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Provisional</span>@endif
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">{{ $assignment->closed_reason ?? 'Cerrada' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @unless ($assignment->voided_at)
                                            <div class="d-flex gap-2">
                                                @if ($assignment->end_date === null)
                                                    <button type="button" class="btn btn-sm btn-soft-primary" wire:click="openReassign({{ $assignment->id }})">Reasignar…</button>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-soft-danger" wire:click="openReason('void', {{ $assignment->id }})">Anular</button>
                                            </div>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No hay asignaciones en el historial.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($history->hasPages())<div class="card-footer border-top">{{ $history->links() }}</div>@endif
        @endif
    </div>

    <div class="modal fade" id="inboxReasonModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Motivo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($reasonAction === 'void')
                        <p class="text-muted">Se eliminarán los registros de asistencia capturados con esta asignación y la persona quedará Sin asignación.</p>
                    @elseif ($reasonAction === 'activate')
                        <p class="text-muted">El expediente no está completo: la persona quedará Activa con la etiqueta "Expediente incompleto".</p>
                    @endif
                    <label class="form-label" for="inbox_reason">Motivo <span class="text-danger">*</span></label>
                    <textarea id="inbox_reason" rows="3" class="form-control @error('reason') is-invalid @enderror" wire:model="reason"></textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmReason" wire:loading.attr="disabled">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="inboxOtherWorkModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Asignar a otra Obra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="inbox_other_work">Obra <span class="text-danger">*</span></label>
                    <select id="inbox_other_work" class="form-select @error('otherWorkId') is-invalid @enderror" wire:model="otherWorkId">
                        <option value="">Selecciona una Obra</option>
                        @foreach ($allWorks as $work)
                            <option value="{{ $work->id }}">{{ $work->name }} — {{ $work->project?->name }}</option>
                        @endforeach
                    </select>
                    @error('otherWorkId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="resolveOther">Asignar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="inboxReassignModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reasignar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="reassign_work">Obra <span class="text-danger">*</span></label>
                        <select id="reassign_work" class="form-select @error('reassignWorkId') is-invalid @enderror" wire:model="reassignWorkId">
                            <option value="">Selecciona una Obra</option>
                            @foreach ($allWorks as $work)
                                <option value="{{ $work->id }}">{{ $work->name }} — {{ $work->project?->name }}</option>
                            @endforeach
                        </select>
                        @error('reassignWorkId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reassign_date">Fecha efectiva <span class="text-danger">*</span></label>
                        <input id="reassign_date" type="date" class="form-control @error('reassignDate') is-invalid @enderror" wire:model="reassignDate" max="{{ $today->toDateString() }}">
                        @error('reassignDate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="reassign_reason">Motivo <span class="text-danger">*</span></label>
                        <textarea id="reassign_reason" rows="3" class="form-control @error('reassignReason') is-invalid @enderror" wire:model="reassignReason"></textarea>
                        @error('reassignReason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="reassign">Reasignar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="inboxMergeModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Fusionar duplicado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Busca a la persona correcta: el pre-registro duplicado se fusiona en ella y se da de baja.</p>
                    <input type="text" class="form-control mb-3" wire:model.live.debounce.300ms="mergeSearch" placeholder="Nombre o NSS">
                    <div class="list-group">
                        @foreach ($mergeCandidates as $candidate)
                            <button type="button" class="list-group-item list-group-item-action" wire:key="merge-{{ $candidate->id }}"
                                    wire:click="merge({{ $candidate->id }})" wire:confirm="¿Fusionar el pre-registro en {{ $candidate->first_name }} {{ $candidate->last_name }}?">
                                {{ $candidate->last_name }}, {{ $candidate->first_name }} <span class="text-muted fs-12">NSS {{ $candidate->nss }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
