@extends('layouts.app')

@section('page_title', 'Crear OC desde SOLCOM #' . $purchaseRequest->folio)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchasing.solcom_pile') }}">Pila SOLCOM</a></li>
    <li class="breadcrumb-item active">Crear OC desde SOLCOM #{{ $purchaseRequest->folio }}</li>
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

{{-- Referencia SOLCOM --}}
<div class="alert alert-info d-flex align-items-center gap-3 mb-4 py-2">
    <i class="ri-links-line fs-20 text-info"></i>
    <div>
        <p class="mb-0 fw-semibold fs-13">
            Generando Orden de Compra a partir de
            <a href="{{ route('purchase_requests.show', $purchaseRequest) }}" class="text-info" target="_blank">
                SOLCOM #{{ $purchaseRequest->folio }}
            </a>
            @if ($purchaseRequest->short_description)
                — {{ $purchaseRequest->short_description }}
            @endif
        </p>
        <p class="mb-0 text-muted fs-12">
            Puedes elegir que conceptos heredar; por defecto se seleccionan todos.
        </p>
    </div>
</div>

<div class="alert alert-warning d-flex align-items-start gap-2 py-2 mb-3">
    <i class="ri-git-branch-line fs-18 mt-1 text-warning"></i>
    <div>
        <p class="mb-0 fw-semibold fs-13">Bifurcación parcial de SOLCOM</p>
        <p class="mb-0 text-muted fs-12">
            Esta OC puede tomar solo una parte de los conceptos para este proveedor. Los conceptos no seleccionados
            permanecerán disponibles para generar otra OC desde la misma SOLCOM.
        </p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-xl-10">
        @php
            $defaultSelectedItems = $purchaseRequest->items->pluck('id')->map(fn($id) => (string) $id)->all();
            $selectedItems = old('selected_item_ids', $defaultSelectedItems);
            $defaultSelectedWorks = $purchaseRequest->projectWorks->pluck('id')->map(fn($id) => (string) $id)->all();
            if (empty($defaultSelectedWorks) && $purchaseRequest->project_work_id) {
                $defaultSelectedWorks = [(string) $purchaseRequest->project_work_id];
            }
            $selectedWorks = old('project_work_ids', $defaultSelectedWorks);
        @endphp
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="ri-file-list-3-line me-2 text-primary"></i>Nueva Orden de Compra
                    <span class="badge bg-primary-subtle text-primary ms-2 py-1 px-2 fs-12">Materiales / Servicios</span>
                </h4>
                <a href="{{ route('purchasing.solcom_pile') }}" class="btn btn-light btn-sm">
                    <i class="ri-arrow-left-line me-1"></i>Cancelar
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('purchase_orders.store') }}" method="POST" id="formCreateOrderFromSolcom">
                    @csrf

                    {{-- Campos ocultos --}}
                    <input type="hidden" name="type"                value="materiales_servicios">
                    <input type="hidden" name="purchase_request_id" value="{{ $purchaseRequest->id }}">

                    <div class="row g-3">

                        {{-- Folio --}}
                        <div class="col-md-4">
                            <label for="folio" class="form-label fw-medium">Folio OC <span class="text-danger">*</span></label>
                            <input type="number" min="1"
                                   class="form-control @error('folio') is-invalid @enderror"
                                   id="folio" name="folio"
                                   value="{{ old('folio', $nextFolio) }}" required min="25000" readonly
                                   style="background-color:#f8f9fa;">
                            @error('folio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Proveedor --}}
                        <div class="col-md-8">
                            <label for="supplier_id" class="form-label fw-medium">Proveedor <span class="text-danger">*</span></label>
                            <select class="form-control @error('supplier_id') is-invalid @enderror"
                                    id="supplier_id" name="supplier_id" required>
                                <option value="">Buscar proveedor...</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}"
                                            {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
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

                        {{-- Proyecto --}}
                        <div class="col-md-6">
                            <label for="project_id" class="form-label fw-medium">Proyecto</label>
                            <select class="form-control @error('project_id') is-invalid @enderror"
                                    id="project_id" disabled
                                    style="background-color:#f8f9fa;pointer-events:none;">
                                <option value="">Seleccionar proyecto...</option>
                                @foreach ($projects as $proj)
                                    <option value="{{ $proj->id }}"
                                            {{ old('project_id', $purchaseRequest->project_id) == $proj->id ? 'selected' : '' }}>
                                        {{ $proj->name }}
                                    </option>
                                @endforeach
                            </select>
                            {{-- Hidden para que el valor se envíe con el formulario --}}
                            <input type="hidden" name="project_id" value="{{ old('project_id', $purchaseRequest->project_id) }}">
                            @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-medium">Obras a cubrir <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-light" id="btn_select_all_works">
                                    <i class="ri-checkbox-multiple-line me-1"></i>Seleccionar todas
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_clear_all_works">
                                    <i class="ri-checkbox-blank-line me-1"></i>Limpiar selección
                                </button>
                            </div>
                            <div id="selected_works_error" class="alert alert-danger py-2 fs-12 d-none mb-2">
                                Debes seleccionar al menos una obra para crear la OC.
                            </div>
                            <div class="border rounded-2 p-2" style="max-height: 220px; overflow-y: auto;">
                                @forelse ($purchaseRequest->projectWorks as $work)
                                    @php
                                        $checkedWork = in_array((string) $work->id, array_map('strval', (array) $selectedWorks), true);
                                    @endphp
                                    <div class="form-check mb-2">
                                        <input class="form-check-input js-inherit-work"
                                               type="checkbox"
                                               name="project_work_ids[]"
                                               id="work_{{ $work->id }}"
                                               value="{{ $work->id }}"
                                               {{ $checkedWork ? 'checked' : '' }}>
                                        <label class="form-check-label" for="work_{{ $work->id }}">
                                            {{ $work->name }}
                                        </label>
                                    </div>
                                @empty
                                    <p class="text-muted fs-12 mb-0">Esta SOLCOM no tiene obras vinculadas.</p>
                                @endforelse
                            </div>
                            <div class="text-muted fs-12 mt-2">
                                Seleccionadas: <strong id="selected_works_badge">{{ count((array) $selectedWorks) }}</strong>
                            </div>
                            @error('project_work_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('project_work_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- Dirección de entrega (se guarda en site de OC) --}}
                        <div class="col-12">
                            <label for="site" class="form-label fw-medium">Dirección de Entrega</label>
                            <input type="text"
                                   class="form-control @error('site') is-invalid @enderror"
                                   id="site" name="site"
                                   value="{{ old('site', $purchaseRequest->delivery_address) }}"
                                   placeholder="Dirección de entrega para esta OC">
                            @error('site')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Moneda --}}
                        <div class="col-md-4">
                            <label for="currency" class="form-label fw-medium">Moneda <span class="text-danger">*</span></label>
                            <select class="form-select @error('currency') is-invalid @enderror"
                                    id="currency" name="currency" required>
                                <option value="MXN" {{ old('currency', 'MXN') === 'MXN' ? 'selected' : '' }}>MXN — Peso Mexicano</option>
                                <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>USD — Dólar</option>
                                <option value="EUR" {{ old('currency') === 'EUR' ? 'selected' : '' }}>EUR — Euro</option>
                            </select>
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Impuesto --}}
                        @php
                            $oldIsrRate = old('isr_rate');
                            $oldRetentionIvaRate = old('retention_iva_rate');
                            $oldRetentionIsrRate = old('retention_isr_rate');
                                $oldCedularRate = old('cedular_rate');
                        @endphp
                        <div class="col-md-4">
                            <label for="tax_rate" class="form-label fw-medium">Incluye impuesto <span class="text-danger">*</span></label>
                            <select class="form-select @error('tax_rate') is-invalid @enderror"
                                    id="tax_rate" name="tax_rate" required>
                                <option value="16"     {{ old('tax_rate', '16') === '16'     ? 'selected' : '' }}>IVA 16% (predeterminado)</option>
                                <option value="8"      {{ old('tax_rate') === '8'      ? 'selected' : '' }}>IVA 8%</option>
                                <option value="0"      {{ old('tax_rate') === '0'      ? 'selected' : '' }}>0%</option>
                                <option value="exempt" {{ old('tax_rate') === 'exempt' ? 'selected' : '' }}>Exento de impuesto</option>
                            </select>
                            @error('tax_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="border rounded-2 p-3 bg-light-subtle">
                                <p class="mb-2 fw-medium fs-13">Impuestos adicionales (opcionales)</p>
                                <div class="row g-3">
                                     <div class="col-md-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input js-extra-tax-toggle" type="checkbox"
                                                   id="check_isr_rate"
                                                   data-target="isr_rate_wrapper"
                                                   {{ ($oldIsrRate !== null && $oldIsrRate !== '') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="check_isr_rate">ISR</label>
                                        </div>
                                        <div id="isr_rate_wrapper" style="display:none;">
                                            <label for="isr_rate" class="form-label fw-medium">ISR (%)</label>
                                            <input type="number" step="0.0001" min="0" max="100"
                                                   class="form-control @error('isr_rate') is-invalid @enderror"
                                                   id="isr_rate" name="isr_rate"
                                                   value="{{ $oldIsrRate }}"
                                                   disabled>
                                            @error('isr_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input js-extra-tax-toggle" type="checkbox"
                                                   id="check_retention_iva_rate"
                                                   data-target="retention_iva_rate_wrapper"
                                                   {{ ($oldRetentionIvaRate !== null && $oldRetentionIvaRate !== '') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="check_retention_iva_rate">Retenciones IVA</label>
                                        </div>
                                        <div id="retention_iva_rate_wrapper" style="display:none;">
                                            <label for="retention_iva_rate" class="form-label fw-medium">Retención IVA (%)</label>
                                            <input type="number" step="0.0001" min="0" max="100"
                                                   class="form-control @error('retention_iva_rate') is-invalid @enderror"
                                                   id="retention_iva_rate" name="retention_iva_rate"
                                                   value="{{ $oldRetentionIvaRate }}"
                                                   disabled>
                                            @error('retention_iva_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input js-extra-tax-toggle" type="checkbox"
                                                   id="check_retention_isr_rate"
                                                   data-target="retention_isr_rate_wrapper"
                                                   {{ ($oldRetentionIsrRate !== null && $oldRetentionIsrRate !== '') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="check_retention_isr_rate">Retenciones ISR</label>
                                        </div>
                                        <div id="retention_isr_rate_wrapper" style="display:none;">
                                            <label for="retention_isr_rate" class="form-label fw-medium">Retención ISR (%)</label>
                                            <input type="number" step="0.0001" min="0" max="100"
                                                   class="form-control @error('retention_isr_rate') is-invalid @enderror"
                                                   id="retention_isr_rate" name="retention_isr_rate"
                                                   value="{{ $oldRetentionIsrRate }}"
                                                   disabled>
                                            @error('retention_isr_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                        <div class="col-md-3">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input js-extra-tax-toggle" type="checkbox"
                                                       id="check_cedular_rate"
                                                       data-target="cedular_rate_wrapper"
                                                       {{ ($oldCedularRate !== null && $oldCedularRate !== '') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="check_cedular_rate">Impuesto cedular</label>
                                            </div>
                                            <div id="cedular_rate_wrapper" style="display:none;">
                                                <label for="cedular_rate" class="form-label fw-medium">Impuesto cedular (%)</label>
                                                <input type="number" step="0.0001" min="0" max="100"
                                                       class="form-control @error('cedular_rate') is-invalid @enderror"
                                                       id="cedular_rate" name="cedular_rate"
                                                       value="{{ $oldCedularRate }}"
                                                       disabled>
                                                @error('cedular_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

                        {{-- Firmas --}}
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
                                   placeholder="Se cargará al seleccionar proveedor">
                            @error('supplier_signatory')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="authorized_signatory" class="form-label fw-medium">Autorización de Pedido</label>
                            <select class="form-select @error('authorized_signatory') is-invalid @enderror"
                                    id="authorized_signatory" name="authorized_signatory">
                                <option value="">Seleccionar autorizador...</option>
                                @foreach ($authorizedSignatories as $sig)
                                    <option value="{{ $sig }}" {{ old('authorized_signatory') === $sig ? 'selected' : '' }}>
                                        {{ $sig }}
                                    </option>
                                @endforeach
                            </select>
                            @error('authorized_signatory')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Preview conceptos a importar --}}
                        <div class="col-12">
                            <hr class="my-1">
                            <h6 class="fw-semibold mb-3">
                                <i class="ri-list-check me-1 text-primary"></i>
                                Conceptos a importar
                                <span id="selected_items_badge" class="badge bg-primary-subtle text-primary ms-1">{{ count($selectedItems) }}</span>
                                <small class="text-muted fw-normal fs-12 ms-1">seleccionado(s)</small>
                            </h6>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-light" id="btn_select_all_items">
                                    <i class="ri-checkbox-multiple-line me-1"></i>Seleccionar todos
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_clear_all_items">
                                    <i class="ri-checkbox-blank-line me-1"></i>Limpiar selección
                                </button>
                                <span class="text-muted fs-12 align-self-center ms-md-auto">
                                    Subtotal estimado seleccionado: <strong id="selected_items_subtotal">0.00</strong>
                                </span>
                            </div>
                            <div id="selected_items_error" class="alert alert-danger py-2 fs-12 d-none mb-2">
                                Debes seleccionar al menos un concepto para crear la OC.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="bg-light-subtle">
                                        <tr>
                                            <th style="width:42px;"></th>
                                            <th>#</th>
                                            <th>Descripción</th>
                                            <th>Unidad</th>
                                            <th class="text-end">Cantidad</th>
                                            <th class="text-end">P/U</th>
                                            <th class="text-end">Importe</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $subtotal = 0; @endphp
                                        @forelse ($purchaseRequest->items as $i => $item)
                                            @php
                                                $qty   = (float) $item->purchase_quantity;
                                                $formattedQty = rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
                                                $price = (float) ($item->concept?->unit_price ?? 0);
                                                $total = $qty * $price;
                                                $subtotal += $total;
                                                $checked = in_array((string) $item->id, array_map('strval', (array) $selectedItems), true);
                                            @endphp
                                            <tr>
                                                <td>
                                                    <input type="checkbox"
                                                           class="form-check-input js-inherit-item"
                                                           name="selected_item_ids[]"
                                                           value="{{ $item->id }}"
                                                           data-total="{{ number_format($total, 2, '.', '') }}"
                                                           {{ $checked ? 'checked' : '' }}>
                                                </td>
                                                <td class="text-muted">{{ $i + 1 }}</td>
                                                <td>{{ $item->description }}</td>
                                                <td>{{ $item->unit }}</td>
                                                <td class="text-end">{{ $formattedQty }}</td>
                                                <td class="text-end">{{ number_format($price, 2) }}</td>
                                                <td class="text-end">{{ number_format($total, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-3">
                                                    Esta SOLCOM no tiene conceptos registrados.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if ($purchaseRequest->items->count() > 0)
                                    <tfoot>
                                        <tr class="table-light fw-semibold">
                                            <td colspan="5" class="text-end">Subtotal estimado:</td>
                                            <td class="text-end">{{ number_format($subtotal, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>

                        {{-- Acciones --}}
                        <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                            <a href="{{ route('purchasing.solcom_pile') }}" class="btn btn-light">Cancelar</a>
                            <button type="submit" class="btn btn-primary" id="createOrderFromSolcomButton">
                                <i class="ri-save-line me-1"></i>Crear Orden de Compra
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@include('purchase_orders.partials.supplier-profile-incomplete-modal')

@endsection

@push('scripts')
<script>
(function () {
    const supplierSel     = document.getElementById('supplier_id');
    const signatoryInput  = document.getElementById('supplier_signatory');

    // Inicializar Choices.js en el proveedor
    const supplierChoices = new Choices(supplierSel, {
        searchEnabled: true,
        searchPlaceholderValue: 'Buscar proveedor...',
        itemSelectText: '',
        noResultsText: 'Sin resultados',
        noChoicesText: 'Sin opciones disponibles',
    });

    // Al cambiar proveedor: precargar contacto principal en supplier_signatory
    supplierSel.addEventListener('change', function () {
        const supplierId = this.value;
        if (!supplierId) return;

        // Solo rellenar si el campo está vacío (no sobreescribir lo que el usuario ya escribió)
        fetch('/proveedores/' + supplierId + '/contacto-principal', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.name && !signatoryInput.value.trim()) {
                signatoryInput.value = data.name;
            }
        })
        .catch(() => {});
    });

    // Validar el perfil del proveedor para fincar la OC
    const orderForm = document.getElementById('formCreateOrderFromSolcom');
    const supplierReadinessUrl = @json(route('suppliers.purchase_order_readiness', ['supplier' => '__supplier__']));
    const supplierProfileModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSupplierProfileIncomplete'));
    const supplierProfileLink = document.getElementById('supplierProfileLink');
    const missingFieldsList = document.getElementById('supplierProfileMissingFields');
    const chooseAnotherSupplierButton = document.getElementById('chooseAnotherSupplier');
    const createOrderButton = document.getElementById('createOrderFromSolcomButton');
    let checkedSupplierId = '';
    let supplierIsReady = false;
    let latestReadiness = null;
    let supplierProfileLocked = false;
    let lockedControlStates = [];

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
        const controls = Array.from(orderForm.querySelectorAll('input, select, textarea, button'));

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

        syncChoicesState(supplierChoices);
    }

    function showSupplierProfileIncomplete(readiness) {
        missingFieldsList.replaceChildren();
        readiness.missing_fields.forEach(function (field) {
            const item = document.createElement('li');
            item.textContent = field;
            missingFieldsList.appendChild(item);
        });
        supplierProfileLink.href = readiness.profile_url;
        supplierProfileModal.show();
    }

    function checkSupplierReadiness() {
        const supplierId = supplierSel.value;
        checkedSupplierId = supplierId;
        supplierIsReady = false;
        latestReadiness = null;

        if (!supplierId) return;

        fetch(supplierReadinessUrl.replace('__supplier__', encodeURIComponent(supplierId)), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(readiness => {
            if (supplierSel.value !== supplierId) return;
            latestReadiness = readiness;
            supplierIsReady = readiness.ready;
            setSupplierProfileLocked(!readiness.ready);
            if (!readiness.ready) showSupplierProfileIncomplete(readiness);
        })
        .catch(() => {});
    }

    supplierSel.addEventListener('change', checkSupplierReadiness);
    orderForm.addEventListener('submit', function (event) {
        if (!supplierSel.value || (supplierIsReady && checkedSupplierId === supplierSel.value)) return;

        event.preventDefault();
        if (latestReadiness && !latestReadiness.ready) {
            showSupplierProfileIncomplete(latestReadiness);
        } else {
            checkSupplierReadiness();
        }
    });

    if (supplierSel.value) checkSupplierReadiness();

    chooseAnotherSupplierButton.addEventListener('click', function () {
        checkedSupplierId = '';
        supplierIsReady = false;
        latestReadiness = null;
        supplierSel.disabled = false;
        syncChoicesState(supplierChoices);
        supplierChoices.setChoiceByValue('');
        supplierSel.focus();
    });

    // Toggle impuestos adicionales
    function toggleExtraTaxField(checkbox) {
        const wrapper = document.getElementById(checkbox.dataset.target);
        if (!wrapper) return;
        const input = wrapper.querySelector('input');
        const enabled = checkbox.checked;
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
}());

(function () {
    const checkboxes = Array.from(document.querySelectorAll('.js-inherit-item'));
    const workCheckboxes = Array.from(document.querySelectorAll('.js-inherit-work'));
    if (!checkboxes.length) return;

    const selectedBadge = document.getElementById('selected_items_badge');
    const selectedSubtotal = document.getElementById('selected_items_subtotal');
    const selectedError = document.getElementById('selected_items_error');
    const selectedWorksBadge = document.getElementById('selected_works_badge');
    const selectedWorksError = document.getElementById('selected_works_error');
    const btnSelectAll = document.getElementById('btn_select_all_items');
    const btnClearAll = document.getElementById('btn_clear_all_items');
    const btnSelectAllWorks = document.getElementById('btn_select_all_works');
    const btnClearAllWorks = document.getElementById('btn_clear_all_works');
    const form = document.querySelector('form[action="{{ route('purchase_orders.store') }}"]');

    function updateSelectedStats() {
        let selected = 0;
        let subtotal = 0;

        checkboxes.forEach(function (cb) {
            if (!cb.checked) return;
            selected += 1;
            subtotal += parseFloat(cb.dataset.total || 0);
        });

        if (selectedBadge) selectedBadge.textContent = String(selected);
        if (selectedSubtotal) {
            selectedSubtotal.textContent = subtotal.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        if (selectedError) selectedError.classList.toggle('d-none', selected > 0);
        return selected;
    }

    function updateSelectedWorks() {
        const selected = workCheckboxes.filter(function (cb) { return cb.checked; }).length;
        if (selectedWorksBadge) selectedWorksBadge.textContent = String(selected);
        if (selectedWorksError) selectedWorksError.classList.toggle('d-none', selected > 0);
        return selected;
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateSelectedStats);
    });

    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function () {
            checkboxes.forEach(function (cb) { cb.checked = true; });
            updateSelectedStats();
        });
    }

    if (btnClearAll) {
        btnClearAll.addEventListener('click', function () {
            checkboxes.forEach(function (cb) { cb.checked = false; });
            updateSelectedStats();
        });
    }

    workCheckboxes.forEach(function (cb) {
        cb.addEventListener('change', updateSelectedWorks);
    });

    if (btnSelectAllWorks) {
        btnSelectAllWorks.addEventListener('click', function () {
            workCheckboxes.forEach(function (cb) { cb.checked = true; });
            updateSelectedWorks();
        });
    }

    if (btnClearAllWorks) {
        btnClearAllWorks.addEventListener('click', function () {
            workCheckboxes.forEach(function (cb) { cb.checked = false; });
            updateSelectedWorks();
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            const hasItems = updateSelectedStats() > 0;
            const hasWorks = updateSelectedWorks() > 0;
            if (hasItems && hasWorks) return;
            e.preventDefault();
            if (!hasItems && selectedError) {
                selectedError.classList.remove('d-none');
                selectedError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            if (!hasWorks && selectedWorksError) {
                selectedWorksError.classList.remove('d-none');
                selectedWorksError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    updateSelectedStats();
    updateSelectedWorks();
}());
</script>
@endpush
