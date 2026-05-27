@extends('layouts.app')

@section('page_title', 'Editar SOLCOM #' . $purchaseRequest->folio)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase_requests.index') }}">Solicitudes de Compra</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase_requests.show', $purchaseRequest) }}">SOLCOM #{{ $purchaseRequest->folio }}</a></li>
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
                    <i class="ri-edit-line me-2 text-primary"></i>Editar Solicitud de Compra
                    <span class="text-primary">Folio #{{ $purchaseRequest->folio }}</span>
                </h4>
                <a href="{{ route('purchase_requests.show', $purchaseRequest) }}" class="btn btn-light btn-sm">
                    <i class="ri-arrow-left-line me-1"></i>Cancelar
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('purchase_requests.update', $purchaseRequest) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">

                        {{-- SOLMAT vinculada (informativo) --}}
                        @if ($purchaseRequest->materialRequest)
                            <div class="col-12">
                                <div class="alert alert-info py-2 d-flex align-items-center gap-2 mb-0">
                                    <i class="ri-links-line fs-16"></i>
                                    Vinculada a SOLMAT
                                    <a href="{{ route('material_requests.show', $purchaseRequest->materialRequest) }}"
                                       class="fw-bold text-decoration-none">
                                        #{{ $purchaseRequest->materialRequest->folio }}
                                    </a>
                                </div>
                            </div>
                        @endif

                        {{-- Código --}}
                        <div class="col-md-6">
                            <label class="form-label">Código</label>
                            <input type="text" name="code"
                                   class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code', $purchaseRequest->code) }}"
                                   placeholder="Ej. HR.IG.9150.004-F1">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Descripción corta --}}
                        <div class="col-md-6">
                            <label class="form-label">Descripción corta del proyecto-consecutivo <span class="text-danger">*</span></label>
                            <input type="text" name="short_description"
                                   class="form-control @error('short_description') is-invalid @enderror"
                                   value="{{ old('short_description', $purchaseRequest->short_description) }}" required>
                            @error('short_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Proyecto / Obra --}}
                        <div class="col-md-6">
                            <label class="form-label">Proyecto <span class="text-danger">*</span></label>
                            <select name="project_id" id="editPrProjectId"
                                    class="form-select @error('project_id') is-invalid @enderror" required>
                                <option value="">— Selecciona proyecto —</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}"
                                        {{ old('project_id', $purchaseRequest->project_id) == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Obra <span class="text-danger">*</span></label>
                            <select name="project_work_id" id="editPrProjectWorkId"
                                    class="form-select @error('project_work_id') is-invalid @enderror" required>
                                <option value="{{ $purchaseRequest->project_work_id }}">
                                    {{ $purchaseRequest->projectWork?->name ?? '— Selecciona obra —' }}
                                </option>
                            </select>
                            @error('project_work_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Zona y Dirección --}}
                        <div class="col-md-6">
                            <label class="form-label">Zona <span class="text-danger">*</span></label>
                            <input type="text" name="zone"
                                   class="form-control @error('zone') is-invalid @enderror"
                                   value="{{ old('zone', $purchaseRequest->zone) }}" required>
                            @error('zone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección de Entrega <span class="text-danger">*</span></label>
                            <input type="text" name="delivery_address"
                                   class="form-control @error('delivery_address') is-invalid @enderror"
                                   value="{{ old('delivery_address', $purchaseRequest->delivery_address) }}" required>
                            @error('delivery_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Fechas --}}
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Solicitud <span class="text-danger">*</span></label>
                            <input type="date" name="request_date"
                                   class="form-control @error('request_date') is-invalid @enderror"
                                   value="{{ old('request_date', $purchaseRequest->request_date?->format('Y-m-d')) }}" required>
                            @error('request_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Necesidad <span class="text-danger">*</span></label>
                            <input type="date" name="need_date"
                                   class="form-control @error('need_date') is-invalid @enderror"
                                   value="{{ old('need_date', $purchaseRequest->need_date?->format('Y-m-d')) }}" required>
                            @error('need_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Acciones --}}
                        <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                            <a href="{{ route('purchase_requests.show', $purchaseRequest) }}" class="btn btn-light">
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
    const projectSel  = document.getElementById('editPrProjectId');
    const workSel     = document.getElementById('editPrProjectWorkId');
    const currentWork = {{ $purchaseRequest->project_work_id ?? 'null' }};

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

    loadWorks(projectSel.value, currentWork);

    projectSel.addEventListener('change', function () {
        loadWorks(this.value, null);
    });
})();
</script>
@endpush
