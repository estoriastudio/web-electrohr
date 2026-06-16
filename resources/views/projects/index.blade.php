@extends('layouts.app')

@section('page_title', 'Proyectos')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Proyectos</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Listado de proyectos</h4>
                </div>
                @hasanyrole('admin|Proyectos')
                @can('create')
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-soft-success"
                            data-bs-toggle="modal" data-bs-target="#modalImportProjects">
                        <i class="ri-upload-2-line me-1"></i> Importar
                    </button>
                    <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modalCreateProject">
                        <i class="ri-add-line me-1"></i> Nuevo Proyecto
                    </button>
                </div>
                @endcan
                @endhasanyrole
            </div>

            {{-- Barra de búsqueda --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('projects.index') }}" class="d-flex gap-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                        <input type="text" name="search" value="{{ $search }}"
                               class="form-control" placeholder="Buscar por nombre o cliente…"
                               autocomplete="off">
                        @if ($search)
                            <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary" title="Limpiar">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Nombre del Proyecto</th>
                                <th>Nombre del Cliente</th>
                                <th class="text-nowrap"># Obras</th>
                                <th class="text-nowrap">Valor de proyecto</th>
                                <th class="text-nowrap">Docs</th>
                                <th class="text-nowrap">Estatus</th>
                                <th class="text-nowrap">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($projects as $project)
                                @php
                                    $sMap = [
                                        'active'   => ['label' => 'Activo',   'class' => 'bg-success-subtle text-success'],
                                        'inactive' => ['label' => 'Inactivo', 'class' => 'bg-warning-subtle text-warning'],
                                    ];
                                    $s = $sMap[$project->status] ?? ['label' => $project->status, 'class' => 'bg-secondary-subtle text-secondary'];
                                @endphp
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                                <span class="text-primary fw-semibold">
                                                    {{ strtoupper(substr($project->name, 0, 1)) }}
                                                </span>
                                            </div>
                                            <div>
                                                <a href="{{ route('projects.show', $project) }}" class="text-dark fw-medium fs-15">
                                                    {{ $project->name }}
                                                </a>
                                                @if ($project->city || $project->state)
                                                    <div class="text-muted fs-12">
                                                        {{ implode(', ', array_filter([$project->city, $project->state])) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $project->client_name }}</td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                            {{ $project->works_count }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">
                                            $ {{ number_format((float) ($project->project_value ?? 0), 2) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{-- Dots semáforo de documentación del proyecto --}}
                                        @php
                                            $docLabels = \App\Models\ProjectDocument::TYPES;
                                        @endphp
                                        <div class="d-flex gap-1 align-items-center">
                                            @foreach ($docLabels as $dt => $dtLabel)
                                                @php
                                                    $docRecord = $project->documents->firstWhere('document_type', $dt);
                                                    $hasFile   = $docRecord && $docRecord->file_path;
                                                @endphp
                                                <span class="rounded-circle d-inline-block"
                                                      style="width: 10px; height: 10px; background: {{ $hasFile ? '#28a745' : '#adb5bd' }}; cursor: default;"
                                                      data-bs-toggle="tooltip"
                                                      data-bs-placement="top"
                                                      title="{{ $dtLabel }}: {{ $hasFile ? 'Subido' : 'Pendiente' }}">
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('projects.show', $project) }}"
                                               class="btn btn-light btn-sm" title="Ver detalle">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @can('update')
                                                <button type="button"
                                                        class="btn btn-soft-primary btn-sm btn-edit-project"
                                                        title="Editar"
                                                        data-id="{{ $project->id }}"
                                                        data-name="{{ $project->name }}"
                                                        data-client="{{ $project->client_name }}"
                                                        data-city="{{ $project->city }}"
                                                        data-state="{{ $project->state }}"
                                                        data-status="{{ $project->status }}"
                                                        data-bs-toggle="modal" data-bs-target="#modalEditProject">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                            @endcan
                                            @can('delete')
                                                <form action="{{ route('projects.destroy', $project) }}"
                                                      method="POST"
                                                      onsubmit="return confirm('¿Eliminar este proyecto y todas sus obras?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No hay proyectos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($projects->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $projects->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     MODAL — Nuevo Proyecto
