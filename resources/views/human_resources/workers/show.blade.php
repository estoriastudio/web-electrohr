@extends('layouts.app')

@section('page_title', 'Trabajador')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('human_resources.workers.index') }}">Trabajadores</a></li>
    <li class="breadcrumb-item active">{{ $worker->first_name }} {{ $worker->last_name }}</li>
@endsection

@section('content')
@include('human_resources.partials.flash')
@php($badge = ['pre_registered' => ['Pre-registro', 'bg-warning-subtle text-warning'], 'active' => ['Activo', 'bg-success-subtle text-success'], 'terminated' => ['Baja', 'bg-danger-subtle text-danger']][$worker->status] ?? ['Sin definir', 'bg-secondary-subtle text-secondary'])
<div class="d-flex flex-wrap gap-3 justify-content-between align-items-start mb-3">
    <div class="d-flex align-items-center gap-3">
        <div class="avatar-md bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
            <span class="fs-4 fw-semibold">{{ strtoupper(substr($worker->first_name, 0, 1)) }}{{ strtoupper(substr($worker->last_name, 0, 1)) }}</span>
        </div>
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <div class="card mt-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-0">Incentivos</h5>
                        <span class="text-muted fs-12">Se incluyen automáticamente en la nómina de la semana correspondiente.</span>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createWorkerIncentiveModal"><i class="ri-medal-line me-1"></i>Registrar incentivo</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light-subtle"><tr><th>Fecha</th><th>Concepto</th><th>Tipo</th><th>Estatus</th><th>Notas</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody>
                            @forelse($worker->incentives as $incentive)
                                @php($incentiveLabel = ['overtime' => 'Tiempo extra', 'day_off_exchange' => 'Libranza', 'emergency' => 'Emergencia'][$incentive->category])
                                <tr>
                                    <td>{{ $incentive->incentive_date->format('d/m/Y') }}</td>
                                    <td>{{ $incentiveLabel }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $incentive->rate_type }}</span></td>
                                    <td><span class="badge {{ $incentive->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $incentive->status === 'active' ? 'Activo' : 'Cancelado' }}</span></td>
                                    <td>{{ $incentive->notes ?: '—' }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('human_resources.incentives.destroy', $incentive) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este incentivo?')">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="return_to_worker" value="1">
                                            <button class="btn btn-light btn-sm text-danger" title="Eliminar"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No hay incentivos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal fade" id="createWorkerIncentiveModal" tabindex="-1" aria-labelledby="createWorkerIncentiveModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('human_resources.incentives.store') }}">
                            @csrf
                            <input type="hidden" name="worker_id" value="{{ $worker->id }}">
                            <input type="hidden" name="return_to_worker" value="1">
                            <div class="modal-header">
                                <h5 class="modal-title" id="createWorkerIncentiveModalLabel">Registrar incentivo</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label" for="workerIncentiveCategory">Concepto</label>
                                        <select id="workerIncentiveCategory" class="form-select" name="category" required>
                                            <option value="overtime">Tiempo extra</option>
                                            <option value="day_off_exchange">Libranza</option>
                                            <option value="emergency">Emergencia</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label" for="workerIncentiveRateType">Tipo</label>
                                        <select id="workerIncentiveRateType" class="form-select" name="rate_type" required><option>A</option><option>B</option><option>C</option><option>D</option></select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label" for="workerIncentiveDate">Fecha</label>
                                        <input id="workerIncentiveDate" class="form-control" name="incentive_date" type="date" value="{{ old('incentive_date', now()->format('Y-m-d')) }}" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label" for="workerIncentiveStatus">Estatus</label>
                                        <select id="workerIncentiveStatus" class="form-select" name="status" required><option value="active">Activo</option><option value="cancelled">Cancelado</option></select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="workerIncentiveNotes">Notas</label>
                                        <textarea id="workerIncentiveNotes" class="form-control" name="notes" rows="3">{{ old('notes') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar incentivo</button></div>
                        </form>
                    </div>
                </div>
            </div>
                <h4 class="mb-0">{{ $worker->first_name }} {{ $worker->last_name }}</h4>
                <span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span>
            </div>
            <span class="text-muted">{{ $worker->employee_code ? 'No. de cuenta: ' . $worker->employee_code : 'Sin número de cuenta' }}</span>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('human_resources.workers.file.show', $worker) }}"><i class="ri-folder-line me-1"></i>Expediente</a>
        <a class="btn btn-primary" href="{{ route('human_resources.workers.edit', $worker) }}"><i class="ri-edit-line me-1"></i>Editar</a>
    </div>
