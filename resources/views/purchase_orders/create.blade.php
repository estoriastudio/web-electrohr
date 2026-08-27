@extends('layouts.app')

@section('page_title', 'Nueva Orden de Compra')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase_orders.index') }}">Órdenes de Compra</a></li>
    <li class="breadcrumb-item active">Nueva</li>
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

{{-- ══ PASO 1 — Selección de tipo ══ --}}
<div id="step-type-select"@if(old('type')) style="display:none;"@endif>
    <div class="text-center mb-4">
        <h4 class="mb-1">¿Qué tipo de orden deseas crear?</h4>
        <p class="text-muted fs-14 mb-0">Selecciona el tipo para ver los campos correspondientes.</p>
    </div>
    <div class="row g-4 justify-content-center">

        {{-- Materiales / Servicios --}}
        <div class="col-md-5">
            <div class="card h-100 border border-2 border-transparent"
                 style="cursor:pointer;transition:border-color .15s,box-shadow .15s;"
                 onmouseenter="this.style.borderColor='#0d6efd';this.style.boxShadow='0 4px 16px rgba(13,110,253,.12)'"
                 onmouseleave="this.style.borderColor='transparent';this.style.boxShadow=''"
                 onclick="selectType('materiales_servicios')">
                <div class="card-body text-center p-4">
                    <div class="d-flex align-items-center justify-content-center mx-auto mb-3 bg-primary-subtle rounded-circle"
                         style="width:64px;height:64px;">
                        <i class="ri-box-3-line text-primary" style="font-size:28px;"></i>
                    </div>
                    <h5 class="mb-2">Materiales / Servicios</h5>
                    <p class="text-muted fs-13 mb-3">Para compras de materiales, insumos o servicios. Puede vincularse a un proyecto, obra y Solicitud de Compra (SOLCOM).</p>
                    <button type="button" class="btn btn-primary px-4">
                        <i class="ri-arrow-right-line me-1"></i> Crear este tipo
                    </button>
                </div>
            </div>
        </div>

        {{-- Mantenimiento --}}
        <div class="col-md-5">
            <div class="card h-100 border border-2 border-transparent"
                 style="cursor:pointer;transition:border-color .15s,box-shadow .15s;"
                 onmouseenter="this.style.borderColor='#6c757d';this.style.boxShadow='0 4px 16px rgba(108,117,125,.12)'"
                 onmouseleave="this.style.borderColor='transparent';this.style.boxShadow=''"
                 onclick="selectType('mantenimiento')">
                <div class="card-body text-center p-4">
                    <div class="d-flex align-items-center justify-content-center mx-auto mb-3 bg-secondary-subtle rounded-circle"
                         style="width:64px;height:64px;">
                        <i class="ri-tools-line text-secondary" style="font-size:28px;"></i>
                    </div>
                    <h5 class="mb-2">Mantenimiento</h5>
                    <p class="text-muted fs-13 mb-3">Para órdenes de mantenimiento vinculadas a un bien móvil (vehículo, maquinaria o equipo menor).</p>
                    <button type="button" class="btn btn-secondary px-4">
                        <i class="ri-arrow-right-line me-1"></i> Crear este tipo
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- ══ PASO 2 — Formulario ══ --}}
<div id="step-form"@if(!old('type')) style="display:none;"@endif>
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2 border-bottom">
            <button type="button" class="btn btn-light btn-sm" id="btnBackToTypes">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </button>
            <h5 class="mb-0 ms-1">
                <i class="ri-file-list-3-line me-1"></i> Nueva Orden de Compra
            </h5>
            <span id="typeBadge" class="badge ms-1 py-1 px-2 fs-12"></span>
        </div>
        <div class="card-body">
            <form action="{{ route('purchase_orders.store') }}" method="POST" id="formCreateOrder">
                @csrf
                <input type="hidden" name="type" id="type" value="{{ old('type') }}">

                <div class="row g-3">

                    {{-- Folio --}}
                    <div class="col-md-4">
                        <label for="folio" class="form-label fw-medium">Folio OC <span class="text-danger">*</span></label>
                        <input type="number" min="1"
                               class="form-control @error('folio') is-invalid @enderror"
                               id="folio" name="folio" value="{{ old('folio', $nextFolio) }}" required min="25000">
                        @error('folio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Proveedor --}}
                    <div class="col-md-8">
                        <label for="supplier_id" class="form-label fw-medium">Proveedor <span class="text-danger">*</span></label>
                        <select class="form-control @error('supplier_id') is-invalid @enderror"
                                id="supplier_id" name="supplier_id" required>
                            <option value="">Buscar proveedor...</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->rfc_name ?? $supplier->commercial_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="is_destajo" name="is_destajo" value="1"
                                   {{ old('is_destajo') ? 'checked' : '' }}>
                            <label class="form-check-label fw-medium" for="is_destajo">OC de destajo</label>
                        </div>
                        <div class="form-text">Permite registrar nuevas condiciones de pago y pagos después de autorizar la OC.</div>
                    </div>

                    {{-- ── Sección Materiales / Servicios ────────────────────────────────── --}}
                    <div id="section-materiales" class="col-12" style="display:none;">
                        <div class="row g-3">

                            {{-- Búsqueda SOLCOM --}}
                            <div class="col-12">
                                <div class="alert alert-info d-flex align-items-center gap-3 py-2 mb-0">
                                    <i class="ri-search-2-line fs-18 flex-shrink-0"></i>
                                    <div class="flex-grow-1">
                                        <p class="mb-1 fw-semibold fs-13">¿Tienes una Solicitud de Compra (SOLCOM)?</p>
                                        <div class="input-group input-group-sm" style="max-width:320px">
                                            <input type="number" id="solcomFolioInput" class="form-control"
                                                   placeholder="Folio SOLCOM…" min="1">
                                            <button type="button" class="btn btn-primary" id="btnSearchSolcom">
                                                <i class="ri-search-line"></i> Buscar
                                            </button>
                                        </div>
                                        <small id="solcomSearchMsg" class="text-danger d-none mt-1"></small>
                                    </div>
                                    <div id="solcomLinkedBadge" class="d-none">
                                        <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">
                                            <i class="ri-links-line me-1"></i>SOLCOM vinculada
                                        </span>
                                    </div>
                                </div>
                                <input type="hidden" name="purchase_request_id" id="purchase_request_id"
                                       value="{{ old('purchase_request_id') }}">
                            </div>

                            {{-- Proyecto --}}
                            <div class="col-md-6">
                                <label for="project_id" class="form-label fw-medium">Proyecto</label>
                                <select class="form-control @error('project_id') is-invalid @enderror"
                                        id="project_id" name="project_id">
                                    <option value="">Seleccionar proyecto...</option>
                                    @foreach ($projects as $proj)
                                        <option value="{{ $proj->id }}" {{ old('project_id') == $proj->id ? 'selected' : '' }}>
                                            {{ $proj->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            {{-- Obra --}}
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Obras a cubrir <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-light" id="btn_select_all_material_works">
                                        <i class="ri-checkbox-multiple-line me-1"></i>Seleccionar todas
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_clear_all_material_works">
                                        <i class="ri-checkbox-blank-line me-1"></i>Limpiar selección
                                    </button>
                                </div>
                                <div id="material_selected_works_error" class="alert alert-danger py-2 fs-12 d-none mb-2">
                                    Debes seleccionar al menos una obra para crear la OC.
                                </div>
                                <div id="material_project_works" class="border rounded-2 p-2" style="max-height: 220px; overflow-y: auto;">
                                    <p class="text-muted fs-12 mb-0">Selecciona un proyecto para ver sus obras.</p>
                                </div>
                                <div class="text-muted fs-12 mt-2">
                                    Seleccionadas: <strong id="material_selected_works_badge">0</strong>
                                </div>
                                @error('project_work_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @error('project_work_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            {{-- Preview conceptos SOLCOM --}}
                            <div id="solcomItemsPreview" class="col-12 d-none">
                                <hr class="my-1">
                                <p class="fw-semibold fs-13 mb-2">
                                    <i class="ri-list-check me-1 text-primary"></i>
                                    Conceptos a importar desde la SOLCOM
                                    <small class="text-muted fw-normal">(se copiarán automáticamente al crear)</small>
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="bg-light-subtle">
                                            <tr>
                                                <th>Descripción</th>
                                                <th>Unidad</th>
                                                <th class="text-end">Cantidad</th>
                                                <th class="text-end">P/U</th>
                                                <th class="text-end">Importe</th>
                                            </tr>
                                        </thead>
                                        <tbody id="solcomItemsBody"></tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- ── Sección Mantenimiento ──────────────────────────────────────────── --}}
                    <div id="section-mantenimiento" class="col-12" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="mobile_asset_id" class="form-label fw-medium">Bien Móvil <span class="text-danger">*</span></label>
                                <select class="form-control @error('mobile_asset_id') is-invalid @enderror"
                                        id="mobile_asset_id" name="mobile_asset_id">
                                    <option value="">Buscar bien móvil...</option>
                                    @foreach ($mobileAssets as $asset)
                                        <option value="{{ $asset->id }}" {{ old('mobile_asset_id') == $asset->id ? 'selected' : '' }}>
                                            {{ $asset->name }}{{ $asset->folio ? ' — ' . $asset->folio : '' }}{{ $asset->brand ? ' (' . $asset->brand . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('mobile_asset_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="maintenance_project_id" class="form-label fw-medium">Proyecto <span class="text-danger">*</span></label>
                                <select class="form-control @error('project_id') is-invalid @enderror"
                                        id="maintenance_project_id" name="project_id" disabled required>
                                    <option value="">Seleccionar proyecto...</option>
                                    @foreach ($projects as $proj)
                                        <option value="{{ $proj->id }}" {{ old('project_id') == $proj->id ? 'selected' : '' }}>
                                            {{ $proj->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-medium">Obras a cubrir <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-light" id="btn_select_all_maintenance_works">
                                        <i class="ri-checkbox-multiple-line me-1"></i>Seleccionar todas
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_clear_all_maintenance_works">
                                        <i class="ri-checkbox-blank-line me-1"></i>Limpiar selección
                                    </button>
                                </div>
                                <div id="maintenance_selected_works_error" class="alert alert-danger py-2 fs-12 d-none mb-2">
                                    Debes seleccionar al menos una obra para crear la OC.
                                </div>
                                <div id="maintenance_project_works" class="border rounded-2 p-2" style="max-height: 220px; overflow-y: auto;">
                                    <p class="text-muted fs-12 mb-0">Selecciona un proyecto para ver sus obras.</p>
                                </div>
                                <div class="text-muted fs-12 mt-2">
                                    Seleccionadas: <strong id="maintenance_selected_works_badge">0</strong>
                                </div>
                                @error('project_work_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @error('project_work_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    {{-- Moneda --}}
                    <div class="col-md-4">
                        <label for="currency" class="form-label fw-medium">Moneda <span class="text-danger">*</span></label>
                        <select class="form-select @error('currency') is-invalid @enderror" id="currency" name="currency" required>
                            <option value="MXN" {{ old('currency', 'MXN') === 'MXN' ? 'selected' : '' }}>MXN &mdash; Peso Mexicano</option>
                            <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>USD &mdash; Dólar</option>
                            <option value="EUR" {{ old('currency') === 'EUR' ? 'selected' : '' }}>EUR &mdash; Euro</option>
                        </select>
                        @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Impuesto --}}
                    @php
                        $oldIsrRate = old('isr_rate');
                        $oldRetentionIvaRate = old('retention_iva_rate');
                        $oldRetentionIsrRate = old('retention_isr_rate');
                    @endphp
                    <div class="col-md-4">
                        <label for="tax_rate" class="form-label fw-medium">Incluye impuesto <span class="text-danger">*</span></label>
                        <select class="form-select @error('tax_rate') is-invalid @enderror" id="tax_rate" name="tax_rate" required>
                            <option value="16"    {{ old('tax_rate', '16') === '16'    ? 'selected' : '' }}>IVA 16% (predeterminado)</option>
                            <option value="8"     {{ old('tax_rate') === '8'     ? 'selected' : '' }}>IVA 8%</option>
                            <option value="0"     {{ old('tax_rate') === '0'     ? 'selected' : '' }}>0%</option>
                            <option value="exempt" {{ old('tax_rate') === 'exempt' ? 'selected' : '' }}>Exento de impuesto</option>
                        </select>
                        @error('tax_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <div class="border rounded-2 p-3 bg-light-subtle">
                            <p class="mb-2 fw-medium fs-13">Impuestos adicionales (opcionales)</p>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input js-extra-tax-toggle" type="checkbox"
                                               id="check_isr_rate"
                                               data-target="isr_rate_wrapper"
                                               {{ ($oldIsrRate !== null && $oldIsrRate !== '') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="check_isr_rate">ISR</label>
                                    </div>
                                    <div id="isr_rate_wrapper" style="display:none;">
                                        <label for="isr_rate" class="form-label fw-medium">ISR (%)</label>
                                        <input type="number" step="0.01" min="0" max="100"
                                               class="form-control @error('isr_rate') is-invalid @enderror"
                                               id="isr_rate" name="isr_rate"
                                               value="{{ $oldIsrRate }}"
                                               disabled>
                                        @error('isr_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input js-extra-tax-toggle" type="checkbox"
                                               id="check_retention_iva_rate"
                                               data-target="retention_iva_rate_wrapper"
                                               {{ ($oldRetentionIvaRate !== null && $oldRetentionIvaRate !== '') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="check_retention_iva_rate">Retenciones IVA</label>
                                    </div>
                                    <div id="retention_iva_rate_wrapper" style="display:none;">
                                        <label for="retention_iva_rate" class="form-label fw-medium">Retención IVA (%)</label>
                                        <input type="number" step="0.01" min="0" max="100"
                                               class="form-control @error('retention_iva_rate') is-invalid @enderror"
                                               id="retention_iva_rate" name="retention_iva_rate"
                                               value="{{ $oldRetentionIvaRate }}"
                                               disabled>
                                        @error('retention_iva_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input js-extra-tax-toggle" type="checkbox"
                                               id="check_retention_isr_rate"
                                               data-target="retention_isr_rate_wrapper"
                                               {{ ($oldRetentionIsrRate !== null && $oldRetentionIsrRate !== '') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="check_retention_isr_rate">Retenciones ISR</label>
                                    </div>
                                    <div id="retention_isr_rate_wrapper" style="display:none;">
                                        <label for="retention_isr_rate" class="form-label fw-medium">Retención ISR (%)</label>
                                        <input type="number" step="0.01" min="0" max="100"
                                               class="form-control @error('retention_isr_rate') is-invalid @enderror"
                                               id="retention_isr_rate" name="retention_isr_rate"
                                               value="{{ $oldRetentionIsrRate }}"
                                               disabled>
                                        @error('retention_isr_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Estatus inicial --}}
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Estatus inicial</label>
                        <div class="form-control bg-light-subtle text-warning-emphasis">
                            <i class="ri-time-line me-1"></i>Pendiente
                        </div>
                        <div class="form-text">La OC deberá emitirse antes de poder autorizarse.</div>
                    </div>

                    {{-- ―― Firmas ―― --}}
                    <div class="col-12">
                        <hr class="my-1">
                        <p class="text-muted fs-12 mb-2"><i class="ri-pen-nib-line me-1"></i>Campos de firma para el PDF</p>
                    </div>

                    <div class="col-md-4">
                        <label for="elaborated_by" class="form-label fw-medium">Elabora Orden</label>
                        <input type="text" maxlength="255"
                               class="form-control @error('elaborated_by') is-invalid @enderror"
                               id="elaborated_by" name="elaborated_by"
                               value="{{ old('elaborated_by', auth()->user()->name) }}">
                        @error('elaborated_by')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="attorney_name" class="form-label fw-medium">Apoderado</label>
                        <input type="text" maxlength="255"
                               class="form-control @error('attorney_name') is-invalid @enderror"
                               id="attorney_name" name="attorney_name"
                               value="{{ old('attorney_name') }}"
                               placeholder="Nombre del apoderado">
                        @error('attorney_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="supplier_signatory" class="form-label fw-medium">Aceptación del Proveedor</label>
                        <input type="text" maxlength="255"
                               class="form-control @error('supplier_signatory') is-invalid @enderror"
                               id="supplier_signatory" name="supplier_signatory"
                               value="{{ old('supplier_signatory') }}"
                               placeholder="Nombre del contacto del proveedor">
                        @error('supplier_signatory')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="authorized_signatory" class="form-label fw-medium">Autorización de Pedido</label>
                        <select class="form-select @error('authorized_signatory') is-invalid @enderror"
                                id="authorized_signatory" name="authorized_signatory">
                            <option value="">Seleccionar autorizador...</option>
                            @foreach ($authorizedSignatories as $sig)
                                <option value="{{ $sig }}" {{ old('authorized_signatory') === $sig ? 'selected' : '' }}>{{ $sig }}</option>
                            @endforeach
                        </select>
                        @error('authorized_signatory')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                </div>

                <div class="mt-4 d-flex gap-2 justify-content-end">
                    <a href="{{ route('purchase_orders.index') }}" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary" id="createOrderButton">
                        <i class="ri-save-line me-1"></i> Crear orden
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

@include('purchase_orders.partials.supplier-profile-incomplete-modal')

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    var stepTypeSelect     = document.getElementById('step-type-select');
    var stepForm           = document.getElementById('step-form');
    var typeInput          = document.getElementById('type');
    var typeBadge          = document.getElementById('typeBadge');
    var btnBack            = document.getElementById('btnBackToTypes');
    var sectionMat         = document.getElementById('section-materiales');
    var sectionMant        = document.getElementById('section-mantenimiento');
    var projectSelect      = document.getElementById('project_id');
    var maintenanceProjectSelect = document.getElementById('maintenance_project_id');
    var materialWorksContainer = document.getElementById('material_project_works');
    var maintenanceWorksContainer = document.getElementById('maintenance_project_works');
    var mobileAssetSelect  = document.getElementById('mobile_asset_id');
    var supplierChoices    = null;
    var projectChoices     = null;
    var maintenanceProjectChoices = null;
    var mobileAssetChoices = null;

    var typeConfig = {
        'materiales_servicios': { label: 'Materiales / Servicios', badgeClass: 'bg-primary-subtle text-primary' },
        'mantenimiento':        { label: 'Mantenimiento',          badgeClass: 'bg-secondary-subtle text-secondary' },
    };

    function initSupplierChoices() {
        if (!supplierChoices) {
            supplierChoices = new Choices(document.getElementById('supplier_id'), {
                searchEnabled: true,
                searchPlaceholderValue: 'Buscar proveedor...',
                itemSelectText: '',
                noResultsText: 'Sin resultados',
                noChoicesText: 'Sin opciones disponibles',
            });
        }
    }

    function selectType(type) {
        typeInput.value = type;

        // Actualizar badge del encabezado
        var cfg = typeConfig[type];
        typeBadge.className = 'badge ms-1 py-1 px-2 fs-12 ' + cfg.badgeClass;
        typeBadge.textContent = cfg.label;

        // Mostrar/ocultar secciones condicionales
        if (type === 'materiales_servicios') {
            sectionMat.style.display  = '';
            sectionMant.style.display = 'none';
            projectSelect.disabled = false;
            maintenanceProjectSelect.disabled = true;
            setWorksDisabled(materialWorksContainer, false);
            setWorksDisabled(maintenanceWorksContainer, true);
            if (mobileAssetSelect) mobileAssetSelect.required = false;
            initSupplierChoices();
            if (!projectChoices) {
                projectChoices = new Choices(projectSelect, {
                    searchEnabled: true,
                    searchPlaceholderValue: 'Buscar proyecto...',
                    itemSelectText: '',
                    noResultsText: 'Sin resultados',
                    noChoicesText: 'Sin opciones disponibles',
                });
            }
        } else {
            sectionMant.style.display = '';
            sectionMat.style.display  = 'none';
            projectSelect.disabled = true;
            maintenanceProjectSelect.disabled = false;
            setWorksDisabled(materialWorksContainer, true);
            setWorksDisabled(maintenanceWorksContainer, false);
            if (mobileAssetSelect) mobileAssetSelect.required = true;
            initSupplierChoices();
            if (!mobileAssetChoices) {
                mobileAssetChoices = new Choices(document.getElementById('mobile_asset_id'), {
                    searchEnabled: true,
                    searchPlaceholderValue: 'Buscar bien móvil...',
                    itemSelectText: '',
                    noResultsText: 'Sin resultados',
                    noChoicesText: 'Sin opciones disponibles',
                });
            }
            if (!maintenanceProjectChoices) {
                maintenanceProjectChoices = new Choices(maintenanceProjectSelect, {
                    searchEnabled: true,
                    searchPlaceholderValue: 'Buscar proyecto...',
                    itemSelectText: '',
                    noResultsText: 'Sin resultados',
                    noChoicesText: 'Sin opciones disponibles',
                });
            }
        }

        stepTypeSelect.style.display = 'none';
        stepForm.style.display       = '';
    }

    // Exponer para los onclick de las tarjetas
    window.selectType = selectType;

    // Botón volver a selección de tipo
    btnBack.addEventListener('click', function () {
        stepForm.style.display       = 'none';
        stepTypeSelect.style.display = '';
        typeInput.value              = '';
    });

    // Si hay old('type') (regreso tras error de validación), mostrar formulario directamente
    if (typeInput.value) {
        selectType(typeInput.value);
    }

    // ── Toggle impuestos adicionales ─────────────────────────────────────
    function toggleExtraTaxField(checkbox) {
        var wrapper = document.getElementById(checkbox.dataset.target);
        if (!wrapper) return;
        var input = wrapper.querySelector('input');
        var enabled = checkbox.checked;
        wrapper.style.display = enabled ? '' : 'none';
        if (input) {
            input.disabled = !enabled;
            input.required = enabled;
        }
    }

    document.querySelectorAll('.js-extra-tax-toggle').forEach(function (cb) {
        cb.addEventListener('change', function () { toggleExtraTaxField(cb); });
        toggleExtraTaxField(cb);
    });

    // ── Validar el perfil del proveedor para fincar la OC ─────────────────
    var supplierSelect = document.getElementById('supplier_id');
    var orderForm = document.getElementById('formCreateOrder');
    var supplierReadinessUrl = @json(route('suppliers.purchase_order_readiness', ['supplier' => '__supplier__']));
    var supplierProfileModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSupplierProfileIncomplete'));
    var supplierProfileLink = document.getElementById('supplierProfileLink');
    var missingFieldsList = document.getElementById('supplierProfileMissingFields');
    var chooseAnotherSupplierButton = document.getElementById('chooseAnotherSupplier');
    var createOrderButton = document.getElementById('createOrderButton');
    var checkedSupplierId = '';
    var supplierIsReady = false;
    var latestReadiness = null;
    var supplierProfileLocked = false;
    var lockedControlStates = [];

    function syncChoicesState(choice) {
        if (!choice) return;
        if (choice.passedElement.element.disabled) {
            choice.disable();
        } else {
            choice.enable();
        }
    }

    function setSupplierProfileLocked(locked) {
        if (supplierProfileLocked === locked) return;

        supplierProfileLocked = locked;
        var controls = Array.prototype.slice.call(orderForm.querySelectorAll('input, select, textarea, button'));

        if (locked) {
            lockedControlStates = controls.map(function (control) {
                return { control: control, disabled: control.disabled, readOnly: control.readOnly };
            });
            controls.forEach(function (control) {
                if (control.type === 'hidden') return;
                if (control.tagName === 'INPUT' && !['checkbox', 'radio', 'file', 'submit', 'button', 'reset'].includes(control.type)) {
                    control.readOnly = true;
                } else if (control.tagName === 'TEXTAREA') {
                    control.readOnly = true;
                } else {
                    control.disabled = true;
                }
            });
            createOrderButton.hidden = true;
        } else {
            lockedControlStates.forEach(function (state) {
                state.control.disabled = state.disabled;
                state.control.readOnly = state.readOnly;
            });
            lockedControlStates = [];
            createOrderButton.hidden = false;
        }

        [supplierChoices, projectChoices, maintenanceProjectChoices, mobileAssetChoices].forEach(syncChoicesState);
    }

    function showSupplierProfileIncomplete(readiness) {
        missingFieldsList.replaceChildren();
        readiness.missing_fields.forEach(function (field) {
            var item = document.createElement('li');
            item.textContent = field;
            missingFieldsList.appendChild(item);
        });
        supplierProfileLink.href = readiness.profile_url;
        supplierProfileModal.show();
    }

    function checkSupplierReadiness() {
        var supplierId = supplierSelect.value;
        checkedSupplierId = supplierId;
        supplierIsReady = false;
        latestReadiness = null;

        if (!supplierId) return;

        fetch(supplierReadinessUrl.replace('__supplier__', encodeURIComponent(supplierId)), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (response) { return response.json(); })
        .then(function (readiness) {
            if (supplierSelect.value !== supplierId) return;
            latestReadiness = readiness;
            supplierIsReady = readiness.ready;
            setSupplierProfileLocked(!readiness.ready);
            if (!readiness.ready) showSupplierProfileIncomplete(readiness);
        })
        .catch(function () {});
    }

    supplierSelect.addEventListener('change', checkSupplierReadiness);
    orderForm.addEventListener('submit', function (event) {
        if (!supplierSelect.value || (supplierIsReady && checkedSupplierId === supplierSelect.value)) return;

        event.preventDefault();
        if (latestReadiness && !latestReadiness.ready) {
            showSupplierProfileIncomplete(latestReadiness);
        } else {
            checkSupplierReadiness();
        }
    });

    if (supplierSelect.value) checkSupplierReadiness();

    chooseAnotherSupplierButton.addEventListener('click', function () {
        checkedSupplierId = '';
        supplierIsReady = false;
        latestReadiness = null;
        supplierSelect.disabled = false;
        syncChoicesState(supplierChoices);
        if (supplierChoices) {
            supplierChoices.setChoiceByValue('');
        } else {
            supplierSelect.value = '';
            supplierSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        supplierSelect.focus();
    });

    // ── Cargar obras al cambiar proyecto (cascade) ────────────────────────
    function getWorkCheckboxes(container) {
        return Array.prototype.slice.call(container.querySelectorAll('.js-project-work'));
    }

    function setWorksDisabled(container, disabled) {
        container.dataset.disabled = disabled ? '1' : '0';
        getWorkCheckboxes(container).forEach(function (checkbox) {
            checkbox.disabled = disabled;
        });
    }

    function updateSelectedWorks(container, badge, error) {
        var selected = getWorkCheckboxes(container).filter(function (checkbox) {
            return checkbox.checked;
        }).length;

        badge.textContent = String(selected);
        error.classList.toggle('d-none', selected > 0);
        return selected;
    }

    function loadWorks(projectId, container, selectedWorkIds, update) {
        container.dataset.projectId = projectId;
        container.replaceChildren();

        if (!projectId) {
            var prompt = document.createElement('p');
            prompt.className = 'text-muted fs-12 mb-0';
            prompt.textContent = 'Selecciona un proyecto para ver sus obras.';
            container.appendChild(prompt);
            update();
            return;
        }

        fetch('/proyectos/' + projectId + '/obras-json', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (works) {
            if (container.dataset.projectId !== projectId) return;

            if (!works.length) {
                var empty = document.createElement('p');
                empty.className = 'text-muted fs-12 mb-0';
                empty.textContent = 'Este proyecto no tiene obras disponibles.';
                container.appendChild(empty);
            }

            works.forEach(function (work) {
                var item = document.createElement('div');
                var checkbox = document.createElement('input');
                var label = document.createElement('label');

                item.className = 'form-check mb-2';
                checkbox.className = 'form-check-input js-project-work';
                checkbox.type = 'checkbox';
                checkbox.name = 'project_work_ids[]';
                checkbox.id = container.id + '_work_' + work.id;
                checkbox.value = work.id;
                checkbox.checked = selectedWorkIds.indexOf(String(work.id)) !== -1;
                checkbox.disabled = container.dataset.disabled === '1';
                label.className = 'form-check-label';
                label.htmlFor = checkbox.id;
                label.textContent = work.name;
                item.appendChild(checkbox);
                item.appendChild(label);
                container.appendChild(item);
            });

            update();
        });
    }

    function initWorkControls(container, selectAllButton, clearAllButton, badge, error) {
        function update() {
            return updateSelectedWorks(container, badge, error);
        }

        container.addEventListener('change', function (event) {
            if (event.target.classList.contains('js-project-work')) update();
        });
        selectAllButton.addEventListener('click', function () {
            getWorkCheckboxes(container).forEach(function (checkbox) { checkbox.checked = true; });
            update();
        });
        clearAllButton.addEventListener('click', function () {
            getWorkCheckboxes(container).forEach(function (checkbox) { checkbox.checked = false; });
            update();
        });

        return update;
    }

    var updateMaterialWorks = initWorkControls(
        materialWorksContainer,
        document.getElementById('btn_select_all_material_works'),
        document.getElementById('btn_clear_all_material_works'),
        document.getElementById('material_selected_works_badge'),
        document.getElementById('material_selected_works_error')
    );
    var updateMaintenanceWorks = initWorkControls(
        maintenanceWorksContainer,
        document.getElementById('btn_select_all_maintenance_works'),
        document.getElementById('btn_clear_all_maintenance_works'),
        document.getElementById('maintenance_selected_works_badge'),
        document.getElementById('maintenance_selected_works_error')
    );

    projectSelect.addEventListener('change', function () {
        loadWorks(this.value, materialWorksContainer, [], updateMaterialWorks);
    });

    maintenanceProjectSelect.addEventListener('change', function () {
        loadWorks(this.value, maintenanceWorksContainer, [], updateMaintenanceWorks);
    });

    var oldWorkIds = @json(array_map('strval', (array) old('project_work_ids', old('project_work_id') ? [old('project_work_id')] : [])));
    if (typeInput.value === 'mantenimiento' && maintenanceProjectSelect.value) {
        loadWorks(maintenanceProjectSelect.value, maintenanceWorksContainer, oldWorkIds, updateMaintenanceWorks);
    }
    if (typeInput.value === 'materiales_servicios' && projectSelect.value) {
        loadWorks(projectSelect.value, materialWorksContainer, oldWorkIds, updateMaterialWorks);
    }

    orderForm.addEventListener('submit', function (event) {
        var isMaintenance = typeInput.value === 'mantenimiento';
        var container = isMaintenance ? maintenanceWorksContainer : materialWorksContainer;
        var badge = isMaintenance
            ? document.getElementById('maintenance_selected_works_badge')
            : document.getElementById('material_selected_works_badge');
        var error = isMaintenance
            ? document.getElementById('maintenance_selected_works_error')
            : document.getElementById('material_selected_works_error');

        if (updateSelectedWorks(container, badge, error) > 0) return;

        event.preventDefault();
        error.classList.remove('d-none');
        error.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // ── Búsqueda de SOLCOM por folio ──────────────────────────────────────
    var btnSearchSolcom   = document.getElementById('btnSearchSolcom');
    var solcomFolioInput  = document.getElementById('solcomFolioInput');
    var solcomHiddenInput = document.getElementById('purchase_request_id');
    var solcomMsgEl       = document.getElementById('solcomSearchMsg');
    var solcomBadgeEl     = document.getElementById('solcomLinkedBadge');

    function buscarSolcom() {
        var folio = solcomFolioInput.value.trim();
        solcomMsgEl.classList.add('d-none');
        solcomBadgeEl.classList.add('d-none');
        document.getElementById('solcomItemsPreview').classList.add('d-none');

        if (!folio) {
            solcomMsgEl.textContent = 'Ingresa un folio.';
            solcomMsgEl.classList.remove('d-none');
            return;
        }

        fetch('/solicitudes-compra/buscar-por-folio?folio=' + encodeURIComponent(folio), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            if (!r.ok) return r.json().then(function (e) { return Promise.reject(e); });
            return r.json();
        })
        .then(function (data) {
            solcomHiddenInput.value = data.id;
            solcomBadgeEl.classList.remove('d-none');

            var tbody = document.getElementById('solcomItemsBody');
            tbody.innerHTML = '';
            if (data.items && data.items.length) {
                data.items.forEach(function (item) {
                    var qty   = parseFloat(item.quantity  || 0);
                    var price = parseFloat(item.unit_price || 0);
                    var total = (qty * price).toFixed(2);
                    tbody.innerHTML += '<tr>'
                        + '<td>' + (item.description || '—') + '</td>'
                        + '<td>' + (item.unit || '—') + '</td>'
                        + '<td class="text-end">' + qty.toFixed(2) + '</td>'
                        + '<td class="text-end">' + price.toFixed(2) + '</td>'
                        + '<td class="text-end">' + total + '</td>'
                        + '</tr>';
                });
                document.getElementById('solcomItemsPreview').classList.remove('d-none');
            }
        })
        .catch(function (err) {
            solcomHiddenInput.value = '';
            solcomMsgEl.textContent = (err && err.error) ? err.error : 'SOLCOM no encontrada. Verifica el folio.';
            solcomMsgEl.classList.remove('d-none');
        });
    }

    btnSearchSolcom.addEventListener('click', buscarSolcom);
    solcomFolioInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); buscarSolcom(); }
    });
});
</script>
@endpush
