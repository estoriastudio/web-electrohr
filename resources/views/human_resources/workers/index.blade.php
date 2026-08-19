@extends('layouts.app')

@section('page_title', 'Trabajadores')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Trabajadores</li>
@endsection

@section('content')
@include('human_resources.partials.flash')
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl">
        <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between"><div><span class="text-muted fs-12">Base total</span><h3 class="mb-0 mt-1">{{ $workerStats['total'] }}</h3></div><i class="ri-team-line fs-28 text-primary"></i></div></div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between"><div><span class="text-muted fs-12">Activos</span><h3 class="mb-0 mt-1 text-success">{{ $workerStats['active'] }}</h3></div><i class="ri-user-follow-line fs-28 text-success"></i></div></div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between"><div><span class="text-muted fs-12">Pre-registro</span><h3 class="mb-0 mt-1 text-warning">{{ $workerStats['pre_registered'] }}</h3></div><i class="ri-user-add-line fs-28 text-warning"></i></div></div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between"><div><span class="text-muted fs-12">Bajas</span><h3 class="mb-0 mt-1 text-danger">{{ $workerStats['terminated'] }}</h3></div><i class="ri-user-unfollow-line fs-28 text-danger"></i></div></div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between"><div><span class="text-muted fs-12">Sin obra base</span><h3 class="mb-0 mt-1 text-secondary">{{ $workerStats['without_project'] }}</h3></div><i class="ri-map-pin-line fs-28 text-secondary"></i></div></div>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center border-bottom">
        <div>
            <h4 class="card-title mb-0">Listado de trabajadores</h4>
            <span class="text-muted fs-12">{{ $workers->total() }} registro(s) en la bandeja actual</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#importPayrollModal"><i class="ri-upload-2-line me-1"></i> Importar nómina</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#importTerminationsModal"><i class="ri-user-unfollow-line me-1"></i> Importar bajas</button>
            <a href="{{ route('human_resources.workers.export') }}" class="btn btn-outline-secondary btn-sm"><i class="ri-download-2-line me-1"></i> Exportar</a>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createWorkerModal"><i class="ri-user-add-line me-1"></i> Nuevo trabajador</button>
        </div>
    </div>
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12">
                <label class="form-label form-label-sm mb-2">Bandeja</label>
                <ul class="nav nav-tabs nav-justified" role="tablist" aria-label="Bandejas de trabajadores">
                    <li class="nav-item" role="presentation">
                        <a href="{{ route('human_resources.workers.index', ['section' => 'active', 'search' => $search ?: null, 'project_work_id' => $projectWorkId ?: null]) }}" class="nav-link {{ $section === 'active' ? 'active' : '' }}">
                            <i class="ri-user-follow-line me-1"></i>Activos
                            <span class="badge rounded-pill bg-success-subtle text-success ms-1">{{ $workerStats['active'] }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="{{ route('human_resources.workers.index', ['section' => 'pre_registered', 'search' => $search ?: null, 'project_work_id' => $projectWorkId ?: null]) }}" class="nav-link {{ $section === 'pre_registered' ? 'active' : '' }}">
                            <i class="ri-user-add-line me-1"></i>Pre-registro
                            <span class="badge rounded-pill bg-warning-subtle text-warning ms-1">{{ $workerStats['pre_registered'] }}</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="{{ route('human_resources.workers.index', ['section' => 'terminated', 'search' => $search ?: null, 'project_work_id' => $projectWorkId ?: null]) }}" class="nav-link {{ $section === 'terminated' ? 'active' : '' }}">
                            <i class="ri-user-unfollow-line me-1"></i>Bajas
                            <span class="badge rounded-pill bg-danger-subtle text-danger ms-1">{{ $workerStats['terminated'] }}</span>
                        </a>
                    </li>
                </ul>
            </div>
            <input type="hidden" name="section" value="{{ $section }}">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                    <input name="search" type="search" value="{{ $search }}" class="form-control" placeholder="Nombre, apodo, cuenta, puesto o cuadrilla" autocomplete="off">
                </div>
            </div>
            <div class="col-md-4">
                <select name="project_work_id" class="form-select form-select-sm">
                    <option value="">Todas las obras</option>
                    @foreach($projectWorks as $projectWork)
                        <option value="{{ $projectWork->id }}" @selected((string) $projectWorkId === (string) $projectWork->id)>{{ $projectWork->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button class="btn btn-primary btn-sm flex-fill" type="submit">Filtrar</button>
                @if($search || $projectWorkId)
                    <a href="{{ route('human_resources.workers.index', ['section' => $section]) }}" class="btn btn-outline-secondary btn-sm" title="Limpiar"><i class="ri-close-line"></i></a>
                @endif
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light-subtle"><tr><th>Trabajador</th><th>Apodo</th><th>Cuadrilla actual</th><th>Puesto</th><th>Obra base</th><th>Estatus</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            @forelse($workers as $worker)
                @php($badge = ['pre_registered' => ['Pre-registro', 'bg-warning-subtle text-warning'], 'active' => ['Activo', 'bg-success-subtle text-success'], 'terminated' => ['Baja', 'bg-danger-subtle text-danger']][$worker->status] ?? ['Sin definir', 'bg-secondary-subtle text-secondary'])
                @php($currentGroup = $worker->groups->first())
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-sm bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"><span class="fw-semibold">{{ strtoupper(substr($worker->first_name, 0, 1)) }}{{ strtoupper(substr($worker->last_name, 0, 1)) }}</span></div>
                            <div><a class="text-dark fw-medium" href="{{ route('human_resources.workers.show', $worker) }}">{{ $worker->last_name }}, {{ $worker->first_name }}</a><div class="text-muted fs-12">{{ $worker->employee_code ?: 'Sin número de cuenta' }}</div></div>
                        </div>
                    </td>
                    <td>{{ $worker->nickname ?: '—' }}</td>
                    <td>@if($currentGroup)<span class="fw-medium">{{ $currentGroup->name }}</span><div class="text-muted fs-12">{{ $currentGroup->projectWork?->name ?: '—' }}</div>@else<span class="text-muted">Sin cuadrilla</span>@endif</td>
                    <td>{{ $worker->positionCategory?->name ?: '—' }}</td>
                    <td>{{ $worker->projectWork?->name ?: 'Sin asignar' }}</td>
                    <td><span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span></td>
                    <td class="text-end"><a href="{{ route('human_resources.workers.show', $worker) }}" class="btn btn-light btn-sm" title="Ver trabajador"><i class="ri-eye-line"></i></a><a href="{{ route('human_resources.workers.edit', $worker) }}" class="btn btn-light btn-sm" title="Editar"><i class="ri-edit-line"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay trabajadores registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($workers->hasPages())<div class="card-footer d-flex justify-content-end">{{ $workers->links('pagination::bootstrap-5') }}</div>@endif
</div>

<div class="modal fade" id="createWorkerModal" tabindex="-1" aria-labelledby="createWorkerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" action="{{ route('human_resources.workers.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="createWorkerModalLabel">Nuevo trabajador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @include('human_resources.workers._form')
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar trabajador</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="importPayrollModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="POST" enctype="multipart/form-data" action="{{ route('human_resources.workers.import-payroll') }}">@csrf<div class="modal-header"><h5 class="modal-title">Importar nómina</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="text-muted mb-3">Se importan número de cuenta, nombre, categoría y sueldo. La obra y fecha de alta quedan pendientes de asignación.</p><label class="form-label" for="payroll_file">Archivo CSV o Excel</label><input id="payroll_file" type="file" class="form-control" name="file" accept=".csv,.xls,.xlsx" required></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Importar</button></div></form></div></div></div>

<div class="modal fade" id="importTerminationsModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="POST" enctype="multipart/form-data" action="{{ route('human_resources.workers.import-terminations') }}">@csrf<div class="modal-header"><h5 class="modal-title">Importar bajas</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="text-muted mb-3">Solo se registran filas de descanso con un trabajador existente; los casos no identificados se omiten.</p><label class="form-label" for="terminations_file">Archivo CSV o Excel</label><input id="terminations_file" type="file" class="form-control" name="file" accept=".csv,.xls,.xlsx" required></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Importar</button></div></form></div></div></div>
@endsection