</div>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header border-bottom"><h5 class="card-title mb-0"><i class="ri-briefcase-4-line me-2 text-primary"></i>Resumen laboral</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Puesto</span><span class="fw-semibold">{{ $worker->positionCategory?->name ?: 'Sin asignar' }}</span></div>
                    <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Obra actual</span><span class="fw-semibold">{{ $worker->currentProjectWork()?->name ?: 'Sin asignar' }}</span></div>
                    <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Sueldo semanal</span><span class="fw-semibold">${{ number_format((float) $worker->weekly_salary, 2) }}</span></div>
                    <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Fecha de alta</span><span class="fw-semibold">{{ $worker->hire_date?->format('d/m/Y') ?: 'Pendiente' }}</span></div>
                </div>
                <hr class="my-4">
                <h6 class="text-muted text-uppercase fs-12 mb-3">Datos personales y contacto</h6>
                <div class="row g-3">
                    <div class="col-md-6"><span class="text-muted d-block fs-12 mb-1">RFC / CURP</span><span>{{ $worker->rfc ?: '—' }} / {{ $worker->curp ?: '—' }}</span></div>
                    <div class="col-md-6"><span class="text-muted d-block fs-12 mb-1">Fecha de nacimiento</span><span>{{ $worker->birth_date?->format('d/m/Y') ?: '—' }}</span></div>
                    <div class="col-md-6"><span class="text-muted d-block fs-12 mb-1">Contacto de emergencia</span><span>{{ $worker->emergency_contact_name ?: '—' }}</span></div>
                    <div class="col-md-6"><span class="text-muted d-block fs-12 mb-1">Teléfono de emergencia</span><span>{{ $worker->emergency_contact_phone ?: '—' }}</span></div>
                    <div class="col-md-6"><span class="text-muted d-block fs-12 mb-1">NSS</span><span>{{ $worker->nss ?: '—' }}</span></div>
                    <div class="col-md-6"><span class="text-muted d-block fs-12 mb-1">Cuenta bancaria</span><span>{{ $worker->bank_account ?: '—' }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header border-bottom"><h5 class="card-title mb-0"><i class="ri-flashlight-line me-2 text-primary"></i>Acciones de relación laboral</h5></div>
            <div class="card-body d-grid gap-2">
                @if ($worker->status !== 'active' && $worker->status !== 'terminated')
                    <form method="POST" action="{{ route('human_resources.workers.activate', $worker) }}">
                        @csrf
                        <button class="btn btn-success w-100" type="submit"><i class="ri-user-follow-line me-1"></i> Dar de alta</button>
                    </form>
                @endif

                @if ($worker->status !== 'pre_registered' && $worker->status !== 'terminated')
                    <form method="POST" action="{{ route('human_resources.workers.pre-register', $worker) }}">
                        @csrf
                        <button class="btn btn-outline-warning w-100" type="submit">Marcar pre-registro</button>
                    </form>
                @endif

                @if ($worker->status !== 'terminated')
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#terminateWorkerModal"><i class="ri-user-unfollow-line me-1"></i>Registrar baja</button>
                @endif
            </div>
        </div>
        @php($workerFile = $worker->file)
        @php($documents = [
            'ine_path' => ['label' => 'INE', 'expiration' => 'ine_expiration_date', 'worker_expiration' => true],
            'birth_certificate_path' => ['label' => 'Acta de nacimiento', 'expiration' => 'birth_certificate_expiration_date'],
            'address_proof_path' => ['label' => 'Comprobante de domicilio', 'expiration' => 'address_proof_expiration_date'],
            'nss_path' => ['label' => 'Comprobante NSS', 'expiration' => 'nss_expiration_date'],
            'license_path' => ['label' => 'Licencia', 'expiration' => 'license_expiration_date'],
            'tax_status_path' => ['label' => 'Constancia fiscal', 'expiration' => 'tax_status_expiration_date'],
            'medical_certificate_path' => ['label' => 'Certificado médico', 'expiration' => 'medical_certificate_expiration_date', 'worker_expiration' => true],
            'cv_path' => ['label' => 'Currículum', 'expiration' => 'cv_expiration_date'],
            'emergency_contact_ine_path' => ['label' => 'INE contacto de emergencia', 'expiration' => 'emergency_contact_ine_expiration_date'],
        ])
        @php($availableDocuments = collect($documents)->filter(fn ($document, $path) => filled($workerFile?->{$path}))->count())
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0"><i class="ri-folder-line me-2 text-primary"></i>Estado de expediente</h5>
                    <span class="text-muted fs-12">{{ $availableDocuments }} de {{ count($documents) }} documentos disponibles</span>
                </div>
                <a class="btn btn-light btn-sm" href="{{ route('human_resources.workers.file.show', $worker) }}" title="Gestionar expediente"><i class="ri-folder-line"></i></a>
            </div>
            <div class="list-group list-group-flush">
                @foreach($documents as $path => $document)
                    @php($hasDocument = filled($workerFile?->{$path}))
                    @php($expirationDate = ($document['worker_expiration'] ?? false) ? $worker->{$document['expiration']} : $workerFile?->{$document['expiration']})
                    @php($isExpired = $expirationDate?->isBefore(today()))
                    @php($expiresSoon = ! $isExpired && $expirationDate?->lessThanOrEqualTo(today()->addDays(30)))
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-start gap-2">
                            <i class="{{ $hasDocument ? 'ri-checkbox-circle-line text-success' : 'ri-checkbox-blank-circle-line text-muted' }} fs-18"></i>
                            <div class="flex-grow-1">
                                <div class="fw-medium fs-13">{{ $document['label'] }}</div>
                                @if(! $hasDocument)
                                    <span class="text-muted fs-12">Pendiente</span>
                                @elseif(! $expirationDate)
                                    <span class="text-muted fs-12">Sin vencimiento registrado</span>
                                @elseif($isExpired)
                                    <span class="text-danger fs-12">Venció el {{ $expirationDate->format('d/m/Y') }}</span>
                                @elseif($expiresSoon)
                                    <span class="text-warning fs-12">Vence el {{ $expirationDate->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-success fs-12">Vigente hasta {{ $expirationDate->format('d/m/Y') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
<div class="card mt-3">
    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0"><i class="ri-team-line me-2 text-primary"></i>Historial de cuadrillas</h5>
            <span class="text-muted fs-12">Asignaciones anteriores y actual del trabajador.</span>
        </div>
        <span class="badge bg-light text-dark border">{{ $worker->groups->count() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light-subtle"><tr><th>Cuadrilla</th><th>Obra</th><th>Ingreso</th><th>Salida</th></tr></thead>
            <tbody>
                @forelse($worker->groups as $group)
                    <tr>
                        <td class="fw-medium">{{ $group->name }}</td>
                        <td>{{ $group->projectWork?->name ?: '—' }}</td>
                        <td>{{ $group->pivot->joined_at?->format('d/m/Y') }}</td>
                        <td>
                            @if($group->pivot->left_at)
                                {{ $group->pivot->left_at->format('d/m/Y') }}
                            @else
                                <span class="badge bg-success-subtle text-success">Actual</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">Sin historial de cuadrillas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@php($vacationStatuses = ['in_progress' => ['En curso', 'bg-warning-subtle text-warning'], 'approved' => ['Aprobada', 'bg-success-subtle text-success'], 'taken' => ['Tomada', 'bg-primary-subtle text-primary'], 'cancelled' => ['Cancelada', 'bg-secondary-subtle text-secondary']])
<div class="card mt-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="card-title mb-0">Vacaciones</h5>
            <span class="text-muted fs-12">Solicitudes y periodos del trabajador.</span>
        </div>
        @if($worker->status !== 'terminated')
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createWorkerVacationModal"><i class="ri-calendar-event-line me-1"></i>Nueva solicitud</button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light-subtle"><tr><th>Periodo</th><th>Días</th><th>Solicitud</th><th>Estatus</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
                @forelse($worker->vacations as $vacation)
                    @php($vacationStatus = $vacationStatuses[$vacation->status])
                    <tr>
                        <td>{{ $vacation->start_date->format('d/m/Y') }} - {{ $vacation->end_date->format('d/m/Y') }}</td>
                        <td>{{ $vacation->total_days }}</td>
                        <td>{{ $vacation->request_date->format('d/m/Y') }}</td>
                        <td><span class="badge {{ $vacationStatus[1] }}">{{ $vacationStatus[0] }}</span></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                @if($vacation->status === 'in_progress')
                                    <form method="POST" action="{{ route('human_resources.worker-vacations.approve', $vacation) }}">
                                        @csrf
                                        <input type="hidden" name="return_to_worker" value="1">
                                        <button class="btn btn-soft-success btn-sm" title="Aprobar"><i class="ri-check-line"></i></button>
                                    </form>
                                @endif
                                @if(in_array($vacation->status, ['in_progress', 'approved'], true))
                                    <form method="POST" action="{{ route('human_resources.worker-vacations.cancel', $vacation) }}">
                                        @csrf
                                        <input type="hidden" name="return_to_worker" value="1">
                                        <button class="btn btn-soft-danger btn-sm" title="Cancelar"><i class="ri-close-line"></i></button>
                                    </form>
                                @endif
                                <a class="btn btn-light btn-sm" href="{{ route('human_resources.worker-vacations.show', $vacation) }}" title="Ver detalle"><i class="ri-eye-line"></i></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay solicitudes de vacaciones.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@if($worker->status !== 'terminated')
    <div class="modal fade" id="createWorkerVacationModal" tabindex="-1" aria-labelledby="createWorkerVacationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('human_resources.worker-vacations.store') }}">
                    @csrf
                    <input type="hidden" name="worker_id" value="{{ $worker->id }}">
                    <input type="hidden" name="return_to_worker" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createWorkerVacationModalLabel">Nueva solicitud de vacaciones</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="workerVacationRequestDate">Fecha de solicitud</label>
                                <input id="workerVacationRequestDate" name="request_date" type="date" class="form-control" value="{{ old('request_date', now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="workerVacationStartDate">Inicio</label>
                                <input id="workerVacationStartDate" name="start_date" type="date" class="form-control" value="{{ old('start_date') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="workerVacationEndDate">Fin</label>
                                <input id="workerVacationEndDate" name="end_date" type="date" class="form-control" value="{{ old('end_date') }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="workerVacationNotes">Notas</label>
                                <textarea id="workerVacationNotes" name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar solicitud</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@if ($worker->status !== 'terminated')
    <div class="modal fade" id="terminateWorkerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('human_resources.workers.terminate', $worker) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar baja</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="termination_type">Tipo</label>
                            <select id="termination_type" class="form-select" name="termination_type" required>
                                <option value="resignation">Renuncia</option>
                                <option value="dismissal">Despido</option>
                                <option value="rest">Descanso</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="termination_date">Fecha</label>
                            <input id="termination_date" class="form-control" type="date" name="termination_date" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div>
                            <label class="form-label" for="termination_reason">Motivo</label>
                            <input id="termination_reason" class="form-control" type="text" name="reason">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-danger" type="submit">Registrar baja</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($worker->status !== 'terminated' && ($errors->has('request_date') || $errors->has('start_date') || $errors->has('end_date') || $errors->has('notes')))
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        new bootstrap.Modal(document.getElementById('createWorkerVacationModal')).show();
    });
    </script>
    @endpush
@endif
@endsection