══════════════════════════════════════════════════════════════════ --}}
@hasanyrole('admin|Proyectos')
@can('create')
<div class="modal fade" id="modalCreateProject" tabindex="-1" aria-labelledby="modalCreateProjectLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('projects.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateProjectLabel">
                        <i class="ri-folder-add-line me-1"></i> Nuevo Proyecto
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="create_name" class="form-label fw-medium">Nombre del Proyecto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="create_name" name="name" value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="create_client_name" class="form-label fw-medium">Nombre del Cliente <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('client_name') is-invalid @enderror"
                                   id="create_client_name" name="client_name" value="{{ old('client_name') }}" required>
                            @error('client_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="create_city" class="form-label fw-medium">Ciudad</label>
                            <input type="text" class="form-control @error('city') is-invalid @enderror"
                                   id="create_city" name="city" value="{{ old('city') }}">
                            @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="create_state" class="form-label fw-medium">Estado</label>
                            <input type="text" class="form-control @error('state') is-invalid @enderror"
                                   id="create_state" name="state" value="{{ old('state') }}">
                            @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Crear proyecto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endhasanyrole


{{-- ══════════════════════════════════════════════════════════════
     MODAL — Editar Proyecto
══════════════════════════════════════════════════════════════════ --}}
@can('update')
<div class="modal fade" id="modalEditProject" tabindex="-1" aria-labelledby="modalEditProjectLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formEditProject" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditProjectLabel">
                        <i class="ri-edit-line me-1"></i> Editar Proyecto
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="edit_name" class="form-label fw-medium">Nombre del Proyecto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-12">
                            <label for="edit_client_name" class="form-label fw-medium">Nombre del Cliente <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_client_name" name="client_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_city" class="form-label fw-medium">Ciudad</label>
                            <input type="text" class="form-control" id="edit_city" name="city">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_state" class="form-label fw-medium">Estado</label>
                            <input type="text" class="form-control" id="edit_state" name="state">
                        </div>
                        <div class="col-12">
                            <label for="edit_status" class="form-label fw-medium">Estatus</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@endsection

{{-- ══════════════════════════════════════════════════════════════
     MODAL — Importar Proyectos
══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalImportProjects" tabindex="-1" aria-labelledby="modalImportProjectsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('projects.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalImportProjectsLabel">
                        <i class="ri-upload-2-line me-1"></i> Importar Proyectos y Obras
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13 mb-3">
                        El archivo Excel debe contener las siguientes columnas:
                    </p>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm fs-12 mb-0">
                            <thead class="bg-light-subtle">
                                <tr>
                                    <th>Columna</th>
                                    <th>Destino</th>
                                    <th>Requerido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td><code>proyecto</code></td><td>Nombre del proyecto</td><td class="text-center"><span class="text-danger">✓</span></td></tr>
                                <tr><td><code>cliente</code></td><td>Cliente del proyecto</td><td class="text-center"><span class="text-danger">✓</span></td></tr>
                                <tr><td><code>obra</code></td><td>Nombre de la obra</td><td class="text-center"><span class="text-danger">✓</span></td></tr>
                                <tr><td><code>num_contrato</code></td><td>Número de contrato</td><td class="text-center text-muted">—</td></tr>
                                <tr><td><code>fecha_inicio_contrato</code></td><td>Fecha inicio del contrato</td><td class="text-center text-muted">—</td></tr>
                                <tr><td><code>importe_contratado</code></td><td>Valor del contrato</td><td class="text-center text-muted">—</td></tr>
                                <tr><td><code>residente</code></td><td>Residente de obra</td><td class="text-center text-muted">—</td></tr>
                                <tr><td><code>supervisor</code></td><td>Supervisor</td><td class="text-center text-muted">—</td></tr>
                                <tr><td><code>moneda</code></td><td>Moneda (MXN, USD…)</td><td class="text-center text-muted">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mb-0">
                        <label for="import_file" class="form-label fw-medium">Archivo Excel <span class="text-danger">*</span></label>
                        <input type="file"
                               class="form-control @error('file') is-invalid @enderror"
                               id="import_file" name="file"
                               accept=".xlsx,.xls,.csv"
                               required>
                        <div class="form-text">Formatos aceptados: .xlsx, .xls, .csv — Máx. 10 MB</div>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="ri-upload-2-line me-1"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Tooltips en dots de documentación
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    document.querySelectorAll('.btn-edit-project').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id     = this.dataset.id;
            var form   = document.getElementById('formEditProject');
            form.action = '/proyectos/' + id;

            document.getElementById('edit_name').value        = this.dataset.name;
            document.getElementById('edit_client_name').value = this.dataset.client;
            document.getElementById('edit_city').value        = this.dataset.city || '';
            document.getElementById('edit_state').value       = this.dataset.state || '';
            document.getElementById('edit_status').value      = this.dataset.status;
        });
    });
});
</script>
@endpush
