@extends('layouts.app')

@section('page_title', $scope === 'all' ? 'Solicitudes de Material — Listado Completo' : 'Mis Solicitudes de Material (SOLMAT)')

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
                <h4 class="card-title mb-0">
                    @if ($scope === 'all')
                        Solicitudes de Material
                        <span class="badge bg-secondary ms-1" style="font-size: .65rem;">Listado Completo</span>
                    @else
                        Mis Solicitudes de Material
                    @endif
                </h4>
                <div class="d-flex align-items-center gap-2">
                    @hasanyrole('admin|Solmat')
                    @if ($scope === 'all')
                        <a href="{{ route('material_requests.index') }}"
                           class="btn btn-sm btn-outline-primary" title="Mis SOLMAT">
                            <i class="ri-user-line me-1"></i> Mis SOLMAT
                        </a>
                    @else
                        <a href="{{ route('material_requests.index', ['scope' => 'all']) }}"
                           class="btn btn-sm btn-outline-secondary" title="Ver todas las SOLMAT">
                            <i class="ri-list-unordered me-1"></i> Listado Completo
                        </a>
                    @endif
                    
                    @hasanyrole('admin|Solmat')
                    @can('create')
                    <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modalCreateMR">
                        <i class="ri-add-line me-1"></i> Nueva SOLMAT
                    </button>
                    @endcan
                    @endhasanyrole

                    <a href="{{ route('material_requests.archived') }}"
                       class="btn btn-sm btn-outline-secondary" title="Archivadas">
                        <i class="ri-archive-line me-1"></i> Archivadas
                    </a>
                    @endhasanyrole
                    @hasanyrole('admin')
                    <a href="{{ route('material_requests.soft_deleted') }}"
                       class="btn btn-sm btn-outline-danger" title="Papelera">
                        <i class="ri-delete-bin-line me-1"></i> Papelera
                    </a>
                    @endhasanyrole
                </div>
            </div>

            {{-- Filtros --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('material_requests.index') }}" class="row g-2 align-items-end">
                    @if ($scope === 'all')
                        <input type="hidden" name="scope" value="all">
                    @endif
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
                            <option value="sent_to_warehouse" {{ $status === 'sent_to_warehouse' ? 'selected' : '' }}>Enviado a Almacén</option>
                            <option value="changes_requested" {{ $status === 'changes_requested' ? 'selected' : '' }}>Cambios Solicitados</option>
                            <option value="linked"    {{ $status === 'linked'    ? 'selected' : '' }}>Ligado</option>
                            <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Finalizado</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search || $status)
                            <a href="{{ route('material_requests.index', $scope === 'all' ? ['scope' => 'all'] : []) }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                @include('material_requests.utilities._table')
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
@hasanyrole('admin|Solmat')
@can('create')
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
                            <label class="form-label">Folio</label>
                            <div class="form-control" style="background-color: #f8f9fa; color: #6c757d;">
                                Se asignará automáticamente
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Código</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code') }}" placeholder="Ej. HR.IG.9150.004-F1">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Proyecto / Obras --}}
                        <div class="col-md-12">
                            <label class="form-label">Proyecto <span class="text-danger">*</span></label>
                            <select name="project_id" id="mrProjectId"
                                    class="form-control @error('project_id') is-invalid @enderror" required>
                                <option value="">— Selecciona proyecto —</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Obras <span class="text-danger">*</span></label>
                            <select name="project_work_ids[]" id="mrProjectWorkId"
                                    class="form-select @error('project_work_ids') is-invalid @enderror"
                                    multiple required>
                            </select>
                            @error('project_work_ids') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Selecciona una o más obras vinculadas.</div>
                        </div>

                        {{-- Ubicación y Dirección --}}
                        <div class="col-md-12">
                            <label class="form-label">Ubicación <span class="text-danger">*</span></label><br>
                            <input type="hidden" name="location_type" id="mrLocationType"
                                   value="{{ old('location_type', 'sitio') }}">
                            <div class="location-round-selector mb-2" role="group" aria-label="Tipo de ubicación">
                                <button type="button"
                                        class="location-round-option {{ old('location_type', 'sitio') !== 'electrohr' ? 'is-active' : '' }}"
                                        data-value="sitio" id="mrLocationOptionSitio">
                                    Sitio
                                </button>
                                <button type="button"
                                        class="location-round-option {{ old('location_type') === 'electrohr' ? 'is-active' : '' }}"
                                        data-value="electrohr" id="mrLocationOptionElectrohr">
                                    Electro HR
                                </button>
                            </div>
                            <input type="text" name="zone" id="mrZone"
                                   class="form-control @error('zone') is-invalid @enderror"
                                   value="{{ old('zone') }}" required
                                   placeholder="Nombre o pega un enlace de Google Maps…">
                            @error('zone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
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
                            <select name="concept_category_id"
                                    id="mrCategoryId"
                                    class="form-select @error('concept_category_id') is-invalid @enderror"
                                    required>
                                <option value="">— Selecciona una categoría —</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}"
                                            {{ old('concept_category_id') == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('concept_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
@endcan
@endhasanyrole

@endsection

@push('scripts')
<style>
.location-round-selector {
    display: inline-flex;
    border: 1px solid var(--bs-border-color);
    border-radius: 999px;
    overflow: hidden;
    background: var(--bs-body-bg);
}
.location-round-option {
    border: 0;
    background: transparent;
    padding: .35rem .85rem;
    font-size: 12px;
    font-weight: 600;
    color: var(--bs-secondary-color);
}
.location-round-option.is-active {
    background: var(--bs-primary);
    color: #fff;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl        = document.getElementById('modalCreateMR');
    if (!modalEl) {
        return;
    }

    var projectSelect  = document.getElementById('mrProjectId');
    var workSelect     = document.getElementById('mrProjectWorkId');
    var categorySelect = document.getElementById('mrCategoryId');
    var projectChoices  = null;
    var categoryChoices = null;

    // ── Choices.js: inicializar al mostrar el modal ─────────────────────────
    modalEl.addEventListener('shown.bs.modal', function () {
        if (!projectChoices) {
            projectChoices = new Choices(projectSelect, {
                searchEnabled: true,
                searchPlaceholderValue: 'Buscar proyecto...',
                itemSelectText: '',
                noResultsText: 'Sin resultados',
                noChoicesText: 'Sin opciones disponibles',
            });
        }
        if (!categoryChoices) {
            categoryChoices = new Choices(categorySelect, {
                searchEnabled: true,
                searchPlaceholderValue: 'Buscar categoría...',
                itemSelectText: '',
                noResultsText: 'Sin resultados',
                noChoicesText: 'Sin categorías disponibles',
            });
        }
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (projectChoices)  { projectChoices.destroy();  projectChoices  = null; }
        if (categoryChoices) { categoryChoices.destroy(); categoryChoices = null; }
        if (workChoices)     { workChoices.destroy();     workChoices     = null; }
    });

    // ── Selector redondo Electro HR / Sitio ─────────────────────────────────
    var locationTypeInput = document.getElementById('mrLocationType');
    var locationButtons = modalEl.querySelectorAll('.location-round-option');
    function setLocationType(type) {
        if (!locationTypeInput) return;
        locationTypeInput.value = type;
        locationButtons.forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-value') === type);
        });
    }
    locationButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setLocationType(btn.getAttribute('data-value'));
        });
    });
    setLocationType(locationTypeInput ? locationTypeInput.value : 'sitio');

    // ── Cargar obras cuando cambia el proyecto ──────────────────────────────
    var workChoices = null;

    projectSelect.addEventListener('change', function () {
        var projectId = this.value;

        // Destruir instancia previa de Choices en obras
        if (workChoices) { workChoices.destroy(); workChoices = null; }
        workSelect.innerHTML = '';

        if (!projectId) return;

        fetch('/proyectos/' + projectId + '/obras-json', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (works) {
            workSelect.innerHTML = '';
            works.forEach(function (w) {
                var opt = document.createElement('option');
                opt.value = w.id;
                opt.textContent = w.name;
                workSelect.appendChild(opt);
            });
            // Inicializar Choices.js en modo múltiple
            workChoices = new Choices(workSelect, {
                searchEnabled: true,
                searchPlaceholderValue: 'Buscar obra…',
                itemSelectText: '',
                noResultsText: 'Sin resultados',
                noChoicesText: 'Sin obras disponibles',
                removeItemButton: true,
                placeholder: true,
                placeholderValue: '— Selecciona una o más obras —',
            });
        })
        .catch(function () {
            workSelect.innerHTML = '<option value="">— Error al cargar obras —</option>';
        });
    });
});

@if ($errors->any())
    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('modalCreateMR');
        if (modalEl) {
            new bootstrap.Modal(modalEl).show();
        }
    });
@endif
</script>
@endpush
