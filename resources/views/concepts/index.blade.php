@extends('layouts.app')

@section('page_title', 'Conceptos')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Conceptos</li>
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

<div class="row" id="concepts-index-content">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Catálogo de Conceptos</h4>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-soft-success"
                            data-bs-toggle="modal" data-bs-target="#modalImportConcepts">
                        <i class="ri-upload-2-line me-1"></i> Importar
                    </button>
                    <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal" data-bs-target="#modalCreateConcept">
                        <i class="ri-add-line me-1"></i> Nuevo Concepto
                    </button>
                </div>
            </div>

            {{-- Tabs de tipo --}}
            <div class="card-body border-bottom py-0 px-0">
                <ul class="nav nav-tabs nav-tabs-custom px-3" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link {{ $type === '' ? 'active' : '' }}"
                           href="{{ route('concepts.index', array_filter(['search' => $search, 'status' => $status, 'category' => $category])) }}">
                            Todos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $type === 'materiales' ? 'active' : '' }}"
                           href="{{ route('concepts.index', array_filter(['search' => $search, 'status' => $status, 'type' => 'materiales', 'category' => $category])) }}">
                            <i class="ri-box-3-line me-1"></i> Materiales
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $type === 'mantenimiento' ? 'active' : '' }}"
                           href="{{ route('concepts.index', array_filter(['search' => $search, 'status' => $status, 'type' => 'mantenimiento', 'category' => $category])) }}">
                            <i class="ri-tools-line me-1"></i> Mantenimiento
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Barra de búsqueda y filtros --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('concepts.index') }}" class="row g-2 align-items-end">
                    @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light">
                                <i class="ri-search-line text-muted"></i>
                            </span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Buscar por código o descripción…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="category" class="form-select form-select-sm">
                            <option value="">Todas las categorías</option>
                            @foreach($categories as $cat)
                                @if(!$type || $cat->type === $type)
                                    <option value="{{ $cat->id }}" @selected($category == $cat->id)>
                                        {{ $cat->name }} ({{ $cat->type === 'materiales' ? 'Mat.' : 'Mant.' }})
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="active"   @selected($status === 'active')>Activo</option>
                            <option value="inactive" @selected($status === 'inactive')>Inactivo</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if($search || $status || $category)
                            <a href="{{ route('concepts.index', array_filter(['type' => $type])) }}"
                               class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
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
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Unidad</th>
                                <th class="text-end">Precio Unitario</th>
                                <th>Categoría</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($concepts as $concept)
                                <tr data-concept-row="{{ $concept->id }}">
                                    <td><span class="fw-semibold">{{ $concept->code }}</span></td>
                                    <td class="text-wrap" style="max-width:400px">{{ $concept->description }}</td>
                                    <td>{{ $concept->unit }}</td>
                                    <td class="text-end">
                                        @if ($concept->unit_price > 0)
                                            ${{ number_format($concept->unit_price, 2) }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($concept->category)
                                            <span class="fw-medium">{{ $concept->category->name }}</span>
                                            @if($concept->subcategory)
                                                <br><span class="text-muted fs-12">{{ $concept->subcategory->name }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted fs-12">Sin categoría</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($concept->type === 'materiales')
                                            <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">Materiales</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Mantenimiento</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($concept->status === 'active')
                                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activo</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Inactivo</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button type="button"
                                                    class="btn btn-soft-primary btn-sm btn-edit-concept"
                                                    title="Editar"
                                                    data-id="{{ $concept->id }}"
                                                    data-code="{{ $concept->code }}"
                                                    data-description="{{ $concept->description }}"
                                                    data-unit="{{ $concept->unit }}"
                                                    data-unit-price="{{ $concept->unit_price }}"
                                                    data-status="{{ $concept->status }}"
                                                    data-type="{{ $concept->type }}"
                                                    data-category-id="{{ $concept->concept_category_id }}"
                                                    data-subcategory-id="{{ $concept->concept_subcategory_id }}">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                              <form action="{{ route('concepts.destroy', $concept) }}"
                                                  method="POST"
                                                  class="form-delete-concept"
                                                  data-code="{{ $concept->code }}">
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
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="ri-pages-line fs-24 d-block mb-1 opacity-50"></i>
                                        No hay conceptos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($concepts->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $concepts->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     MODAL — Crear concepto
══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalCreateConcept" tabindex="-1" aria-labelledby="modalCreateConceptLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('concepts.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateConceptLabel">
                        <i class="ri-pages-line me-1"></i> Nuevo Concepto
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="create_category" class="form-label fw-medium">
                                Categoría <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('concept_category_id') is-invalid @enderror"
                                    id="create_category" name="concept_category_id" required>
                                <option value="">Seleccione una categoría</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}"
                                            data-type="{{ $cat->type }}"
                                            @selected(old('concept_category_id') == $cat->id)>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('concept_category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="create_subcategory" class="form-label fw-medium">
                                Subcategoría <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('concept_subcategory_id') is-invalid @enderror"
                                    id="create_subcategory" name="concept_subcategory_id" required>
                                <option value="">Seleccione una subcategoría</option>
                            </select>
                            @error('concept_subcategory_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-5">
                            <label for="create_code" class="form-label fw-medium">
                                Código <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="create_code" name="code"
                                   value="{{ old('code') }}"
                                placeholder="Ej. ALM-COM-001"
                                   required autocomplete="off" readonly>
                        </div>
                        <div class="col-md-7">
                            <label for="create_unit" class="form-label fw-medium">
                                Unidad <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control @error('unit') is-invalid @enderror"
                                   id="create_unit" name="unit"
                                   value="{{ old('unit') }}"
                                   placeholder="Ej. pza, kg, m, m²"
                                   required>
                            @error('unit')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label for="create_description" class="form-label fw-medium">
                                Descripción <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="create_description" name="description"
                                      rows="3"
                                      placeholder="Descripción detallada del concepto"
                                      required>{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="create_unit_price" class="form-label fw-medium">Precio Unitario</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number"
                                       class="form-control @error('unit_price') is-invalid @enderror"
                                       id="create_unit_price" name="unit_price"
                                       value="{{ old('unit_price', '0') }}"
                                       step="0.01" min="0"
                                       placeholder="0.00">
                                @error('unit_price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="create_status" class="form-label fw-medium">
                                Estado <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('status') is-invalid @enderror"
                                    id="create_status" name="status" required>
                                <option value="active"   @selected(old('status', 'active') === 'active')>Activo</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactivo</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="create_type" class="form-label fw-medium">
                                Tipo <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('type') is-invalid @enderror"
                                    id="create_type" name="type" required>
                                <option value="materiales" @selected(old('type', $type ?: 'materiales') === 'materiales')>Materiales</option>
                                <option value="mantenimiento" @selected(old('type', $type) === 'mantenimiento')>Mantenimiento</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Guardar concepto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════
     MODAL — Editar concepto
══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalEditConcept" tabindex="-1" aria-labelledby="modalEditConceptLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formEditConcept" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditConceptLabel">
                        <i class="ri-edit-line me-1"></i> Editar Concepto
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="edit_code" class="form-label fw-medium">
                                Código <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="edit_code" name="code"
                                   required autocomplete="off">
                        </div>
                        <div class="col-md-7">
                            <label for="edit_unit" class="form-label fw-medium">
                                Unidad <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="edit_unit" name="unit"
                                   required>
                        </div>
                        <div class="col-12">
                            <label for="edit_description" class="form-label fw-medium">
                                Descripción <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control"
                                      id="edit_description" name="description"
                                      rows="3"
                                      required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_unit_price" class="form-label fw-medium">Precio Unitario</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number"
                                       class="form-control"
                                       id="edit_unit_price" name="unit_price"
                                       step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label fw-medium">
                                Estado <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_type" class="form-label fw-medium">
                                Tipo <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="edit_type" name="type" required>
                                <option value="materiales">Materiales</option>
                                <option value="mantenimiento">Mantenimiento</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_category" class="form-label fw-medium">Categoría</label>
                            <select class="form-select" id="edit_category" name="concept_category_id">
                                <option value="">— Sin categoría —</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" data-type="{{ $cat->type }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_subcategory" class="form-label fw-medium">Subcategoría</label>
                            <select class="form-select" id="edit_subcategory" name="concept_subcategory_id">
                                <option value="">— Sin subcategoría —</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Actualizar concepto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     MODAL — Importar Conceptos
══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalImportConcepts" tabindex="-1" aria-labelledby="modalImportConceptsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('concepts.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalImportConceptsLabel">
                        <i class="ri-upload-2-line me-1"></i> Importar Conceptos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13 mb-3">
                        El archivo debe contener una fila de encabezados con las siguientes columnas:
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
                                <tr><td><code>codigo</code></td><td>Código del concepto</td><td class="text-center"><span class="text-danger">✓</span></td></tr>
                                <tr><td><code>descripcion</code></td><td>Descripción</td><td class="text-center text-muted">—</td></tr>
                                <tr><td><code>unidad</code></td><td>Unidad de medida</td><td class="text-center text-muted">—</td></tr>
                                <tr><td><code>costo</code></td><td>Precio unitario</td><td class="text-center text-muted">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted fs-12 mb-3">
                        <i class="ri-information-line me-1"></i>
                        Si el código ya existe, se actualizará su información. Las demás columnas del archivo serán ignoradas.
                    </p>
                    <div class="mb-0">
                        <label for="concept_import_file" class="form-label fw-medium">Archivo Excel <span class="text-danger">*</span></label>
                        <input type="file"
                               class="form-control @error('file') is-invalid @enderror"
                               id="concept_import_file" name="file"
                               accept=".xlsx,.xls,.csv"
                               required>
                        <div class="form-text">Formatos aceptados: .xlsx, .xls, .csv — Máx. 10 MB</div>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnImportConceptsSubmit">
                        <i class="ri-upload-2-line me-1"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Carga dinámica de subcategorías ───────────────────────────────────────────
function loadSubcategories(categoryId, selectEl, preselectId, emptyLabel) {
    var defaultLabel = emptyLabel || '— Sin subcategoría —';
    selectEl.innerHTML = '<option value="">' + defaultLabel + '</option>';
    if (!categoryId) return Promise.resolve();

    return fetch('/categorias-conceptos/' + categoryId + '/subcategorias-json', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (subs) {
        subs.forEach(function (s) {
            var opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            if (preselectId && String(s.id) === String(preselectId)) opt.selected = true;
            selectEl.appendChild(opt);
        });
    })
    .catch(function () {
        return null;
    });
}

// ── Modal Crear: cascada tipo → categoría ────────────────────────────────────
var createType     = document.getElementById('create_type');
var createCategory = document.getElementById('create_category');
var createSubcat   = document.getElementById('create_subcategory');
var createCode     = document.getElementById('create_code');
var nextCodeUrl    = @json(route('concepts.next_code'));
var initialCreateSubcategoryId = @json(old('concept_subcategory_id'));
var codeRequestToken = 0;

function updateCreateCodePreview() {
    if (!createCode || !createCategory || !createSubcat) return;

    var categoryId = createCategory.value;
    var subcategoryId = createSubcat.value;

    if (!categoryId || !subcategoryId) {
        createCode.value = '';
        return;
    }

    var requestToken = ++codeRequestToken;
    var url = nextCodeUrl
        + '?concept_category_id=' + encodeURIComponent(categoryId)
        + '&concept_subcategory_id=' + encodeURIComponent(subcategoryId);

    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (data) {
        if (requestToken !== codeRequestToken) return;
        createCode.value = data && data.code ? data.code : '';
    })
    .catch(function () {
        if (requestToken !== codeRequestToken) return;
        createCode.value = '';
    });
}

function filterCategoriesByType(typeVal, categorySelect) {
    Array.from(categorySelect.options).forEach(function (opt) {
        if (!opt.value) return;
        opt.hidden = typeVal ? opt.dataset.type !== typeVal : false;
    });
    if (categorySelect.selectedOptions[0] && categorySelect.selectedOptions[0].hidden) {
        categorySelect.value = '';
        loadSubcategories('', createSubcat, null, 'Seleccione una subcategoría');
        updateCreateCodePreview();
    }
}

if (createType) {
    createType.addEventListener('change', function () {
        filterCategoriesByType(this.value, createCategory);
    });
    filterCategoriesByType(createType.value, createCategory);
}

if (createCategory) {
    createCategory.addEventListener('change', function () {
        loadSubcategories(this.value, createSubcat, null, 'Seleccione una subcategoría');
        updateCreateCodePreview();
    });
}

if (createSubcat) {
    createSubcat.addEventListener('change', function () {
        updateCreateCodePreview();
    });
}

// ── Modal Editar ─────────────────────────────────────────────────────────────
var editType     = document.getElementById('edit_type');
var editCategory = document.getElementById('edit_category');
var editSubcat   = document.getElementById('edit_subcategory');

if (editType) {
    editType.addEventListener('change', function () {
        filterCategoriesByTypeEdit(this.value);
    });
}

function filterCategoriesByTypeEdit(typeVal) {
    Array.from(editCategory.options).forEach(function (opt) {
        if (!opt.value) return;
        opt.hidden = typeVal ? opt.dataset.type !== typeVal : false;
    });
}

if (editCategory) {
    editCategory.addEventListener('change', function () {
        loadSubcategories(this.value, editSubcat, null);
    });
}

document.querySelectorAll('.btn-edit-concept').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id            = this.dataset.id;
        var code          = this.dataset.code;
        var description   = this.dataset.description;
        var unit          = this.dataset.unit;
        var unitPrice     = this.dataset.unitPrice;
        var status        = this.dataset.status;
        var type          = this.dataset.type;
        var categoryId    = this.dataset.categoryId;
        var subcategoryId = this.dataset.subcategoryId;

        var form = document.getElementById('formEditConcept');
        form.action = '/conceptos/' + id;

        document.getElementById('edit_code').value        = code;
        document.getElementById('edit_description').value = description;
        document.getElementById('edit_unit').value        = unit;
        document.getElementById('edit_unit_price').value  = unitPrice;
        document.getElementById('edit_status').value      = status;
        editType.value = type || 'materiales';

        filterCategoriesByTypeEdit(editType.value);
        editCategory.value = categoryId || '';
        loadSubcategories(categoryId, editSubcat, subcategoryId);

        var modal = new bootstrap.Modal(document.getElementById('modalEditConcept'));
        modal.show();
    });
});

document.querySelectorAll('.form-delete-concept').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!window.confirm('¿Eliminar el concepto «' + form.dataset.code + '»?')) return;

        var submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new FormData(form)
        })
        .then(function (response) {
            if (!response.ok) throw new Error();
            return response.json();
        })
        .then(function (data) {
            var row = form.closest('tr');
            var tableBody = row.parentElement;
            row.remove();

            if (!tableBody.querySelector('tr[data-concept-row]')) {
                tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">'
                    + '<i class="ri-pages-line fs-24 d-block mb-1 opacity-50"></i>'
                    + 'No hay conceptos registrados.</td></tr>';
            }

            showConceptDeletionAlert(data.message);
        })
        .catch(function () {
            submitButton.disabled = false;
            showConceptDeletionAlert('No fue posible eliminar el concepto. Intente de nuevo.', 'danger');
        });
    });
});

function showConceptDeletionAlert(message, type) {
    var alert = document.createElement('div');
    alert.className = 'alert alert-' + (type || 'success') + ' alert-dismissible fade show';
    alert.setAttribute('role', 'alert');
    alert.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    document.getElementById('concepts-index-content').before(alert);
}

document.addEventListener('DOMContentLoaded', function () {
    if (createType && createCategory) {
        filterCategoriesByType(createType.value, createCategory);
    }

    if (createCategory) {
        loadSubcategories(createCategory.value, createSubcat, initialCreateSubcategoryId, 'Seleccione una subcategoría')
            .then(function () {
                updateCreateCodePreview();
            });
    }

    const importForm = document.querySelector('#modalImportConcepts form');
    const importBtn  = document.getElementById('btnImportConceptsSubmit');
    if (importForm && importBtn) {
        importForm.addEventListener('submit', function () {
            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Importando...';
        });
    }
});
</script>
@endpush
