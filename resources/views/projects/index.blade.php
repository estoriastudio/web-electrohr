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
                <div class="d-flex gap-2">
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
                            <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
                        </div>
                    </form>
                    <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modalCreateProject">
                        <i class="ri-add-line me-1"></i> Nuevo Proyecto
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Nombre del Proyecto</th>
                                <th>Nombre del Cliente</th>
                                <th># Obras</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
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
                                        <a href="{{ route('projects.show', $project) }}" class="fw-medium text-dark">
                                            {{ $project->name }}
                                        </a>
                                        @if ($project->city || $project->state)
                                            <div class="text-muted fs-12">
                                                {{ implode(', ', array_filter([$project->city, $project->state])) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ $project->client_name }}</td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                                            {{ $project->works_count }}
                                        </span>
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
                                            <form action="{{ route('projects.destroy', $project) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('¿Eliminar este proyecto y todas sus obras?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
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


{{-- ══════════════════════════════════════════════════════════════
     MODAL — Editar Proyecto
══════════════════════════════════════════════════════════════════ --}}
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

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
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
