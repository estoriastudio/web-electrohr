<div>
    @php
        $dateLabel = \Carbon\CarbonImmutable::parse($date)->format('d/m/Y');
    @endphp

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
            <div>
                <h4 class="card-title mb-0">{{ $mode === 'field' ? 'Asistencias' : 'Captura general' }}</h4>
                <span class="text-muted fs-12">
                    @if ($mode === 'field')
                        Padrón de la Obra el {{ $dateLabel }}.
                    @else
                        Cualquier persona con asignación el {{ $dateLabel }}. Un día pasado o bloqueado exige motivo.
                    @endif
                </span>
            </div>
            @if ($mode === 'field' && $currentState)
                <div class="d-flex flex-wrap gap-1">
                    @if ($currentState['closed'])
                        <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Día liberado</span>
                    @elseif ($isToday)
                        <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">Captura abierta</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Solo consulta</span>
                    @endif
                    @if ($currentState['unlock'])
                        <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Habilitado hasta {{ $currentState['unlock']->expires_at->format('d/m H:i') }}</span>
                    @endif
                </div>
            @endif
        </div>

        <div class="card-body border-bottom">
            <div class="row g-2">
                @if ($mode === 'general')
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" wire:model.live="project_id">
                            <option value="">Todos los Proyectos</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-{{ $mode === 'general' ? 2 : 3 }}">
                    <select class="form-select form-select-sm" wire:model.live="project_work_id">
                        @if ($mode === 'general')<option value="">Todas las Obras</option>@endif
                        @foreach ($works as $work)
                            <option value="{{ $work->id }}">{{ $work->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    @if ($mode === 'field')
                        <select class="form-select form-select-sm" wire:model.live="date">
                            @foreach ($dateOptions as $option)
                                <option value="{{ $option }}">
                                    {{ \Carbon\CarbonImmutable::parse($option)->format('d/m') }} —
                                    {{ $option === now()->toDateString() ? 'Hoy (captura)' : ($option === \App\Services\Hr\HrClock::yesterday()->toDateString() ? 'Ayer (consulta)' : 'Habilitado') }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="date" class="form-control form-control-sm" wire:model.live="date" max="{{ now()->toDateString() }}">
                    @endif
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" wire:model.live="group">
                        <option value="">Todas las cuadrillas</option>
                        @foreach ($groups as $workerGroup)
                            <option value="{{ $workerGroup->id }}">{{ $workerGroup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" wire:model.live="position">
                        <option value="">Todos los cargos</option>
                        @foreach ($positions as $positionCategory)
                            <option value="{{ $positionCategory->id }}">{{ $positionCategory->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-{{ $mode === 'general' ? 2 : 3 }}">
                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Nombre o NSS">
                </div>
            </div>
        </div>

        <div class="card-body border-bottom">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted">{{ count($selected) }} seleccionada(s)</span>
                <select class="form-select form-select-sm" style="width: 220px;" wire:model="bulkIncident">
                    <option value="">Aplicar incidencia…</option>
                    @foreach (['attendance', 'absence', 'day_off_a', 'day_off_b', 'day_off_c'] as $key)
                        <option value="{{ $key }}">{{ $incidentCatalog[$key][1] }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-sm btn-primary" wire:click="applyBulk" wire:loading.attr="disabled">
                    <span wire:loading wire:target="applyBulk" class="spinner-border spinner-border-sm me-1"></span>
                    Aplicar en bloque
                </button>

                @if ($mode === 'field')
                    <div class="ms-auto d-flex flex-wrap gap-2">
                        @if ($currentState && $currentState['editable'])
                            <button type="button" class="btn btn-sm btn-success" wire:click="releaseDay" wire:confirm="¿Liberar las incidencias de {{ $dateLabel }}? Cierra la edición del campo.">
                                <i class="ri-lock-line me-1"></i> Liberar incidencias
                            </button>
                        @endif
                        @if ($showOvertime && $currentState && $currentState['otOpen'])
                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="releaseOvertime" wire:confirm="¿Liberar las horas extra de {{ $dateLabel }}?">
                                <i class="ri-time-line me-1"></i> Liberar horas extra
                                <span class="text-muted fs-12">· Hasta {{ $otDeadline->isToday() ? 'hoy' : $otDeadline->format('d/m') }} a las {{ $otDeadline->format('H:i') }}</span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
            @if ($mode === 'field' && $showOvertime && $currentState && ! $currentState['otOpen'])
                <div class="text-muted fs-12 mt-2">
                    <i class="ri-information-line"></i>
                    El plazo de horas extra de {{ $dateLabel }} venció ({{ $otDeadline->format('d/m H:i') }}) o ya se liberó. Usa "HE fuera de plazo…" por persona.
                </div>
            @endif
            @if ($isIncentiveDay && $canSeeLetters)
                <div class="text-muted fs-12 mt-2"><i class="ri-information-line"></i> Día de incentivo: define la letra de cada persona antes de liberar.</div>
            @endif
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                    <thead class="bg-light-subtle">
                        <tr>
                            <th style="width: 20px;">
                                <input type="checkbox" class="form-check-input" wire:model.live="selectAll" title="Seleccionar a todas las personas del filtro">
                            </th>
                            <th>Persona</th>
                            <th>Cuadrilla</th>
                            @if ($mode === 'general')<th>Obra</th>@endif
                            <th>Incidencia</th>
                            @if ($canSeeLetters)<th>Letra</th>@endif
                            <th>Apoyo</th>
                            @if ($showOvertime)<th>HE</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $worker = $row['worker'];
                                $record = $row['record'];
                                $state = $workState[$row['work']->id];
                                $editable = $state['editable'];
                                $incident = $record?->incident;
                            @endphp
                            <tr wire:key="row-{{ $worker->id }}">
                                <td><input type="checkbox" class="form-check-input" value="{{ $worker->id }}" wire:model.live="selected" @disabled(! $editable)></td>
                                <td>
                                    <span class="fw-medium">{{ $worker->last_name }}, {{ $worker->first_name }}</span>
                                    <div class="text-muted fs-12">
                                        {{ $worker->positionCategory?->name }}
                                        @if ($row['assignment']->provisional)<span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Provisional</span>@endif
                                    </div>
                                </td>
                                <td>{{ $worker->workerGroup?->name ?? '—' }}</td>
                                @if ($mode === 'general')<td>{{ $row['work']->name }}</td>@endif
                                <td>
                                    @if ($row['allowed'] === [])
                                        <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Falta bloqueada</span>
                                    @elseif ($editable)
                                        <select class="form-select form-select-sm" style="min-width: 170px;" wire:change="setIncident({{ $worker->id }}, $event.target.value)">
                                            <option value="" @selected(! $incident || $incident === 'pending') disabled>Pendiente</option>
                                            @foreach ($row['allowed'] as $key)
                                                <option value="{{ $key }}" @selected($incident === $key)>{{ $incidentCatalog[$key][0] }} · {{ $incidentCatalog[$key][1] }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($incident)
                                        <span class="badge bg-{{ $incidentCatalog[$incident][2] }}-subtle text-{{ $incidentCatalog[$incident][2] }} py-1 px-2 fs-12">{{ $incidentCatalog[$incident][0] }} · {{ $incidentCatalog[$incident][1] }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Pendiente</span>
                                    @endif
                                </td>
                                @if ($canSeeLetters)
                                    <td>
                                        @if ($isIncentiveDay && $editable)
                                            <select class="form-select form-select-sm" style="width: 90px;" wire:change="setLetter({{ $worker->id }}, $event.target.value)">
                                                <option value="" @selected(! $row['letter']) disabled>—</option>
                                                @foreach (\App\Models\WeeklyIncentiveLetter::CATEGORIES as $letter)
                                                    <option value="{{ $letter }}" @selected($row['letter']?->category === $letter)>{{ $letter === 'NA' ? 'N/A' : $letter }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            {{ $row['letter'] ? ($row['letter']->category === 'NA' ? 'N/A' : $row['letter']->category) : '—' }}
                                        @endif
                                    </td>
                                @endif
                                <td>
                                    @if ($row['support'])
                                        <span class="badge bg-info-subtle text-info py-1 px-2 fs-12">{{ $row['support']->toProjectWork?->name }} · {{ \App\Models\WorkSupport::FRACTIONS[$row['support']->fraction] }}</span>
                                        @if ($editable)
                                            <button type="button" class="btn btn-link btn-sm p-0 text-danger" wire:click="removeSupport({{ $worker->id }})" title="Quitar apoyo"><i class="ri-close-line"></i></button>
                                        @endif
                                    @elseif ($editable && $record && $record->isWorked())
                                        <button type="button" class="btn btn-soft-primary btn-sm" wire:click="openSupport({{ $worker->id }})">Apoyo</button>
                                    @else
                                        —
                                    @endif
                                </td>
                                @if ($showOvertime)
                                    <td>
                                        @if ($mode === 'general' || $state['otOpen'])
                                            <input type="number" min="0" max="{{ $maxHours }}" step="1" class="form-control form-control-sm" style="width: 80px;"
                                                   value="{{ $row['overtime']?->hours }}"
                                                   wire:change="setOvertime({{ $worker->id }}, $event.target.value)"
                                                   wire:key="ot-{{ $worker->id }}-{{ $row['overtime']?->hours }}">
                                        @else
                                            @if ($row['overtime'])
                                                <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $row['overtime']->hours }} h</span>
                                            @endif
                                            @if ($editable || ! $state['otOpen'])
                                                <button type="button" class="btn btn-soft-primary btn-sm" wire:click="openLate({{ $worker->id }})">HE fuera de plazo…</button>
                                            @endif
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No hay personas en el padrón con esos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($assignments->hasPages())
            <div class="card-footer border-top">{{ $assignments->links() }}</div>
        @endif
    </div>

    <div class="modal fade" id="attendanceReasonModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Corrección directa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Un día pasado o bloqueado exige motivo; queda en la bitácora y en el historial de la persona.</p>
                    <label class="form-label" for="attendance_reason">Motivo <span class="text-danger">*</span></label>
                    <textarea id="attendance_reason" rows="3" class="form-control @error('reason') is-invalid @enderror" wire:model="reason"></textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmReason" wire:loading.attr="disabled">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="supportModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Apoyo a otra Obra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="support_to">Obra a la que se carga el costo <span class="text-danger">*</span></label>
                        <select id="support_to" class="form-select @error('supportTo') is-invalid @enderror" wire:model="supportTo">
                            <option value="">Selecciona una Obra</option>
                            @foreach ($allWorks as $work)
                                <option value="{{ $work->id }}">{{ $work->name }} — {{ $work->project?->name }}</option>
                            @endforeach
                        </select>
                        @error('supportTo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <label class="form-label" for="support_fraction">Fracción</label>
                    <select id="support_fraction" class="form-select" wire:model="supportFraction">
                        <option value="half">Medio día</option>
                        <option value="full">Día completo</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="saveSupport">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lateOvertimeModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">HE fuera de plazo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="late_hours">Horas (1 a {{ $maxHours }}) <span class="text-danger">*</span></label>
                        <input id="late_hours" type="number" min="1" max="{{ $maxHours }}" step="1" class="form-control @error('lateHours') is-invalid @enderror" wire:model="lateHours">
                        @error('lateHours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <label class="form-label" for="late_reason">Motivo <span class="text-danger">*</span></label>
                    <textarea id="late_reason" rows="3" class="form-control @error('lateReason') is-invalid @enderror" wire:model="lateReason"></textarea>
                    @error('lateReason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="requestLate" wire:loading.attr="disabled">Enviar a RH</button>
                </div>
            </div>
        </div>
    </div>
</div>
