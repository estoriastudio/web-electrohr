@extends('layouts.app')

@section('page_title', 'Trabajador')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('human_resources.workers.index') }}">Trabajadores</a></li>
    <li class="breadcrumb-item active">{{ $worker->first_name }} {{ $worker->last_name }}</li>
@endsection

@section('content')
@include('human_resources.partials.flash')

@php
    $workerStatus = [
        'pre_registered' => ['Pre-registro', 'bg-warning-subtle text-warning'],
        'active' => ['Activo', 'bg-success-subtle text-success'],
        'terminated' => ['Baja', 'bg-danger-subtle text-danger'],
    ][$worker->status] ?? ['Sin definir', 'bg-secondary-subtle text-secondary'];
    $paymentType = $worker->payment_type === 'piecework' ? 'Destajo' : 'Sueldo semanal';
    $workerFile = $worker->file;
    $isFileComplete = $workerFile?->isComplete() ?? false;
    $documents = [
        'ine_path' => ['label' => 'INE', 'expiration' => 'ine_expiration_date', 'worker_expiration' => true],
        'birth_certificate_path' => ['label' => 'Acta de nacimiento', 'expiration' => 'birth_certificate_expiration_date'],
        'address_proof_path' => ['label' => 'Comprobante de domicilio', 'expiration' => 'address_proof_expiration_date'],
        'nss_path' => ['label' => 'Comprobante NSS', 'expiration' => 'nss_expiration_date'],
        'license_path' => ['label' => 'Licencia', 'expiration' => 'license_expiration_date'],
        'tax_status_path' => ['label' => 'Constancia fiscal', 'expiration' => 'tax_status_expiration_date'],
        'medical_certificate_path' => ['label' => 'Certificado médico', 'expiration' => 'medical_certificate_expiration_date', 'worker_expiration' => true],
        'cv_path' => ['label' => 'Currículum', 'expiration' => 'cv_expiration_date'],
        'emergency_contact_ine_path' => ['label' => 'INE contacto de emergencia', 'expiration' => 'emergency_contact_ine_expiration_date'],
        'professional_title_path' => ['label' => 'Título profesional (opcional)'],
        'professional_license_path' => ['label' => 'Cédula profesional (opcional)'],
    ];
    $availableDocuments = collect($documents)->filter(fn ($document, $path) => filled($workerFile?->{$path}))->count();
    $vacationStatuses = [
        'in_progress' => ['En curso', 'bg-warning-subtle text-warning'],
        'approved' => ['Aprobada', 'bg-success-subtle text-success'],
        'taken' => ['Tomada', 'bg-primary-subtle text-primary'],
        'cancelled' => ['Cancelada', 'bg-secondary-subtle text-secondary'],
    ];
    $incentiveLabels = ['incentive' => 'Incentivo', 'overtime' => 'Horas extra', 'day_off_exchange' => 'Libranza', 'emergency' => 'Emergencia'];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div class="d-flex align-items-center gap-3">
        <div class="avatar-md bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
            @if($worker->profile_photo_path)
                <img src="{{ route('human_resources.workers.profile-photo', $worker) }}" alt="Fotografía de {{ $worker->first_name }} {{ $worker->last_name }}" class="w-100 h-100 rounded-circle" style="object-fit: cover;">
            @else
                <span class="fs-20 fw-semibold">{{ strtoupper(substr($worker->first_name, 0, 1)) }}{{ strtoupper(substr($worker->last_name, 0, 1)) }}</span>
            @endif
        </div>
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <h4 class="mb-0">{{ $worker->first_name }} {{ $worker->last_name }}</h4>
                <span class="badge {{ $workerStatus[1] }} py-1 px-2 fs-12">{{ $workerStatus[0] }}</span>
            </div>
            <span class="text-muted fs-13">No. de empleado (NSS): {{ $worker->nss }}</span>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-light" href="{{ route('human_resources.workers.file.show', $worker) }}"><i class="ri-folder-line me-1"></i>Expediente</a>
        @if($canManageWorker)
            <a class="btn btn-primary" href="{{ route('human_resources.workers.edit', $worker) }}"><i class="ri-edit-line me-1"></i>Editar</a>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header border-bottom"><h4 class="card-title mb-0"><i class="ri-briefcase-4-line me-2 text-primary"></i>Resumen laboral</h4></div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Puesto</span><span class="text-dark fw-medium">{{ $worker->positionCategory?->name ?: 'Sin asignar' }}</span></div>
                    <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Obra actual</span><span class="text-dark fw-medium">{{ $worker->currentProjectWork()?->name ?: 'Sin asignar' }}</span></div>
                    @if($canManageWorker)
                        <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Esquema de pago</span><span class="text-dark fw-medium">{{ $paymentType }}</span></div>
                        <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Sueldo semanal</span><span class="text-dark fw-medium">${{ number_format((float) $worker->weekly_salary, 2) }}</span></div>
                    @endif
                    <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Fecha de alta</span><span class="text-dark fw-medium">{{ $worker->hire_date?->format('d/m/Y') ?: 'Pendiente' }}</span></div>
                    <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Perfil</span><span class="text-dark fw-medium">{{ $worker->is_dc5 ? 'DC5 - Agente capacitado' : 'Sin DC5' }}</span></div>
                </div>
                <hr class="my-4">
                <h6 class="text-muted text-uppercase fs-12 mb-3">Datos personales y contacto</h6>
                <div class="row g-3">
                    <div class="col-md-6"><span class="text-muted d-block fs-12 mb-1">RFC / CURP</span><span>{{ $worker->rfc ?: '—' }} / {{ $worker->curp ?: '—' }}</span></div>
                    <div class="col-md-6"><span class="text-muted d-block fs-12 mb-1">Fecha de nacimiento</span><span>{{ $worker->birth_date?->format('d/m/Y') ?: '—' }}</span></div>
                    <div class="col-md-6"><span class="text-muted d-block fs-12 mb-1">Contacto de emergencia</span><span>{{ $worker->emergency_contact_name ?: '—' }}</span></div>
                    <div class="col-md-6"><span class="text-muted d-block fs-12 mb-1">Teléfono de emergencia</span><span>{{ $worker->emergency_contact_phone ?: '—' }}</span></div>
                    <div class="col-md-6"><span class="text-muted d-block fs-12 mb-1">NSS</span><span>{{ $worker->nss ?: '—' }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        @if($canManageWorker)
        <div class="card">
            <div class="card-header border-bottom"><h4 class="card-title mb-0"><i class="ri-flashlight-line me-2 text-primary"></i>Acciones laborales</h4></div>
            <div class="card-body d-grid gap-2">
                @if ($worker->status === 'terminated')
                    <form method="POST" action="{{ route('human_resources.workers.reactivate', $worker) }}">
                        @csrf
                        <button class="btn btn-success w-100" type="submit"><i class="ri-user-follow-line me-1"></i>Reactivar trabajador</button>
                    </form>
                @endif
                @if ($worker->status !== 'active' && $worker->status !== 'terminated')
                    @if($isFileComplete)
                        <form method="POST" action="{{ route('human_resources.workers.activate', $worker) }}">@csrf<button class="btn btn-success w-100" type="submit"><i class="ri-user-follow-line me-1"></i>Dar de alta</button></form>
                    @else
                        @role('admin')
                            <form method="POST" action="{{ route('human_resources.workers.activate', $worker) }}">@csrf<button class="btn btn-outline-warning w-100" type="submit"><i class="ri-user-follow-line me-1"></i>Dar de alta sin expediente completo</button></form>
                        @else
                        <a class="btn btn-outline-primary" href="{{ route('human_resources.workers.file.show', $worker) }}"><i class="ri-folder-upload-line me-1"></i>Completar expediente para dar de alta</a>
                        @endrole
                    @endif
                @endif
                @if ($worker->status !== 'pre_registered' && $worker->status !== 'terminated')
                    <form method="POST" action="{{ route('human_resources.workers.pre-register', $worker) }}">@csrf<button class="btn btn-outline-warning w-100" type="submit">Marcar pre-registro</button></form>
                @endif
                @if ($worker->status !== 'terminated')
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#terminateWorkerModal"><i class="ri-user-unfollow-line me-1"></i>Registrar baja</button>
                @endif
            </div>
        </div>
        @endif
        <div class="card mt-3">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <div><h4 class="card-title mb-0"><i class="ri-folder-line me-2 text-primary"></i>Expediente</h4><span class="text-muted fs-12">{{ $availableDocuments }} de {{ count($documents) }} documentos disponibles</span></div>
                <a class="btn btn-light btn-sm" href="{{ route('human_resources.workers.file.show', $worker) }}" title="Gestionar expediente"><i class="ri-arrow-right-line align-middle fs-18"></i></a>
            </div>
            <div class="list-group list-group-flush">
                @foreach($documents as $path => $document)
                    @php
                        $hasDocument = filled($workerFile?->{$path});
                        $expirationColumn = $document['expiration'] ?? null;
                        $expirationDate = $expirationColumn
                            ? (($document['worker_expiration'] ?? false) ? $worker->{$expirationColumn} : $workerFile?->{$expirationColumn})
                            : null;
                        $isExpired = $expirationDate?->isBefore(today());
                        $expiresSoon = ! $isExpired && $expirationDate?->lessThanOrEqualTo(today()->addDays(30));
                    @endphp
                    <div class="list-group-item px-3 py-2"><div class="d-flex align-items-start gap-2"><i class="{{ $hasDocument ? 'ri-checkbox-circle-line text-success' : 'ri-checkbox-blank-circle-line text-muted' }} fs-18"></i><div class="flex-grow-1"><div class="fw-medium fs-13">{{ $document['label'] }}</div>@if(! $hasDocument)<span class="text-muted fs-12">Pendiente</span>@elseif(! $expirationDate)<span class="text-muted fs-12">Sin vencimiento registrado</span>@elseif($isExpired)<span class="text-danger fs-12">Venció el {{ $expirationDate->format('d/m/Y') }}</span>@elseif($expiresSoon)<span class="text-warning fs-12">Vence el {{ $expirationDate->format('d/m/Y') }}</span>@else<span class="text-success fs-12">Vigente hasta {{ $expirationDate->format('d/m/Y') }}</span>@endif</div></div></div>
                @endforeach
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <div><h4 class="card-title mb-0"><i class="ri-award-line me-2 text-primary"></i>Certificaciones DC3</h4><span class="text-muted fs-12">{{ $worker->dc3s->count() }} certificación(es) registrada(s)</span></div>
                <a class="btn btn-light btn-sm" href="{{ route('human_resources.workers.file.show', $worker) }}" title="Gestionar certificaciones DC3"><i class="ri-arrow-right-line align-middle fs-18"></i></a>
            </div>
            <div class="list-group list-group-flush">
                @forelse($worker->dc3s as $dc3)
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ route('human_resources.workers.file.dc3.download', [$worker, $dc3]) }}"><span class="fw-medium">{{ $dc3->label }}</span><i class="ri-download-2-line text-muted"></i></a>
                @empty
                    <div class="list-group-item text-muted fs-13">Sin certificaciones DC3 registradas.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@if($canManageWorker)
