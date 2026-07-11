@extends('layouts.app')

@section('page_title', 'Herramientas')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Herramientas</li>
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
                <h4 class="card-title mb-0">Registro de Herramientas</h4>
                <div class="d-flex gap-2">
                    <a href="{{ route('tool_controls.index') }}" class="btn btn-sm btn-soft-success">
                        <i class="ri-calendar-check-line me-1"></i> Control de uso
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateTool">
                        <i class="ri-add-line me-1"></i> Nueva Herramienta
                    </button>
                </div>
            </div>

            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('tools.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light">
                                <i class="ri-search-line text-muted"></i>
                            </span>
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Buscar por número económico, nombre, serie, marca..."
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="root_category_id" class="form-select form-select-sm">
                            <option value="">Todas las categorías</option>
                            @foreach ($rootCategories as $rootCategory)
                                <option value="{{ $rootCategory->id }}" @selected((string) $rootCategoryId === (string) $rootCategory->id)>
                                    {{ $rootCategory->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Todos los estatus</option>
                            <option value="active" @selected($status === 'active')>Activa</option>
                            <option value="in_service" @selected($status === 'in_service')>En Servicio</option>
                            <option value="inactive" @selected($status === 'inactive')>Inactiva</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search || $status || $rootCategoryId)
                            <a href="{{ route('tools.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
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
                                <th>No. Económico</th>
                                <th>Descripción</th>
                                <th>Categoría</th>
                                <th>Marca / Modelo</th>
                                <th>No. Serie</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tools as $tool)
                                @php
                                    $selectedCategory = $tool->category;
                                    $rootCategoryForTool = $selectedCategory?->parent_id ? $selectedCategory->parent : $selectedCategory;
                                    $subcategoryForTool = $selectedCategory?->parent_id ? $selectedCategory : null;
                                @endphp
                                <tr>
                                    <td><span class="fw-semibold">{{ $tool->economic_number }}</span></td>
                                    <td>
                                        <div class="fw-medium text-wrap" style="max-width:320px;">{{ $tool->description }}</div>
                                        @if($tool->requires_calibration)
                                            <span class="badge bg-warning-subtle text-warning mt-1">Requiere calibración</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($rootCategoryForTool)
                                            <span class="fw-medium">{{ $rootCategoryForTool->name }}</span>
                                            @if ($subcategoryForTool)
                                                <br><span class="text-muted fs-12">{{ $subcategoryForTool->name }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">Sin categoría</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $tool->brand ?: 'Sin marca' }}</span>
                                        <br>
                                        <span class="fs-12 text-muted">{{ $tool->model ?: 'Sin modelo' }}</span>
                                    </td>
                                    <td>{{ $tool->serial_number ?: 'Sin serie' }}</td>
                                    <td>
                                        @if ($tool->status === 'active')
                                            <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">Activa</span>
                                        @elseif ($tool->status === 'in_service')
                                            <span class="badge bg-info-subtle text-info py-1 px-2 fs-12">En Servicio</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-12">Inactiva</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('tools.show', $tool) }}"
                                               class="btn btn-soft-info btn-sm"
                                               title="Ver detalle">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-soft-primary btn-sm btn-edit-tool"
                                                    title="Editar"
                                                    data-id="{{ $tool->id }}"
                                                    data-economic-number="{{ $tool->economic_number }}"
                                                    data-description="{{ $tool->description }}"
                                                    data-brand="{{ $tool->brand }}"
                                                    data-model="{{ $tool->model }}"
                                                    data-serial-number="{{ $tool->serial_number }}"
                                                    data-status="{{ $tool->status }}"
                                                    data-requires-calibration="{{ $tool->requires_calibration ? '1' : '0' }}"
                                                    data-root-category-id="{{ $rootCategoryForTool?->id }}"
                                                    data-subcategory-id="{{ $subcategoryForTool?->id }}">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                            <form action="{{ route('tools.destroy', $tool) }}"
                                                  method="POST"
                                                                                                    onsubmit="return confirm('¿Eliminar la herramienta {{ $tool->economic_number }}?')">
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
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-tools-line fs-24 d-block mb-1 opacity-50"></i>
                                        No se encontraron herramientas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($tools->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $tools->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="modalCreateTool" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('tools.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-hammer-line me-1"></i> Nueva Herramienta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Categoría <span class="text-danger">*</span></label>
                            <select name="root_category_id" id="create_root_category_id" class="form-select js-category-select @error('root_category_id') is-invalid @enderror" required>
                                <option value="">Seleccionar categoría...</option>
                                @foreach ($rootCategories as $rootCategory)
                                    <option value="{{ $rootCategory->id }}" @selected(old('root_category_id') == $rootCategory->id)>
                                        {{ $rootCategory->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('root_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subcategoría</label>
                            <select name="subcategory_id" id="create_subcategory_id" class="form-select js-subcategory-select @error('subcategory_id') is-invalid @enderror">
                                <option value="">Sin subcategoría</option>
                            </select>
                            <div class="form-text">Si la categoría tiene subcategorías, selecciona una. Si no tiene, déjalo vacío.</div>
                            @error('subcategory_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Número Económico <span class="text-danger">*</span></label>
                            <input type="text" name="economic_number" class="form-control @error('economic_number') is-invalid @enderror" value="{{ old('economic_number') }}" required maxlength="100" autocomplete="off">
                            @error('economic_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Descripción <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="2" required maxlength="500">{{ old('description') }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Marca</label>
                            <input type="text" name="brand" class="form-control @error('brand') is-invalid @enderror" value="{{ old('brand') }}" maxlength="120">
                            @error('brand') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Modelo</label>
                            <input type="text" name="model" class="form-control @error('model') is-invalid @enderror" value="{{ old('model') }}" maxlength="120">
                            @error('model') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Número de Serie</label>
                            <input type="text" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror" value="{{ old('serial_number') }}" maxlength="120">
                            @error('serial_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estatus <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Activa</option>
                                <option value="in_service" @selected(old('status') === 'in_service')>En Servicio</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactiva</option>
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="create_requires_calibration" name="requires_calibration" value="1" @checked(old('requires_calibration'))>
                                <label class="form-check-label" for="create_requires_calibration">Requiere calibración</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditTool" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formEditTool" action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-edit-line me-1"></i> Editar Herramienta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Categoría <span class="text-danger">*</span></label>
                            <select name="root_category_id" id="edit_root_category_id" class="form-select js-category-select" required>
                                <option value="">Seleccionar categoría...</option>
                                @foreach ($rootCategories as $rootCategory)
                                    <option value="{{ $rootCategory->id }}">{{ $rootCategory->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subcategoría</label>
                            <select name="subcategory_id" id="edit_subcategory_id" class="form-select js-subcategory-select">
                                <option value="">Sin subcategoría</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Número Económico <span class="text-danger">*</span></label>
                            <input type="text" name="economic_number" id="edit_economic_number" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Descripción <span class="text-danger">*</span></label>
                            <textarea name="description" id="edit_description" class="form-control" rows="2" required maxlength="500"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Marca</label>
                            <input type="text" name="brand" id="edit_brand" class="form-control" maxlength="120">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Modelo</label>
                            <input type="text" name="model" id="edit_model" class="form-control" maxlength="120">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Número de Serie</label>
                            <input type="text" name="serial_number" id="edit_serial_number" class="form-control" maxlength="120">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estatus <span class="text-danger">*</span></label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="active">Activa</option>
                                <option value="in_service">En Servicio</option>
                                <option value="inactive">Inactiva</option>
                            </select>
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="edit_requires_calibration" name="requires_calibration" value="1">
                                <label class="form-check-label" for="edit_requires_calibration">Requiere calibración</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function initSearchableSelect(element, placeholder) {
    if (!element || element.dataset.choicesReady === '1' || typeof Choices === 'undefined') {
        return;
    }

    element._choicesInstance = new Choices(element, {
        searchEnabled: true,
        itemSelectText: '',
        searchPlaceholderValue: placeholder,
        noResultsText: 'Sin resultados',
        noChoicesText: 'Sin opciones disponibles',
        shouldSort: false,
        maxItemCount: 1,
    });

    element.dataset.choicesReady = '1';
}

function syncChoicesOptions(selectEl) {
    if (!selectEl || !selectEl._choicesInstance) {
        return;
    }

    var selectedValue = selectEl.value;
    var choices = Array.from(selectEl.options).map(function (option) {
        return {
            value: option.value,
            label: option.text,
            selected: String(option.value) === String(selectedValue),
            disabled: option.disabled,
        };
    });

    selectEl._choicesInstance.clearChoices();
    selectEl._choicesInstance.setChoices(choices, 'value', 'label', true);
}

function loadToolSubcategories(rootCategoryId, selectEl, preselectId) {
    selectEl.innerHTML = '<option value="">Sin subcategoría</option>';
    syncChoicesOptions(selectEl);

    if (!rootCategoryId) {
        return;
    }

    fetch('/categorias-herramientas/' + rootCategoryId + '/subcategorias-json', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (response) { return response.json(); })
    .then(function (subcategories) {
        subcategories.forEach(function (subcategory) {
            var option = document.createElement('option');
            option.value = subcategory.id;
            option.textContent = subcategory.name;
            if (preselectId && String(subcategory.id) === String(preselectId)) {
                option.selected = true;
            }
            selectEl.appendChild(option);
        });

        syncChoicesOptions(selectEl);
    });
}

(function () {
    var createRootCategory = document.getElementById('create_root_category_id');
    var createSubcategory = document.getElementById('create_subcategory_id');

    initSearchableSelect(createRootCategory, 'Buscar categoría...');
    initSearchableSelect(createSubcategory, 'Buscar subcategoría...');

    if (createRootCategory && createSubcategory) {
        createRootCategory.addEventListener('change', function () {
            loadToolSubcategories(this.value, createSubcategory, null);
        });

        if (createRootCategory.value) {
            loadToolSubcategories(createRootCategory.value, createSubcategory, '{{ old('subcategory_id') }}');
        }
    }
}());

(function () {
    var editButtons = document.querySelectorAll('.btn-edit-tool');
    var editForm = document.getElementById('formEditTool');
    var editRootCategory = document.getElementById('edit_root_category_id');
    var editSubcategory = document.getElementById('edit_subcategory_id');

    initSearchableSelect(editRootCategory, 'Buscar categoría...');
    initSearchableSelect(editSubcategory, 'Buscar subcategoría...');

    if (!editButtons.length || !editForm) {
        return;
    }

    editButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            editForm.action = '/herramientas/' + this.dataset.id;

            document.getElementById('edit_economic_number').value = this.dataset.economicNumber || '';
            document.getElementById('edit_description').value = this.dataset.description || '';
            document.getElementById('edit_brand').value = this.dataset.brand || '';
            document.getElementById('edit_model').value = this.dataset.model || '';
            document.getElementById('edit_serial_number').value = this.dataset.serialNumber || '';
            document.getElementById('edit_status').value = this.dataset.status || 'active';
            document.getElementById('edit_requires_calibration').checked = this.dataset.requiresCalibration === '1';

            editRootCategory.value = this.dataset.rootCategoryId || '';
            loadToolSubcategories(this.dataset.rootCategoryId, editSubcategory, this.dataset.subcategoryId || '');

            var modal = new bootstrap.Modal(document.getElementById('modalEditTool'));
            modal.show();
        });
    });

    if (editRootCategory && editSubcategory) {
        editRootCategory.addEventListener('change', function () {
            loadToolSubcategories(this.value, editSubcategory, null);
        });
    }
}());

</script>
@endpush
