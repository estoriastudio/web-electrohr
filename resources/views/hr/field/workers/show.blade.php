@extends('layouts.app')

@section('page_title', 'Trabajador')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('rh.field.workers.index') }}">Mis trabajadores</a></li>
    <li class="breadcrumb-item active">{{ $worker->first_name }} {{ $worker->last_name }}</li>
@endsection

@section('content')
    @include('partials.flash')

    @php
        $status = [
            'pre_registered' => ['Pre-registro', 'bg-warning-subtle text-warning'],
            'active' => ['Activo', 'bg-success-subtle text-success'],
            'terminated' => ['Baja', 'bg-danger-subtle text-danger'],
        ][$worker->status] ?? ['Sin definir', 'bg-secondary-subtle text-secondary'];
        $currentWork = $worker->currentAssignment?->projectWork;
    @endphp

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h4 class="mb-0">{{ $worker->first_name }} {{ $worker->last_name }}</h4>
        <span class="badge {{ $status[1] }} py-1 px-2 fs-12">{{ $status[0] }}</span>
        @if ($worker->file_incomplete)
            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">Expediente incompleto</span>
        @endif
        <a href="{{ route('rh.field.workers.index') }}" class="btn btn-light btn-sm ms-auto"><i class="ri-arrow-left-line me-1"></i> Volver</a>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header border-bottom"><h4 class="card-title mb-0">Datos generales</h4></div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-sm-6 col-lg-4"><span class="text-muted d-block fs-12 mb-1">Obra actual</span><span class="text-dark fw-medium">{{ $currentWork?->name ?? 'Sin asignar' }}</span></div>
                        <div class="col-sm-6 col-lg-4"><span class="text-muted d-block fs-12 mb-1">Proyecto</span><span class="text-dark fw-medium">{{ $currentWork?->project?->name ?? '—' }}</span></div>
                        <div class="col-sm-6 col-lg-4"><span class="text-muted d-block fs-12 mb-1">Cuadrilla</span><span class="text-dark fw-medium">{{ $worker->workerGroup?->name ?? 'Sin cuadrilla' }}</span></div>
                        <div class="col-sm-6 col-lg-4"><span class="text-muted d-block fs-12 mb-1">Puesto</span><span class="text-dark fw-medium">{{ $worker->positionCategory?->name ?? 'Sin asignar' }}</span></div>
                        <div class="col-sm-6 col-lg-4"><span class="text-muted d-block fs-12 mb-1">Fecha de alta</span><span class="text-dark fw-medium">{{ $worker->hire_date?->format('d/m/Y') ?? 'Pendiente' }}</span></div>
                        <div class="col-sm-6 col-lg-4"><span class="text-muted d-block fs-12 mb-1">Perfil</span><span class="text-dark fw-medium">{{ $worker->is_dc5 ? 'DC5 - Agente capacitado' : 'Sin DC5' }}</span></div>
                        <div class="col-sm-6 col-lg-4"><span class="text-muted d-block fs-12 mb-1">NSS</span><span>{{ $worker->nss ?: '—' }}</span></div>
                        <div class="col-sm-6 col-lg-4"><span class="text-muted d-block fs-12 mb-1">RFC / CURP</span><span>{{ $worker->rfc ?: '—' }} / {{ $worker->curp ?: '—' }}</span></div>
                        <div class="col-sm-6 col-lg-4"><span class="text-muted d-block fs-12 mb-1">Fecha de nacimiento</span><span>{{ $worker->birth_date?->format('d/m/Y') ?? '—' }}</span></div>
                        <div class="col-sm-6 col-lg-4"><span class="text-muted d-block fs-12 mb-1">Contacto de emergencia</span><span>{{ $worker->emergency_contact_name ?: '—' }}</span></div>
                        <div class="col-sm-6 col-lg-4"><span class="text-muted d-block fs-12 mb-1">Teléfono de emergencia</span><span>{{ $worker->emergency_contact_phone ?: '—' }}</span></div>
                        @if ($worker->profile_complete_deadline)
                            <div class="col-sm-6 col-lg-4">
                                <span class="text-muted d-block fs-12 mb-1">Plazo del expediente</span>
                                <span class="fw-medium {{ $worker->file_incomplete || $worker->status === 'pre_registered' ? ($worker->profile_complete_deadline->isBefore(today()) ? 'text-danger' : 'text-dark') : 'text-dark' }}">{{ $worker->profile_complete_deadline->format('d/m/Y') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header border-bottom"><h4 class="card-title mb-0">Expediente</h4></div>
                <div class="list-group list-group-flush">
                    @foreach ($documents as $document)
                        @php
                            $expiration = $document['expiration'];
                            $expired = $expiration?->isBefore(today());
                            $soon = ! $expired && $expiration?->lessThanOrEqualTo(today()->addDays(30));
                        @endphp
                        <div class="list-group-item d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-medium fs-13">{{ $document['label'] }}</div>
                                @if (! $document['available'])
                                    <span class="text-muted fs-12">Pendiente</span>
                                @elseif (! $expiration)
                                    <span class="text-muted fs-12">Sin vencimiento registrado</span>
                                @elseif ($expired)
                                    <span class="text-danger fs-12">Vencido el {{ $expiration->format('d/m/Y') }}</span>
                                @elseif ($soon)
                                    <span class="text-warning fs-12">Vence el {{ $expiration->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-success fs-12">Vigente hasta {{ $expiration->format('d/m/Y') }}</span>
                                @endif
                            </div>
                            @if ($document['available'])
                                <a href="{{ route('human_resources.workers.file.download', [$worker->id, $document['key']]) }}" class="btn btn-light btn-sm" title="Descargar"><i class="ri-download-2-line"></i></a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header border-bottom"><h4 class="card-title mb-0">Certificaciones DC3</h4></div>
                <div class="list-group list-group-flush">
                    @forelse ($worker->dc3s as $dc3)
                        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                           href="{{ route('human_resources.workers.file.dc3.download', [$worker->id, $dc3->id]) }}">
                            <span>
                                <span class="fw-medium">{{ $dc3->label }}</span>
                                <span class="text-muted fs-12 d-block">Registrada el {{ $dc3->created_at?->format('d/m/Y') }}</span>
                            </span>
                            <i class="ri-download-2-line text-muted"></i>
                        </a>
                    @empty
                        <div class="list-group-item text-muted fs-13">Sin certificaciones DC3 registradas.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
