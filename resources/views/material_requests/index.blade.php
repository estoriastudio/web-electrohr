@extends('layouts.app')

@section('page_title', 'Solicitudes de Material (SOLMAT)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Solicitudes de Material</li>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
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
                <h4 class="card-title mb-0">Solicitudes de Material</h4>
                @hasanyrole('admin|orders')
                <button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="modal" data-bs-target="#modalCreateMR">
                    <i class="ri-add-line me-1"></i> Nueva SOLMAT
                </button>
                @endhasanyrole
            </div>

            {{-- Filtros --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('material_requests.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control" placeholder="Folio, proyecto, zona, categoría…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="pending"   {{ $status === 'pending'   ? 'selected' : '' }}>Pendiente</option>
                            <option value="linked"    {{ $status === 'linked'    ? 'selected' : '' }}>Ligado</option>
                            <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Finalizado</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search || $status)
                            <a href="{{ route('material_requests.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio</th>
                                <th>Código</th>
                                <th>Proyecto</th>
                                <th>Obra</th>
                                <th>Zona</th>
                                <th>F. Solicitud</th>
                                <th>Categoría</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $statusMap = [
                                    'pending'   => ['label' => 'Pendiente',  'class' => 'bg-warning-subtle text-warning'],
                                    'linked'    => ['label' => 'Ligado',     'class' => 'bg-info-subtle text-info'],
                                    'completed' => ['label' => 'Finalizado', 'class' => 'bg-success-subtle text-success'],
                                ];
                            @endphp
                            @forelse ($materialRequests as $mr)
                                @php $s = $statusMap[$mr->status] ?? ['label' => $mr->status, 'class' => 'bg-secondary-subtle text-secondary']; @endphp
                                <tr>
                                    <td><span class="fw-semibold">{{ $mr->folio }}</span></td>
                                    <td>{{ $mr->code ?? '—' }}</td>
                                    <td>{{ $mr->project?->name ?? '—' }}</td>
                                    <td>{{ $mr->projectWork?->name ?? '—' }}</td>
                                    <td>{{ $mr->zone }}</td>
                                    <td>{{ $mr->request_date?->format('d/m/Y') }}</td>
                                    <td>{{ $mr->supply_category }}</td>
                                    <td><span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('material_requests.show', $mr) }}"
                                               class="btn btn-light btn-sm" title="Ver detalle">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            @hasanyrole('admin|orders')
                                            <a href="{{ route('material_requests.edit', $mr) }}"
                                               class="btn btn-soft-primary btn-sm" title="Editar">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <form action="{{ route('material_requests.destroy', $mr) }}" method="POST"
                                                  onsubmit="return confirm('¿Eliminar SOLMAT #{{ $mr->folio }}?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                            @endhasanyrole
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No hay solicitudes de material registradas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($materialRequests->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $materialRequests->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- MODAL — Nueva SOLMAT --}}
<div class="modal fade" id="modalCreateMR" tabindex="-1" aria-labelledby="modalCreateMRLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('material_requests.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateMRLabel">
                        <i class="ri-file-add-line me-2 text-primary"></i>Nueva Solicitud de Material (SOLMAT)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        {{-- Folio y Código --}}
                        <div class="col-md-4">
                            <label class="form-label">Folio <span class="text-danger">*</span></label>
                            <input type="number" name="folio" class="form-control @error('folio') is-invalid @enderror"
                                   value="{{ old('folio', $nextFolio) }}" required min="1">
                            @error('folio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Código</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code') }}" placeholder="Ej. HR.IG.9150.004-F1">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Proyecto / Obra --}}
                        <div class="col-md-6">
                            <label class="form-label">Proyecto <span class="text-danger">*</span></label>
                            <select name="project_id" id="mrProjectId"
                                    class="form-select @error('project_id') is-invalid @enderror" required>
                                <option value="">— Selecciona proyecto —</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Obra <span class="text-danger">*</span></label>
                            <select name="project_work_id" id="mrProjectWorkId"
                                    class="form-select @error('project_work_id') is-invalid @enderror" required>
                                <option value="">— Selecciona obra —</option>
                            </select>
                            @error('project_work_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Zona y Dirección --}}
                        <div class="col-md-6">
                            <label class="form-label">Zona <span class="text-danger">*</span></label>
                            <input type="text" name="zone" class="form-control @error('zone') is-invalid @enderror"
                                   value="{{ old('zone') }}" required>
                            @error('zone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección de entrega <span class="text-danger">*</span></label>
                            <input type="text" name="delivery_address"
                                   class="form-control @error('delivery_address') is-invalid @enderror"
                                   value="{{ old('delivery_address') }}" required>
                            @error('delivery_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Fechas --}}
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Solicitud <span class="text-danger">*</span></label>
                            <input type="date" name="request_date"
                                   class="form-control @error('request_date') is-invalid @enderror"
                                   value="{{ old('request_date', now()->format('Y-m-d')) }}" required>
                            @error('request_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Necesidad <span class="text-danger">*</span></label>
                            <input type="date" name="need_date"
                                   class="form-control @error('need_date') is-invalid @enderror"
                                   value="{{ old('need_date') }}" required>
                            @error('need_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Categoría --}}
                        <div class="col-12">
                            <label class="form-label">Categoría de Suministros <span class="text-danger">*</span></label>
                            <input type="text" name="supply_category"
                                   class="form-control @error('supply_category') is-invalid @enderror"
                                   value="{{ old('supply_category') }}" required>
                            @error('supply_category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i>Crear SOLMAT
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('mrProjectId').addEventListener('change', function () {
    const projectId  = this.value;
    const workSelect = document.getElementById('mrProjectWorkId');

    workSelect.innerHTML = '<option value="">— Cargando obras… —</option>';

    if (!projectId) {
        workSelect.innerHTML = '<option value="">— Selecciona obra —</option>';
        return;
    }

    fetch(`/proyectos/${projectId}/obras-json`)
        .then(r => r.json())
        .then(works => {
            workSelect.innerHTML = '<option value="">— Selecciona obra —</option>';
            works.forEach(w => {
                workSelect.innerHTML += `<option value="${w.id}">${w.name}</option>`;
            });
        })
        .catch(() => {
            workSelect.innerHTML = '<option value="">— Error al cargar obras —</option>';
        });
});

@if ($errors->any())
    document.addEventListener('DOMContentLoaded', () => {
        new bootstrap.Modal(document.getElementById('modalCreateMR')).show();
    });
@endif
</script>
@endpush
