@extends('layouts.app')

@section('page_title', 'Vacaciones')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Vacaciones</li>
@endsection

@section('content')
@include('human_resources.partials.flash')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1">Vacaciones</h4>
        <span class="text-muted">Resumen operativo al {{ $today->format('d/m/Y') }}</span>
    </div>
    <a class="btn btn-outline-primary" href="{{ route('human_resources.workers.index') }}"><i class="ri-team-line me-1"></i>Ver trabajadores</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl">
        <div class="card h-100"><div class="card-body"><span class="text-muted fs-12">Solicitudes en curso</span><h3 class="mb-0 mt-1 text-warning">{{ $summary['in_progress'] }}</h3></div></div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card h-100"><div class="card-body"><span class="text-muted fs-12">Aprobadas</span><h3 class="mb-0 mt-1 text-success">{{ $summary['approved'] }}</h3></div></div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card h-100"><div class="card-body"><span class="text-muted fs-12">Tomadas</span><h3 class="mb-0 mt-1 text-primary">{{ $summary['taken'] }}</h3></div></div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card h-100"><div class="card-body"><span class="text-muted fs-12">Canceladas</span><h3 class="mb-0 mt-1 text-secondary">{{ $summary['cancelled'] }}</h3></div></div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card h-100"><div class="card-body"><span class="text-muted fs-12">Fuera hoy</span><h3 class="mb-0 mt-1 text-danger">{{ $summary['away_today'] }}</h3><span class="text-muted fs-12">Vacaciones aprobadas vigentes</span></div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <div>
            <h5 class="card-title mb-0">Próximos periodos</h5>
            <span class="text-muted fs-12">Solicitudes en curso y vacaciones aprobadas.</span>
        </div>
        <span class="badge bg-light text-dark border">{{ $upcomingVacations->count() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light-subtle"><tr><th>Trabajador</th><th>Obra</th><th>Periodo</th><th>Días</th><th>Estatus</th><th class="text-end">Perfil</th></tr></thead>
            <tbody>
                @forelse($upcomingVacations as $vacation)
                    @php($status = ['in_progress' => ['En curso', 'bg-warning-subtle text-warning'], 'approved' => ['Aprobada', 'bg-success-subtle text-success']][$vacation->status])
                    <tr>
                        <td class="fw-medium">{{ $vacation->worker->first_name }} {{ $vacation->worker->last_name }}</td>
                        <td>{{ $vacation->worker->projectWork?->name ?: 'Sin asignar' }}</td>
                        <td>{{ $vacation->start_date->format('d/m/Y') }} - {{ $vacation->end_date->format('d/m/Y') }}</td>
                        <td>{{ $vacation->total_days }}</td>
                        <td><span class="badge {{ $status[1] }}">{{ $status[0] }}</span></td>
                        <td class="text-end"><a class="btn btn-light btn-sm" href="{{ route('human_resources.workers.show', $vacation->worker) }}" title="Ver perfil"><i class="ri-eye-line"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay vacaciones próximas o en curso.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection