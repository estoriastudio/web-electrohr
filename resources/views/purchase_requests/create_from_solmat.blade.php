@extends('layouts.app')

@php
    $isMultiSource = $isMultiSource ?? false;
    $sourceMaterialRequests = $sourceMaterialRequests ?? collect([$materialRequest]);
    $availableProjectWorks = $availableProjectWorks ?? $materialRequest->projectWorks;
    $previewItems = $previewItems ?? $materialRequest->items->map(function ($item) {
        return [
            'concept_id' => $item->concept_id,
            'code' => $item->code,
            'description' => $item->description,
            'unit' => $item->unit,
            'resolved_quantity' => (float) $item->total_quantity,
            'work_quantities' => $item->workQuantities->map(fn ($row) => [
                'work_id' => (string) $row->project_work_id,
                'quantity' => (float) $row->quantity,
            ])->values()->all(),
            'source_folios' => [(int) $materialRequest->folio],
        ];
    });

    $sourceMaterialRequestIds = $sourceMaterialRequestIds
        ?? $sourceMaterialRequests->pluck('id')->map(fn ($id) => (int) $id)->all();

    $defaultSelectedWorks = $defaultSelectedWorks
        ?? $availableProjectWorks->pluck('id')->map(fn ($id) => (string) $id)->all();

    $selectedWorks = old('project_work_ids', $defaultSelectedWorks);
    $selectedItemKeys = array_map('strval', (array) old(
        'selected_item_keys',
        $previewItems->pluck('item_key')->all()
    ));

    $pageTitle = $isMultiSource
        ? 'Crear SOLCOM consolidada (' . $sourceMaterialRequests->count() . ' SOLMAT)'
        : 'Crear SOLCOM desde SOLMAT #' . $materialRequest->folio;
@endphp

