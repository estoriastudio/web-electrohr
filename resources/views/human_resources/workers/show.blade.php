@extends('layouts.app')

@section('page_title', 'Trabajador')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('human_resources.workers.index') }}">Trabajadores</a></li>
    <li class="breadcrumb-item active">{{ $worker->first_name }} {{ $worker->last_name }}</li>
@endsection

@section('content')
@include('partials.flash')

@php
    $workerStatus = [
        'pre_registered' => ['Pre-registro', 'bg-warning-subtle text-warning'],
        'active' => ['Activo', 'bg-success-subtle text-success'],
        'terminated' => ['Baja', 'bg-danger-subtle text-danger'],
    ][$worker->status] ?? ['Sin definir', 'bg-secondary-subtle text-secondary'];
    $workerFile = $worker->file;
    $isFileComplete = $workerFile?->isComplete() ?? false;
    $canSeeAmounts = $profile['canSeeAmounts'];
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
                @if($worker->file_incomplete)
                    <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Expediente incompleto</span>
                @endif
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
                    <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Cuadrilla</span><span class="text-dark fw-medium">{{ $worker->workerGroup?->name ?: 'Sin cuadrilla' }}</span></div>
                    @if($canSeeAmounts)
                        <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Esquema de pago</span><span class="text-dark fw-medium">{{ $worker->payment_type === 'piecework' ? 'Destajo' : 'Sueldo semanal' }}</span></div>
                        <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Sueldo semanal</span><span class="text-dark fw-medium">${{ number_format((float) $worker->weekly_salary, 2) }}</span></div>
                    @endif
                    <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Fecha de alta</span><span class="text-dark fw-medium">{{ $worker->hire_date?->format('d/m/Y') ?: 'Pendiente' }}</span></div>
                    <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Perfil</span><span class="text-dark fw-medium">{{ $worker->is_dc5 ? 'DC5 - Agente capacitado' : 'Sin DC5' }}</span></div>
                    @if($worker->field_registered_at)
                        <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Alta en obra</span><span class="text-dark fw-medium">{{ $worker->field_registered_at->format('d/m/Y') }}@if($worker->registeredByUser) · {{ $worker->registeredByUser->name }}@endif</span></div>
                        <div class="col-sm-6 col-lg-3"><span class="text-muted d-block fs-12 mb-1">Plazo del expediente</span><span class="fw-medium {{ $worker->file_incomplete && $worker->profile_complete_deadline?->isBefore(today()) ? 'text-danger' : 'text-dark' }}">{{ $worker->profile_complete_deadline?->format('d/m/Y') ?: '—' }}</span></div>
                    @endif
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
                        @if($worker->status === 'pre_registered')
                            <button type="button" class="btn btn-outline-warning w-100" data-bs-toggle="modal" data-bs-target="#activateWithoutFileModal"><i class="ri-user-follow-line me-1"></i>Activar sin expediente completo</button>
                        @endif
                        <a class="btn btn-outline-primary" href="{{ route('human_resources.workers.file.show', $worker) }}"><i class="ri-folder-upload-line me-1"></i>Completar expediente</a>
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

@if($canSeeAmounts)
<div class="card mt-3">
    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
        <div><h4 class="card-title mb-0"><i class="ri-file-text-line me-2 text-primary"></i>Recibos de nómina</h4><span class="text-muted fs-12">Periodos autorizados.</span></div>
        <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">{{ $receipts->count() }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                <thead class="bg-light-subtle"><tr><th>Periodo</th><th>Proyecto</th><th class="text-end">Pago realizado</th><th class="text-end">Recibo</th></tr></thead>
                <tbody>
                    @forelse($receipts as $receipt)
                        <tr>
                            <td class="fw-medium">{{ $receipt['period_start']->format('d/m/Y') }} - {{ $receipt['period_start']->copy()->addDays(6)->format('d/m/Y') }}</td>
                            <td>{{ $receipt['project_name'] }}</td>
                            <td class="text-end fw-semibold">${{ number_format((float) $receipt['amount'], 2) }}</td>
                            <td class="text-end"><a href="{{ route('human_resources.workers.payroll-receipt.download', [$worker, $receipt['id']]) }}" class="btn btn-light btn-sm" title="Descargar recibo PDF"><i class="ri-download-2-line"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4"><i class="ri-inbox-line fs-24 d-block mb-1"></i>No hay recibos de periodos autorizados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if($canManageWorker && $worker->status === 'pre_registered')
    <div class="modal fade" id="activateWithoutFileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('human_resources.workers.activate', $worker) }}">
                    @csrf
                    <input type="hidden" name="without_complete_file" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title">Activar sin expediente completo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">La persona quedará Activa con la etiqueta "Expediente incompleto" hasta que se entreguen los documentos obligatorios.</p>
                        <label class="form-label" for="activate_reason">Motivo <span class="text-danger">*</span></label>
                        <textarea id="activate_reason" name="reason" rows="3" maxlength="500" required
                                  class="form-control @error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Activar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if($canManageWorker && $worker->status !== 'terminated')
    <div class="modal fade" id="terminateWorkerModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('human_resources.workers.terminate', $worker) }}">@csrf<div class="modal-header"><h5 class="modal-title">Registrar baja</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-12"><label class="form-label" for="termination_type">Tipo</label><select id="termination_type" class="form-select" name="termination_type" required><option value="resignation">Renuncia</option><option value="dismissal">Despido</option><option value="rest">Descanso</option></select></div><div class="col-12"><label class="form-label" for="termination_date">Fecha</label><input id="termination_date" class="form-control" type="date" name="termination_date" value="{{ now()->format('Y-m-d') }}" required></div><div class="col-12"><label class="form-label" for="termination_reason">Motivo</label><input id="termination_reason" class="form-control" type="text" name="reason"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger" type="submit">Registrar baja</button></div></form></div></div></div>
@endif

@if($errors->has('reason') || $errors->has('without_complete_file'))
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('activateWithoutFileModal');
                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endpush
@endif
@endsection