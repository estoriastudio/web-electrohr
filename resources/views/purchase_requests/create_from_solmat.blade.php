@extends('layouts.app')

@section('page_title', 'Crear SOLCOM desde SOLMAT #' . $materialRequest->folio)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('warehouse.solmat_pile') }}">Pila SOLMAT</a></li>
    <li class="breadcrumb-item active">Crear SOLCOM desde SOLMAT #{{ $materialRequest->folio }}</li>
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

{{-- Referencia SOLMAT --}}
<div class="alert alert-info d-flex align-items-center gap-3 mb-4 py-2">
    <i class="ri-links-line fs-20 flex-shrink-0 text-info"></i>
    <div>
        <p class="mb-0 fw-semibold fs-13">
            Generando SOLCOM a partir de
            <a href="{{ route('material_requests.show', $materialRequest) }}" class="text-info" target="_blank">
                SOLMAT #{{ $materialRequest->folio }}
            </a>
            — {{ $materialRequest->supply_category }}
        </p>
        <p class="mb-0 text-muted fs-12">
            Los {{ $materialRequest->items->count() }} concepto(s) se importarán automáticamente al crear la SOLCOM.
        </p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-xl-10">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="ri-shopping-cart-2-line me-2 text-primary"></i>Nueva Solicitud de Compra (SOLCOM)
                </h4>
                <a href="{{ route('warehouse.solmat_pile') }}" class="btn btn-light btn-sm">
                    <i class="ri-arrow-left-line me-1"></i>Cancelar
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('purchase_requests.store') }}" method="POST" id="formCreateSolcom">
                    @csrf

                    {{-- Campos ocultos --}}
                    <input type="hidden" name="material_request_id" value="{{ $materialRequest->id }}">

                    <div class="row g-3">

                        {{-- Folio y Código --}}
                        <div class="col-md-4">
                            <label class="form-label">Folio <span class="text-danger">*</span></label>
                            <input type="number" name="folio"
                                   class="form-control @error('folio') is-invalid @enderror"
                                   value="{{ old('folio', $nextFolio) }}"
                                   required min="18000" readonly
                                   style="background-color: #f8f9fa;">
                            @error('folio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Código</label>
                            <input type="text" name="code"
                                   class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code') }}"
                                   placeholder="Ej. HR.IG.9150.004-F1">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Descripción corta --}}
                        <div class="col-12">
                            <label class="form-label">
                                Descripción corta del proyecto-consecutivo <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="short_description"
                                   class="form-control @error('short_description') is-invalid @enderror"
                                   value="{{ old('short_description', $materialRequest->supply_category) }}"
                                   required placeholder="Ej. HERRAJE CONDUCTOR">
                            @error('short_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Proyecto --}}
                        <div class="col-md-6">
                            <label class="form-label">Proyecto <span class="text-danger">*</span></label>
                            <select name="project_id" id="solcomProjectId"
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

                        {{-- Obra (single, pre-seleccionada de las obras de la SOLMAT) --}}
                        <div class="col-md-6">
                            <label class="form-label">Obra <span class="text-danger">*</span></label>
                            <select name="project_work_id" id="solcomProjectWorkId"
                                    class="form-select @error('project_work_id') is-invalid @enderror" required>
                                <option value="">— Selecciona obra —</option>
                                @foreach ($materialRequest->projectWorks as $pw)
                                    <option value="{{ $pw->id }}"
                                        {{ old('project_work_id', $materialRequest->projectWorks->first()?->id) == $pw->id ? 'selected' : '' }}>
                                        {{ $pw->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('project_work_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if ($materialRequest->projectWorks->count() > 1)
                                <div class="form-text">
                                    <i class="ri-information-line me-1"></i>
                                    La SOLMAT tiene {{ $materialRequest->projectWorks->count() }} obras vinculadas. Selecciona la que aplica para esta SOLCOM.
                                </div>
                            @endif
                        </div>

                        {{-- Zona / Ubicación y Dirección --}}
                        <div class="col-md-6">
                            <label class="form-label">Zona / Ubicación <span class="text-danger">*</span></label>
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
                                   value="{{ old('request_date', now()->format('Y-m-d')) }}" required>
                            @error('request_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Necesidad <span class="text-danger">*</span></label>
                            <input type="date" name="need_date"
                                   class="form-control @error('need_date') is-invalid @enderror"
                                   value="{{ old('need_date', $materialRequest->need_date?->format('Y-m-d')) }}" required>
                            @error('need_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Preview de conceptos a importar --}}
                        <div class="col-12">
                            <hr>
                            <h6 class="fw-semibold mb-3">
                                <i class="ri-list-check me-1 text-primary"></i>
                                Conceptos a importar
                                <span class="badge bg-primary-subtle text-primary ms-1">{{ $materialRequest->items->count() }}</span>
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="bg-light-subtle">
                                        <tr>
                                            <th>#</th>
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th>Unidad</th>
                                            <th class="text-end">Cantidad Solicitada</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($materialRequest->items as $i => $item)
                                            <tr>
                                                <td class="text-muted">{{ $i + 1 }}</td>
                                                <td><span class="fw-semibold">{{ $item->code }}</span></td>
                                                <td>{{ $item->description }}</td>
                                                <td>{{ $item->unit }}</td>
                                                <td class="text-end">{{ (int) $item->quantity }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">
                                                    Esta SOLMAT no tiene conceptos registrados.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Acciones --}}
                        <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                            <a href="{{ route('warehouse.solmat_pile') }}" class="btn btn-light">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line me-1"></i>Crear SOLCOM
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
    const projectSel = document.getElementById('solcomProjectId');
    const workSel    = document.getElementById('solcomProjectWorkId');
    const solmatWorks = @json($materialRequest->projectWorks->map(fn($pw) => ['id' => $pw->id, 'name' => $pw->name]));

    // Al cambiar proyecto: cargar obras del proyecto seleccionado desde API
    projectSel.addEventListener('change', function () {
        const projectId = this.value;
        workSel.innerHTML = '<option value="">— Cargando obras… —</option>';

        if (!projectId) {
            workSel.innerHTML = '<option value="">— Selecciona obra —</option>';
            return;
        }

        fetch('/proyectos/' + projectId + '/obras-json', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(works => {
            workSel.innerHTML = '<option value="">— Selecciona obra —</option>';
            works.forEach(w => {
                const opt = document.createElement('option');
                opt.value = w.id;
                opt.textContent = w.name;
                // Pre-seleccionar si la obra es una de las obras de la SOLMAT
                if (solmatWorks.some(sw => sw.id == w.id)) opt.selected = true;
                workSel.appendChild(opt);
            });
        })
        .catch(() => { workSel.innerHTML = '<option value="">— Error al cargar obras —</option>'; });
    });
}());
</script>
@endpush