@section('page_title', $pageTitle)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('warehouse.solmat_pile') }}">Pila SOLMAT</a></li>
    @if ($isMultiSource)
        <li class="breadcrumb-item active">Crear SOLCOM consolidada</li>
    @else
        <li class="breadcrumb-item active">Crear SOLCOM desde SOLMAT #{{ $materialRequest->folio }}</li>
    @endif
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
    <i class="ri-links-line fs-20 shrink-0 text-info"></i>
    <div>
        <p class="mb-0 fw-semibold fs-13">
            @if ($isMultiSource)
                Generando SOLCOM consolidada a partir de {{ $sourceMaterialRequests->count() }} SOLMAT
            @else
                Generando SOLCOM a partir de
                <a href="{{ route('material_requests.show', $materialRequest) }}" class="text-info" target="_blank">
                    SOLMAT #{{ $materialRequest->folio }}
                </a>
                — {{ $materialRequest->supply_category }}
            @endif
        </p>
        <p class="mb-0 text-muted fs-12">
            Se importarán {{ $previewItems->count() }} concepto(s) en la SOLCOM resultante.
        </p>
        @if ($isMultiSource)
            <div class="mt-2 d-flex flex-wrap gap-1">
                @foreach ($sourceMaterialRequests as $sourceMaterialRequest)
                    <a href="{{ route('material_requests.show', $sourceMaterialRequest) }}" class="badge bg-info-subtle text-info border" target="_blank">
                        SOLMAT #{{ $sourceMaterialRequest->folio }}
                    </a>
                @endforeach
            </div>
        @endif
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
                    @if ($isMultiSource)
                        @foreach ($sourceMaterialRequestIds as $sourceMaterialRequestId)
                            <input type="hidden" name="material_request_ids[]" value="{{ $sourceMaterialRequestId }}">
                        @endforeach
                    @else
                        <input type="hidden" name="material_request_id" value="{{ $materialRequest->id }}">
                    @endif
                    <input type="hidden"
                           name="clear_solmat_pile_selection_on_success"
                           value="{{ !empty($clearSolmatPileSelectionOnSuccess) ? 1 : 0 }}">

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
                            <select id="solcomProjectId"
                                    class="form-select @error('project_id') is-invalid @enderror" disabled
                                    style="background-color:#f8f9fa;pointer-events:none;">
                                <option value="">— Selecciona proyecto —</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}"
                                        {{ old('project_id', $materialRequest->project_id) == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="project_id" value="{{ old('project_id', $materialRequest->project_id) }}">
                            @error('project_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">El proyecto se hereda de la SOLMAT origen y no puede modificarse.</div>
                        </div>

                        {{-- Obras vinculadas de SOLMAT --}}
                        <div class="col-12">
                            <label class="form-label">Obras vinculadas <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-light" id="btn_select_all_works">
                                    <i class="ri-checkbox-multiple-line me-1"></i>Seleccionar todas
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_clear_all_works">
                                    <i class="ri-checkbox-blank-line me-1"></i>Limpiar selección
                                </button>
                                <span class="text-muted fs-12 align-self-center ms-md-auto">
                                    Obras seleccionadas: <strong id="selected_works_badge">{{ count((array) $selectedWorks) }}</strong>
                                </span>
                            </div>
                            <div id="selected_works_error" class="alert alert-danger py-2 fs-12 d-none mb-2">
                                Debes seleccionar al menos una obra para crear la SOLCOM.
                            </div>
                            <div class="table-responsive border rounded-2">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="bg-light-subtle">
                                        <tr>
                                            <th style="width:42px;"></th>
                                            <th>Obra</th>
                                            <th class="text-end">Proyecto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($availableProjectWorks as $pw)
                                            @php
                                                $checked = in_array((string) $pw->id, array_map('strval', (array) $selectedWorks), true);
                                            @endphp
                                            <tr>
                                                <td>
                                                    <input type="checkbox"
                                                           class="form-check-input js-solcom-work"
                                                           name="project_work_ids[]"
                                                           value="{{ $pw->id }}"
                                                           {{ $checked ? 'checked' : '' }}>
                                                </td>
                                                <td class="fw-medium">{{ $pw->name }}</td>
                                                <td class="text-end text-muted">{{ $materialRequest->project?->name }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-3">
                                                    Esta SOLMAT no tiene obras vinculadas.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @error('project_work_ids') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @error('project_work_ids.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <div class="form-text">
                                La SOLCOM puede cubrir una o varias obras de las SOLMAT seleccionadas.
                            </div>
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
                                   value="{{ old('need_date', now()->format('Y-m-d')) }}" required>
                            @error('need_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Preview de conceptos a importar --}}
                        <div class="col-12">
                            <hr>
                            <h6 class="fw-semibold mb-3">
                                <i class="ri-list-check me-1 text-primary"></i>
                                Conceptos disponibles para importar
                                <span class="badge bg-primary-subtle text-primary ms-1">{{ $previewItems->count() }}</span>
                                <span class="badge bg-light text-dark border ms-1" id="selected_items_badge">{{ count($selectedItemKeys) }} seleccionados</span>
                            </h6>
                            <div id="selected_items_error" class="alert alert-danger py-2 fs-12 d-none mb-2">
                                Debes seleccionar al menos un concepto disponible para crear la SOLCOM.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="bg-light-subtle">
                                        <tr>
                                            <th style="width:42px;"></th>
                                            <th>#</th>
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th>Origen</th>
                                            <th>Unidad</th>
                                            <th class="text-end">Comprometido</th>
                                            <th class="text-end">Cantidad disponible</th>
                                            <th class="text-end">Cantidad SOLCOM</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($previewItems as $i => $item)
                                            @php
                                                $itemWorkQuantities = collect($item['work_quantities'] ?? [])->map(fn ($row) => [
                                                    'work_id' => (string) ($row['work_id'] ?? ''),
                                                    'quantity' => (float) ($row['quantity'] ?? 0),
                                                ])->values();
                                                $itemCommittedWorkQuantities = collect($item['committed_work_quantities'] ?? [])->map(fn ($row) => [
                                                    'work_id' => (string) ($row['work_id'] ?? ''),
                                                    'quantity' => (float) ($row['quantity'] ?? 0),
                                                ])->values();
                                                $sourceFolios = collect($item['source_folios'] ?? [])->values();
                                                $resolvedQuantity = (float) ($item['resolved_quantity'] ?? 0);
                                                $committedQuantity = (float) ($item['committed_quantity'] ?? 0);
                                                $itemKey = (string) ($item['item_key'] ?? 'item:' . $i);
                                                $isSelected = in_array($itemKey, $selectedItemKeys, true);
                                            @endphp
                                            <tr class="js-solcom-item-row"
                                                data-item-total="{{ $resolvedQuantity }}"
                                                data-item-work-quantities='@json($itemWorkQuantities)'
                                                data-item-committed-work-quantities='@json($itemCommittedWorkQuantities)'>
                                                <td>
                                                    <input type="checkbox"
                                                           class="form-check-input js-solcom-item"
                                                           name="selected_item_keys[]"
                                                           value="{{ $itemKey }}"
                                                           id="solcom_item_{{ $i }}"
                                                           @checked($isSelected)>
                                                </td>
                                                <td class="text-muted">{{ $i + 1 }}</td>
                                                <td><span class="fw-semibold">{{ $item['code'] ?? '—' }}</span></td>
                                                <td>{{ $item['description'] ?? '—' }}</td>
                                                <td>
                                                    @if ($sourceFolios->isNotEmpty())
                                                        @foreach ($sourceFolios as $folio)
                                                            <span class="badge bg-light text-muted border">#{{ $folio }}</span>
                                                        @endforeach
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>{{ $item['unit'] ?? '—' }}</td>
                                                <td class="text-end js-solmat-committed-qty">
                                                    @if ($committedQuantity > 0)
                                                        <span class="badge bg-warning-subtle text-warning">{{ number_format($committedQuantity, 2, '.', '') }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-end js-solmat-total-qty">{{ number_format($resolvedQuantity, 2, '.', '') }}</td>
                                                <td class="text-end fw-semibold text-primary js-solcom-total-qty">0.00</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-3">
                                                    No hay conceptos disponibles para las obras seleccionadas.
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
    const workCheckboxes = Array.from(document.querySelectorAll('.js-solcom-work'));
    const selectedWorksBadge = document.getElementById('selected_works_badge');
    const selectedWorksError = document.getElementById('selected_works_error');
    const btnSelectAllWorks = document.getElementById('btn_select_all_works');
    const btnClearAllWorks = document.getElementById('btn_clear_all_works');
    const form = document.getElementById('formCreateSolcom');
    const itemRows = Array.from(document.querySelectorAll('.js-solcom-item-row'));
    const itemCheckboxes = Array.from(document.querySelectorAll('.js-solcom-item'));
    const selectedItemsBadge = document.getElementById('selected_items_badge');
    const selectedItemsError = document.getElementById('selected_items_error');

    function selectedWorkIds() {
        return workCheckboxes.filter(function (cb) { return cb.checked; }).map(function (cb) {
            return String(cb.value);
        });
    }

    function formatQty(value) {
        return Number(value || 0).toFixed(2);
    }

    function updateItemQuantities() {
        const selectedIds = selectedWorkIds();

        itemRows.forEach(function (row) {
            const totalCell = row.querySelector('.js-solcom-total-qty');
            const committedCell = row.querySelector('.js-solmat-committed-qty');
            const total = parseFloat(row.getAttribute('data-item-total') || '0');
            let selectedTotal = 0;
            let selectedCommitted = 0;

            try {
                const workQuantities = JSON.parse(row.getAttribute('data-item-work-quantities') || '[]');
                if (Array.isArray(workQuantities) && workQuantities.length > 0) {
                    selectedTotal = workQuantities.reduce(function (acc, entry) {
                        return selectedIds.includes(String(entry.work_id))
                            ? acc + parseFloat(entry.quantity || 0)
                            : acc;
                    }, 0);
                    const committedWorkQuantities = JSON.parse(row.getAttribute('data-item-committed-work-quantities') || '[]');
                    selectedCommitted = committedWorkQuantities.reduce(function (acc, entry) {
                        return selectedIds.includes(String(entry.work_id))
                            ? acc + parseFloat(entry.quantity || 0)
                            : acc;
                    }, 0);
                    if (selectedIds.length === 0) {
                        selectedTotal = 0;
                        selectedCommitted = 0;
                    }
                } else {
                    selectedTotal = total;
                }
            } catch (error) {
                selectedTotal = total;
            }

            if (totalCell) {
                totalCell.textContent = formatQty(selectedTotal);
            }
            if (committedCell) {
                committedCell.innerHTML = selectedCommitted > 0
                    ? '<span class="badge bg-warning-subtle text-warning">' + formatQty(selectedCommitted) + '</span>'
                    : '<span class="text-muted">—</span>';
            }

            const itemCheckbox = row.querySelector('.js-solcom-item');
            if (itemCheckbox) {
                itemCheckbox.disabled = selectedTotal <= 0;
                if (itemCheckbox.disabled) {
                    itemCheckbox.checked = false;
                }
                row.classList.toggle('opacity-50', itemCheckbox.disabled);
            }
        });

        updateSelectedItems();
    }

    function updateSelectedItems() {
        const selectedCount = itemCheckboxes.filter(function (checkbox) {
            return !checkbox.disabled && checkbox.checked;
        }).length;

        if (selectedItemsBadge) {
            selectedItemsBadge.textContent = String(selectedCount) + ' seleccionados';
        }
        if (selectedItemsError) {
            selectedItemsError.classList.toggle('d-none', selectedCount > 0);
        }

        return selectedCount;
    }

    function updateSelectedWorks() {
        const selectedCount = workCheckboxes.filter(function (cb) { return cb.checked; }).length;
        if (selectedWorksBadge) {
            selectedWorksBadge.textContent = String(selectedCount);
        }
        if (selectedWorksError) {
            selectedWorksError.classList.toggle('d-none', selectedCount > 0);
        }
        updateItemQuantities();
        return selectedCount;
    }

    workCheckboxes.forEach(function (cb) {
        cb.addEventListener('change', updateSelectedWorks);
    });

    itemCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelectedItems);
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
        form.addEventListener('submit', function (event) {
            if (updateSelectedWorks() > 0 && updateSelectedItems() > 0) {
                return;
            }
            event.preventDefault();
            if (selectedWorksError) {
                selectedWorksError.classList.remove('d-none');
                selectedWorksError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            if (updateSelectedItems() === 0 && selectedItemsError) {
                selectedItemsError.classList.remove('d-none');
                selectedItemsError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    updateSelectedWorks();
}());
</script>
@endpush
