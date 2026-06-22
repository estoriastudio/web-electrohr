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
            Los {{ $purchaseRequest->items->count() }} concepto(s) se importarán automáticamente al crear la OC.
        </p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-xl-10">
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
                <form action="{{ route('purchase_orders.store') }}" method="POST">
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

                        {{-- Obra --}}
                        <div class="col-md-6">
                            <label for="project_work_id" class="form-label fw-medium">Obra</label>
                            <select class="form-control @error('project_work_id') is-invalid @enderror"
                                    id="project_work_id" disabled
                                    style="background-color:#f8f9fa;pointer-events:none;">
                                <option value="">Seleccionar obra...</option>
                                {{-- Se llenará por JS con la obra de la SOLCOM pre-seleccionada --}}
                            </select>
                            {{-- Hidden para que el valor se envíe con el formulario --}}
                            <input type="hidden" name="project_work_id" id="project_work_id_hidden"
                                   value="{{ old('project_work_id', $purchaseRequest->project_work_id) }}">
                            @error('project_work_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

                        {{-- Estatus --}}
                        <div class="col-md-4">
                            <label for="status" class="form-label fw-medium">Estatus <span class="text-danger">*</span></label>
                            <select class="form-select @error('status') is-invalid @enderror"
                                    id="status" name="status" required>
                                <option value="emitida"    {{ old('status', 'emitida') === 'emitida'    ? 'selected' : '' }}>Emitida</option>
                                <option value="pendiente"  {{ old('status') === 'pendiente'  ? 'selected' : '' }}>Pendiente</option>
                                @role('admin')
                                <option value="autorizada" {{ old('status') === 'autorizada' ? 'selected' : '' }}>Autorizada</option>
                                @endrole
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Recurrencia --}}
                        <div class="col-12">
                            <label class="form-label fw-medium">Recurrencia <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recurrence_type"
                                           id="rec_unico" value="unico"
                                           {{ old('recurrence_type', 'unico') === 'unico' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="rec_unico">Pago único</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recurrence_type"
                                           id="rec_recurrente" value="recurrente"
                                           {{ old('recurrence_type') === 'recurrente' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="rec_recurrente">Pago recurrente</label>
                                </div>
                            </div>
                        </div>

                        {{-- Campos de recurrencia --}}
                        <div id="campos_recurrencia" style="display:none;" class="col-12">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="recurrence_frequency" class="form-label fw-medium">Frecuencia <span class="text-danger">*</span></label>
                                    <select class="form-select @error('recurrence_frequency') is-invalid @enderror"
                                            id="recurrence_frequency" name="recurrence_frequency">
                                        <option value="">Seleccionar...</option>
                                        <option value="semanal"   {{ old('recurrence_frequency') === 'semanal'   ? 'selected' : '' }}>Semanal</option>
                                        <option value="quincenal" {{ old('recurrence_frequency') === 'quincenal' ? 'selected' : '' }}>Quincenal</option>
                                        <option value="mensual"   {{ old('recurrence_frequency') === 'mensual'   ? 'selected' : '' }}>Mensual</option>
                                    </select>
                                    @error('recurrence_frequency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="recurrence_start_date" class="form-label fw-medium">Fecha inicio <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('recurrence_start_date') is-invalid @enderror"
                                           id="recurrence_start_date" name="recurrence_start_date"
                                           value="{{ old('recurrence_start_date') }}">
                                    @error('recurrence_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="recurrence_end_date" class="form-label fw-medium">Fecha fin</label>
                                    <input type="date" class="form-control @error('recurrence_end_date') is-invalid @enderror"
                                           id="recurrence_end_date" name="recurrence_end_date"
                                           value="{{ old('recurrence_end_date') }}">
                                    @error('recurrence_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
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
                                <span class="badge bg-primary-subtle text-primary ms-1">{{ $purchaseRequest->items->count() }}</span>
                                <small class="text-muted fw-normal fs-12 ms-1">(se copiarán automáticamente al crear)</small>
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="bg-light-subtle">
                                        <tr>
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
                                                $price = (float) ($item->concept?->unit_price ?? 0);
                                                $total = $qty * $price;
                                                $subtotal += $total;
                                            @endphp
                                            <tr>
                                                <td class="text-muted">{{ $i + 1 }}</td>
                                                <td>{{ $item->description }}</td>
                                                <td>{{ $item->unit }}</td>
                                                <td class="text-end">{{ number_format($qty, 2) }}</td>
                                                <td class="text-end">{{ number_format($price, 2) }}</td>
                                                <td class="text-end">{{ number_format($total, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-3">
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
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line me-1"></i>Crear Orden de Compra
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
    const projectSel      = document.getElementById('project_id');
    const workSel         = document.getElementById('project_work_id');
    const workHidden      = document.getElementById('project_work_id_hidden');
    const supplierSel     = document.getElementById('supplier_id');
    const signatoryInput  = document.getElementById('supplier_signatory');
    const solcomWorkId    = {{ $purchaseRequest->project_work_id ?? 'null' }};

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

    // Cargar obras del proyecto (solo visual, el hidden ya tiene el valor)
    function loadWorks(projectId, preselectWorkId) {
        workSel.innerHTML = '<option value="">Cargando obras…</option>';

        if (!projectId) {
            workSel.innerHTML = '<option value="">Seleccionar obra...</option>';
            return;
        }

        fetch('/proyectos/' + projectId + '/obras-json', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(works => {
            workSel.innerHTML = '<option value="">Seleccionar obra...</option>';
            works.forEach(w => {
                const opt = document.createElement('option');
                opt.value = w.id;
                opt.textContent = w.name;
                if (preselectWorkId && w.id == preselectWorkId) opt.selected = true;
                workSel.appendChild(opt);
            });
        })
        .catch(() => {
            workSel.innerHTML = '<option value="">Error al cargar obras</option>';
        });
    }

    // Carga inicial si hay proyecto pre-seleccionado
    const initialProject = projectSel.value || '{{ $purchaseRequest->project_id }}';
    if (initialProject) {
        loadWorks(initialProject, solcomWorkId);
    }

    // Toggle campos de recurrencia
    document.querySelectorAll('input[name="recurrence_type"]').forEach(function (el) {
        el.addEventListener('change', function () {
            document.getElementById('campos_recurrencia').style.display =
                this.value === 'recurrente' ? '' : 'none';
        });
    });
    const checkedRec = document.querySelector('input[name="recurrence_type"]:checked');
    if (checkedRec && checkedRec.value === 'recurrente') {
        document.getElementById('campos_recurrencia').style.display = '';
    }
}());
</script>
@endpush
