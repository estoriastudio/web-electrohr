<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
            <div>
                <h4 class="card-title mb-0">Bitácora de Recursos Humanos</h4>
                <span class="text-muted fs-12">Quién, cuándo y por qué cambió cada dato del proceso de asistencia y nómina.</span>
            </div>
        </div>

        <div class="card-body border-bottom">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="audit_worker">Trabajador</label>
                    <input id="audit_worker" type="text" class="form-control form-control-sm"
                           wire:model.live.debounce.300ms="worker" placeholder="Nombre, NSS o código">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="audit_entity">Entidad</label>
                    <select id="audit_entity" class="form-select form-select-sm" wire:model.live="entity">
                        <option value="">Todas</option>
                        @foreach ($entities as $entityName)
                            <option value="{{ $entityName }}">{{ $entityName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="audit_from">Desde</label>
                    <input id="audit_from" type="date" class="form-control form-control-sm" wire:model.live="date_from">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="audit_to">Hasta</label>
                    <input id="audit_to" type="date" class="form-control form-control-sm" wire:model.live="date_to">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="button" class="btn btn-sm btn-light" wire:click="clearFilters" title="Limpiar filtros">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                    <thead class="bg-light-subtle">
                        <tr>
                            <th>Cuándo</th>
                            <th>Quién</th>
                            <th>Acción</th>
                            <th>Entidad</th>
                            <th>Trabajador</th>
                            <th>Antes</th>
                            <th>Después</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr wire:key="audit-{{ $log->id }}">
                                <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    {{ $log->user?->name ?? 'Sistema' }}
                                    <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $log->role }}</span>
                                </td>
                                <td>
                                    {{ $log->action }}
                                    @if ($log->sensitive)
                                        <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Sensible</span>
                                    @endif
                                </td>
                                <td>{{ $log->entity }}@if ($log->entity_id) <span class="text-muted">#{{ $log->entity_id }}</span>@endif</td>
                                <td>{{ $log->worker ? $log->worker->first_name.' '.$log->worker->last_name : '—' }}</td>
                                <td class="text-wrap" style="max-width: 220px;">{{ $log->before ?? '—' }}</td>
                                <td class="text-wrap" style="max-width: 220px;">{{ $log->after ?? '—' }}</td>
                                <td class="text-wrap" style="max-width: 220px;">{{ $log->reason ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No hay registros en la bitácora.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($logs->hasPages())
            <div class="card-footer border-top">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