<div class="card mt-3">
    <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><h4 class="card-title mb-0"><i class="ri-team-line me-2 text-primary"></i>Historial de cuadrillas</h4><span class="text-muted fs-12">Asignaciones anteriores y actual del trabajador.</span></div>
        <span class="badge bg-light-subtle text-dark border py-1 px-2 fs-12">{{ $worker->groups->count() }}</span>
    </div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table align-middle text-nowrap table-hover table-centered mb-0"><thead class="bg-light-subtle"><tr><th>Cuadrilla</th><th>Obra</th><th>Ingreso</th><th>Salida</th></tr></thead><tbody>@forelse($worker->groups as $group)<tr><td class="fw-medium">{{ $group->name }}</td><td>{{ $group->projectWork?->name ?: '—' }}</td><td>{{ $group->pivot->joined_at?->format('d/m/Y') }}</td><td>@if($group->pivot->left_at){{ $group->pivot->left_at->format('d/m/Y') }}@else<span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Actual</span>@endif</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-4"><i class="ri-inbox-line fs-24 d-block mb-1"></i>Sin historial de cuadrillas.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endif

@if($canManageWorker)
<div class="card mt-3">
    <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><h4 class="card-title mb-0"><i class="ri-medal-line me-2 text-primary"></i>Incentivos</h4><span class="text-muted fs-12">Se incluyen automáticamente en la nómina de la semana correspondiente.</span></div>
        @if($worker->status !== 'terminated')<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createWorkerIncentiveModal"><i class="ri-add-line me-1"></i>Registrar incentivo</button>@endif
    </div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table align-middle text-nowrap table-hover table-centered mb-0"><thead class="bg-light-subtle"><tr><th>Fecha</th><th>Concepto</th><th>Tipo</th><th>Estatus</th><th>Notas</th><th class="text-end">Acciones</th></tr></thead><tbody>@forelse($worker->incentives as $incentive)<tr><td>{{ $incentive->incentive_date->format('d/m/Y') }}</td><td>{{ $incentiveLabels[$incentive->category] ?? $incentive->category }}</td><td>@if($incentive->category === 'overtime' && $incentive->overtime_hours !== null)<span class="badge bg-info-subtle text-info py-1 px-2 fs-12">{{ $incentive->overtime_hours }} h x ${{ number_format((float) $incentive->overtime_hourly_rate, 2) }}</span>@else<span class="badge bg-info-subtle text-info py-1 px-2 fs-12">{{ $incentive->rate_type }}</span>@endif</td><td><span class="badge {{ $incentive->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} py-1 px-2 fs-12">{{ $incentive->status === 'active' ? 'Activo' : 'Cancelado' }}</span></td><td class="text-muted text-wrap">{{ $incentive->notes ?: '—' }}</td><td class="text-end">@if($worker->status !== 'terminated')<form method="POST" action="{{ route('human_resources.incentives.destroy', $incentive) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este incentivo?')">@csrf @method('DELETE')<input type="hidden" name="return_to_worker" value="1"><button class="btn btn-soft-danger btn-sm" title="Eliminar"><i class="ri-delete-bin-line align-middle fs-18"></i></button></form>@endif</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4"><i class="ri-inbox-line fs-24 d-block mb-1"></i>No hay incentivos registrados.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endif

<div class="card mt-3">
    <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><h4 class="card-title mb-0"><i class="ri-calendar-event-line me-2 text-primary"></i>Vacaciones</h4><span class="text-muted fs-12">Solicitudes y periodos del trabajador.</span></div>
        @if($canManageWorker && $worker->status !== 'terminated')<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createWorkerVacationModal"><i class="ri-add-line me-1"></i>Nueva solicitud</button>@endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                <thead class="bg-light-subtle"><tr><th>Periodo</th><th>Días</th><th>Solicitud</th><th>Estatus</th>@if($canManageWorker)<th class="text-end">Acciones</th>@endif</tr></thead>
                <tbody>
                    @forelse($worker->vacations as $vacation)
                        @php($vacationStatus = $vacationStatuses[$vacation->status])
                        <tr>
                            <td>{{ $vacation->start_date->format('d/m/Y') }} - {{ $vacation->end_date->format('d/m/Y') }}</td>
                            <td>{{ $vacation->total_days }}</td>
                            <td>{{ $vacation->request_date->format('d/m/Y') }}</td>
                            <td><span class="badge {{ $vacationStatus[1] }} py-1 px-2 fs-12">{{ $vacationStatus[0] }}</span></td>
                            @if($canManageWorker)<td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    @if($vacation->status === 'in_progress')
                                        <form method="POST" action="{{ route('human_resources.worker-vacations.approve', $vacation) }}">
                                            @csrf
                                            <input type="hidden" name="return_to_worker" value="1">
                                            <button class="btn btn-soft-success btn-sm" title="Aprobar"><i class="ri-check-line align-middle fs-18"></i></button>
                                        </form>
                                    @endif
                                    @if(in_array($vacation->status, ['in_progress', 'approved'], true))
                                        <form method="POST" action="{{ route('human_resources.worker-vacations.cancel', $vacation) }}">
                                            @csrf
                                            <input type="hidden" name="return_to_worker" value="1">
                                            <button class="btn btn-soft-danger btn-sm" title="Cancelar"><i class="ri-close-line align-middle fs-18"></i></button>
                                        </form>
                                    @endif
                                    <a class="btn btn-soft-info btn-sm" href="{{ route('human_resources.worker-vacations.show', $vacation) }}" title="Ver detalle"><i class="ri-eye-line align-middle fs-18"></i></a>
                                </div>
                            </td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canManageWorker ? 5 : 4 }}" class="text-center text-muted py-4"><i class="ri-inbox-line fs-24 d-block mb-1"></i>No hay solicitudes de vacaciones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canManageWorker && $worker->status !== 'terminated')
    <div class="modal fade" id="createWorkerVacationModal" tabindex="-1" aria-labelledby="createWorkerVacationModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('human_resources.worker-vacations.store') }}">@csrf<input type="hidden" name="worker_id" value="{{ $worker->id }}"><input type="hidden" name="return_to_worker" value="1"><div class="modal-header"><h5 class="modal-title" id="createWorkerVacationModalLabel">Nueva solicitud de vacaciones</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label" for="workerVacationRequestDate">Fecha de solicitud</label><input id="workerVacationRequestDate" name="request_date" type="date" class="form-control" value="{{ old('request_date', now()->format('Y-m-d')) }}" required></div><div class="col-md-6"><label class="form-label" for="workerVacationStartDate">Inicio</label><input id="workerVacationStartDate" name="start_date" type="date" class="form-control" value="{{ old('start_date') }}" required></div><div class="col-md-6"><label class="form-label" for="workerVacationEndDate">Fin</label><input id="workerVacationEndDate" name="end_date" type="date" class="form-control" value="{{ old('end_date') }}" required></div><div class="col-12"><label class="form-label" for="workerVacationNotes">Notas</label><textarea id="workerVacationNotes" name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar solicitud</button></div></form></div></div></div>

    <div class="modal fade" id="createWorkerIncentiveModal" tabindex="-1" aria-labelledby="createWorkerIncentiveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('human_resources.incentives.store') }}" data-incentive-form>
                    @csrf
                    <input type="hidden" name="worker_id" value="{{ $worker->id }}">
                    <input type="hidden" name="return_to_worker" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createWorkerIncentiveModalLabel">Registrar incentivo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="workerIncentiveCategory">Concepto <span class="text-danger">*</span></label>
                                <select id="workerIncentiveCategory" class="form-select @error('category') is-invalid @enderror" name="category" required>
                                    <option value="">Selecciona un concepto</option>
                                    <option value="incentive" @selected(old('category') === 'incentive')>Incentivo</option>
                                    <option value="overtime" @selected(old('category') === 'overtime')>Horas extra</option>
                                    <option value="day_off_exchange" @selected(old('category') === 'day_off_exchange')>Libranza</option>
                                </select>
                                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5 d-none" data-incentive-rate-field>
                                <label class="form-label" for="workerIncentiveRateType">Tipo <span class="text-danger">*</span></label>
                                <select id="workerIncentiveRateType" class="form-select @error('rate_type') is-invalid @enderror" name="rate_type" required>
                                    @foreach(['A', 'B', 'C', 'D'] as $rateType)
                                        <option value="{{ $rateType }}" @selected(old('rate_type') === $rateType)>{{ $rateType }}</option>
                                    @endforeach
                                </select>
                                @error('rate_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 d-none" data-overtime-field>
                                <label class="form-label" for="workerIncentiveOvertimeHours">Cantidad de horas extra <span class="text-danger">*</span></label>
                                <input id="workerIncentiveOvertimeHours" class="form-control @error('overtime_hours') is-invalid @enderror" name="overtime_hours" type="number" min="0.01" max="99.99" step="0.01" value="{{ old('overtime_hours') }}">
                                @error('overtime_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 d-none" data-overtime-field>
                                <label class="form-label" for="workerIncentiveOvertimeHourlyRate">Valor de la hora extra <span class="text-danger">*</span></label>
                                <input id="workerIncentiveOvertimeHourlyRate" class="form-control @error('overtime_hourly_rate') is-invalid @enderror" name="overtime_hourly_rate" type="number" min="0.01" max="99999.99" step="0.01" value="{{ old('overtime_hourly_rate') }}">
                                @error('overtime_hourly_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-7 d-none" data-incentive-details-field>
                                <label class="form-label" for="workerIncentiveDate">Fecha <span class="text-danger">*</span></label>
                                <input id="workerIncentiveDate" class="form-control @error('incentive_date') is-invalid @enderror" name="incentive_date" type="date" value="{{ old('incentive_date', now()->format('Y-m-d')) }}" required>
                                @error('incentive_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 d-none" data-incentive-details-field>
                                <label class="form-label" for="workerIncentiveNotes">Notas</label>
                                <textarea id="workerIncentiveNotes" class="form-control" name="notes" rows="3">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar incentivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('#createWorkerIncentiveModal [data-incentive-form]');
        const category = form.querySelector('[name="category"]');
        const rateField = form.querySelector('[data-incentive-rate-field]');
        const rateType = form.querySelector('[name="rate_type"]');
        const overtimeFields = form.querySelectorAll('[data-overtime-field]');
        const detailFields = form.querySelectorAll('[data-incentive-details-field]');

        const updateFields = function () {
            const hasCategory = category.value !== '';
            const isOvertime = category.value === 'overtime';
            const allowedTypes = category.value === 'day_off_exchange' ? ['A', 'B', 'C'] : ['A', 'B', 'C', 'D'];

            rateField.classList.toggle('d-none', !hasCategory || isOvertime);
            rateType.disabled = !hasCategory || isOvertime;
            overtimeFields.forEach(function (field) { field.classList.toggle('d-none', !isOvertime); });
            detailFields.forEach(function (field) { field.classList.toggle('d-none', !hasCategory); });

            Array.from(rateType.options).forEach(function (option) {
                option.hidden = !allowedTypes.includes(option.value);
            });

            if (!allowedTypes.includes(rateType.value)) {
                rateType.value = allowedTypes[0];
            }
        };

        category.addEventListener('change', updateFields);
        updateFields();
    });
    </script>
    @endpush

    <div class="modal fade" id="terminateWorkerModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('human_resources.workers.terminate', $worker) }}">@csrf<div class="modal-header"><h5 class="modal-title">Registrar baja</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-12"><label class="form-label" for="termination_type">Tipo</label><select id="termination_type" class="form-select" name="termination_type" required><option value="resignation">Renuncia</option><option value="dismissal">Despido</option><option value="rest">Descanso</option></select></div><div class="col-12"><label class="form-label" for="termination_date">Fecha</label><input id="termination_date" class="form-control" type="date" name="termination_date" value="{{ now()->format('Y-m-d') }}" required></div><div class="col-12"><label class="form-label" for="termination_reason">Motivo</label><input id="termination_reason" class="form-control" type="text" name="reason"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger" type="submit">Registrar baja</button></div></form></div></div></div>
@endif

@if ($worker->status !== 'terminated' && ($errors->has('request_date') || $errors->has('start_date') || $errors->has('end_date')))
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        new bootstrap.Modal(document.getElementById('createWorkerVacationModal')).show();
    });
    </script>
    @endpush
@endif

@if ($worker->status !== 'terminated' && ($errors->has('category') || $errors->has('rate_type') || $errors->has('overtime_hours') || $errors->has('overtime_hourly_rate') || $errors->has('incentive_date')))
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        new bootstrap.Modal(document.getElementById('createWorkerIncentiveModal')).show();
    });
    </script>
    @endpush
@endif
@endsection