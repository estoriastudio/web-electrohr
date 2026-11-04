<div>
    @include('hr.office.partials.inbox-tabs', ['active' => 'late-overtime'])

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
            <div>
                <h4 class="card-title mb-0">HE fuera de plazo</h4>
                <span class="text-muted fs-12">Solicitudes del campo, sin importes. Se aprueban o rechazan una o varias a la vez.</span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-success" wire:click="openDecision('approve')"><i class="ri-check-line me-1"></i> Aprobar</button>
                <button type="button" class="btn btn-sm btn-soft-danger" wire:click="openDecision('reject')"><i class="ri-close-line me-1"></i> Rechazar</button>
            </div>
        </div>

        <div class="card-body border-bottom">
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select form-select-sm" wire:model.live="status">
                        <option value="">Todos los estados</option>
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" wire:model.live="project_work_id">
                        <option value="">Todas las Obras</option>
                        @foreach ($works as $work)
                            <option value="{{ $work->id }}">{{ $work->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control form-control-sm" wire:model.live="date">
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Nombre o NSS">
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
                            <th>Obra</th>
                            <th>Fecha</th>
                            <th>Horas</th>
                            <th>Motivo</th>
                            <th>Pidió</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $request)
                            <tr wire:key="late-{{ $request->id }}">
                                <td>
                                    @if ($request->status === 'pending')
                                        <input type="checkbox" class="form-check-input" value="{{ $request->id }}" wire:model.live="selected">
                                    @endif
                                </td>
                                <td>{{ $request->worker?->last_name }}, {{ $request->worker?->first_name }}</td>
                                <td>{{ $request->projectWork?->name }}<div class="text-muted fs-12">{{ $request->projectWork?->project?->name }}</div></td>
                                <td>{{ $request->date->format('d/m/Y') }}</td>
                                <td>{{ $request->hours }} h</td>
                                <td class="text-wrap" style="max-width: 260px;">{{ $request->reason }}</td>
                                <td>{{ $request->requestedByUser?->name }}</td>
                                <td>
                                    @if ($request->status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">Por aprobar</span>
                                    @elseif ($request->status === 'approved')
                                        <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Aprobada</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Rechazada</span>
                                    @endif
                                    @if ($request->note)<div class="text-muted fs-12 text-wrap" style="max-width: 200px;">{{ $request->note }}</div>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No hay solicitudes con esos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($requests->hasPages())<div class="card-footer border-top">{{ $requests->links() }}</div>@endif
    </div>

    <div class="modal fade" id="lateOvertimeDecisionModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $decision === 'approve' ? 'Aprobar' : 'Rechazar' }} {{ count($selected) }} solicitud(es)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="late_note">Nota</label>
                    <textarea id="late_note" rows="3" class="form-control @error('note') is-invalid @enderror" wire:model="note"></textarea>
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn {{ $decision === 'approve' ? 'btn-success' : 'btn-danger' }}" wire:click="decide" wire:loading.attr="disabled">Confirmar</button>
                </div>
            </div>
        </div>
    </div>
</div>
