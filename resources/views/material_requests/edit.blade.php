@extends('layouts.app')

@section('page_title', 'Editar SOLMAT #' . $materialRequest->folio)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('material_requests.index') }}">Solicitudes de Material</a></li>
    <li class="breadcrumb-item"><a href="{{ route('material_requests.show', $materialRequest) }}">SOLMAT #{{ $materialRequest->folio }}</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')

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

<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="ri-edit-line me-2 text-primary"></i>Editar Solicitud de Material
                    <span class="text-primary">Folio #{{ $materialRequest->folio }}</span>
                </h4>
                <a href="{{ route('material_requests.show', $materialRequest) }}" class="btn btn-light btn-sm">
                    <i class="ri-arrow-left-line me-1"></i>Cancelar
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('material_requests.update', $materialRequest) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">

                        {{-- Código --}}
                        <div class="col-md-6">
                            <label class="form-label">Código</label>
                            <input type="text" name="code"
                                   class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code', $materialRequest->code) }}"
                                   placeholder="Ej. HR.IG.9150.004-F1">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Categoría --}}
                        <div class="col-md-6">
                            <label class="form-label">Categoría de Suministros <span class="text-danger">*</span></label>
                            <input type="text" name="supply_category"
                                   class="form-control @error('supply_category') is-invalid @enderror"
                                   value="{{ old('supply_category', $materialRequest->supply_category) }}" required>
                            @error('supply_category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Proyecto / Obra --}}
                        <div class="col-md-6">
                            <label class="form-label">Proyecto <span class="text-danger">*</span></label>
                            <select name="project_id" id="editMrProjectId"
                                    class="form-select @error('project_id') is-invalid @enderror" required>
                                <option value="">— Selecciona proyecto —</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}"
                                        {{ old('project_id', $materialRequest->project_id) == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Obra <span class="text-danger">*</span></label>
                            <select name="project_work_id" id="editMrProjectWorkId"
                                    class="form-select @error('project_work_id') is-invalid @enderror" required>
                                <option value="{{ $materialRequest->project_work_id }}">
                                    {{ $materialRequest->projectWork?->name ?? '— Selecciona obra —' }}
                                </option>
                            </select>
                            @error('project_work_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Zona y Dirección --}}
                        <div class="col-md-6">
                            <label class="form-label">Zona <span class="text-danger">*</span></label>
                            <input type="text" name="zone"
                                   class="form-control @error('zone') is-invalid @enderror"
                                   value="{{ old('zone', $materialRequest->zone) }}" required>
                            @error('zone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección de Entrega <span class="text-danger">*</span></label>
                            <input type="text" name="delivery_address"
                                   class="form-control @error('delivery_address') is-invalid @enderror"
                                   value="{{ old('delivery_address', $materialRequest->delivery_address) }}" required>
                            @error('delivery_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Fechas --}}
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Solicitud <span class="text-danger">*</span></label>
                            <input type="date" name="request_date"
                                   class="form-control @error('request_date') is-invalid @enderror"
                                   value="{{ old('request_date', $materialRequest->request_date?->format('Y-m-d')) }}" required>
                            @error('request_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Necesidad <span class="text-danger">*</span></label>
                            <input type="date" name="need_date"
                                   class="form-control @error('need_date') is-invalid @enderror"
                                   value="{{ old('need_date', $materialRequest->need_date?->format('Y-m-d')) }}" required>
                            @error('need_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Acciones --}}
                        <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                            <a href="{{ route('material_requests.show', $materialRequest) }}" class="btn btn-light">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line me-1"></i>Guardar cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const projectSel  = document.getElementById('editMrProjectId');
    const workSel     = document.getElementById('editMrProjectWorkId');
    const currentWork = {{ $materialRequest->project_work_id ?? 'null' }};

    function loadWorks(projectId, selectValue) {
        if (!projectId) return;
        fetch(`/proyectos/${projectId}/obras-json`)
            .then(r => r.json())
            .then(works => {
                workSel.innerHTML = '<option value="">— Selecciona obra —</option>';
                works.forEach(w => {
                    const opt = document.createElement('option');
                    opt.value = w.id;
                    opt.textContent = w.name;
                    if (w.id == selectValue) opt.selected = true;
                    workSel.appendChild(opt);
                });
            });
    }

    // Cargar obras del proyecto actual al iniciar
    loadWorks(projectSel.value, currentWork);

    projectSel.addEventListener('change', function () {
        loadWorks(this.value, null);
    });
})();
</script>
@endpush
