@extends('layouts.app')

@section('page_title', 'OC #' . ($purchaseOrder->folio ?? $purchaseOrder->id) . ' — Orden de Compra')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase_orders.index') }}">Órdenes de Compra</a></li>
    <li class="breadcrumb-item active">OC #{{ $purchaseOrder->folio ?? $purchaseOrder->id }}</li>
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

@php
    $statusMap = [
        'emitida'    => ['label' => 'Emitida',    'class' => 'bg-info-subtle text-info'],
        'pendiente'  => ['label' => 'Pendiente',  'class' => 'bg-warning-subtle text-warning'],
        'autorizada' => ['label' => 'Autorizada', 'class' => 'bg-success-subtle text-success'],
    ];
    $s = $statusMap[$purchaseOrder->status] ?? ['label' => $purchaseOrder->status, 'class' => 'bg-secondary-subtle text-secondary'];

    $tipoLabel = $purchaseOrder->type === 'materiales_servicios' ? 'Materiales / Servicios' : 'Mantenimiento';
    $totalCubierto = $purchaseOrder->saldo_cubierto;
    $importeTotal  = $purchaseOrder->total_with_iva;
    $progressTotal = $importeTotal > 0
        ? min(100, round(($totalCubierto / $importeTotal) * 100, 1))
        : 0;

    $isAdmin = auth()->user()?->hasRole('admin') ?? false;
    $canModifyPurchaseOrder = $purchaseOrder->status !== 'autorizada' || $isAdmin;
    $orderedMilestones = $purchaseOrder->milestones->sortBy('id')->values();
    $milestonePositionMap = $orderedMilestones->pluck('id')->flip()->map(fn ($idx) => $idx + 1);
    $groupedEvidences = $purchaseOrder->evidences
        ->sortByDesc('created_at')
        ->groupBy(fn ($e) => optional($e->created_at)->format('Y-m-d') ?? 'sin-fecha');
    $minDueDate = now()->format('Y-m-d');
    $conceptSearchType = $purchaseOrder->type === 'mantenimiento' ? 'mantenimiento' : 'materiales';
    $isDelivered = (bool) $purchaseOrder->is_delivered;
    $deliveryBadge = $isDelivered
        ? ['label' => 'Entregado', 'class' => 'bg-success-subtle text-success', 'icon' => 'ri-check-line']
        : ['label' => 'Por entregar', 'class' => 'bg-warning-subtle text-warning', 'icon' => 'ri-truck-line'];
@endphp

{{-- ── ALERTA DE AUTORIZACIÓN ── --}}
@if (in_array($purchaseOrder->status, ['emitida', 'pendiente']))
    <div id="purchaseOrderApproveAlert" class="alert alert-warning alert-dismissible d-flex align-items-start gap-3 mb-3 oc-approve-alert" role="alert">
        <i class="ri-shield-check-line fs-22 mt-1 text-warning"></i>
        <div class="flex-grow-1">
            <h6 class="alert-heading mb-1 fw-semibold">Orden de compra pendiente de autorización</h6>
            <p class="mb-0 fs-13">
                Esta OC se encuentra en estatus
                <span class="badge {{ $s['class'] }} py-1 px-2 fs-12 ms-1">{{ $s['label'] }}</span>.
                Requiere revisión y aprobación de un administrador antes de proceder con los pagos.
            </p>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                <span class="badge bg-dark-subtle text-dark py-1 px-2 fs-12">
                    Pendientes por autorizar: {{ $pendingAuthCount ?? 0 }}
                </span>
                @if (!empty($nextPendingPurchaseOrder))
                    <a href="{{ route('purchase_orders.show', $nextPendingPurchaseOrder->id) }}"
                       class="btn btn-sm btn-outline-dark py-1 px-2"
                       title="Ir a la siguiente OC pendiente">
                        <i class="ri-arrow-right-line me-1"></i>Siguiente OC
                    </a>
                @endif
            </div>
        </div>
        @role('admin')
        <form id="approvePurchaseOrderForm" action="{{ route('purchase_orders.approve', $purchaseOrder) }}" method="POST" class="align-self-center">
            @csrf @method('PATCH')
            <button type="submit"
                    id="approvePurchaseOrderBtn"
                    class="btn btn-success btn-sm">
                <i class="ri-check-double-line me-1"></i> Autorizar OC
            </button>
        </form>
        @endrole
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <div id="approveInlineProgress" class="oc-approve-inline-progress" aria-hidden="true">
            <div class="oc-approve-inline-progress-bar" aria-hidden="true"></div>
            <span id="approveInlineProgressText" class="oc-approve-inline-progress-text">Avanzando a la siguiente...</span>
        </div>
    </div>
@endif

{{-- ── ENCABEZADO ── --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1 fw-semibold">
                            <i class="ri-file-list-3-line me-1 text-primary"></i>
                            Orden de Compra
                            <span class="text-primary">Folio #{{ $purchaseOrder->folio ?? '—' }}</span>
                        </h5>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $tipoLabel }}</span>
                            <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
                            <span class="badge {{ $deliveryBadge['class'] }} py-1 px-2 fs-12">
                                <i class="{{ $deliveryBadge['icon'] }} me-1"></i>{{ $deliveryBadge['label'] }}
                            </span>
                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">{{ $purchaseOrder->currency }}</span>
                        </div>
                        @if ($purchaseOrder->type === 'materiales_servicios' && ($purchaseOrder->project || $purchaseOrder->site))
                            <p class="card-text text-muted fs-13 mb-0 mt-1">
                                @if ($purchaseOrder->project)<span><i class="ri-building-2-line me-1"></i>{{ $purchaseOrder->project }}</span>@endif
                                @if ($purchaseOrder->project && $purchaseOrder->site) &nbsp;·&nbsp; @endif
                                @if ($purchaseOrder->site)<span><i class="ri-tools-line me-1"></i>{{ $purchaseOrder->site }}</span>@endif
                            </p>
                        @endif
                        @if ($purchaseOrder->type === 'mantenimiento' && $purchaseOrder->mobileAsset)
                            <p class="card-text text-muted fs-13 mb-0 mt-1">
                                <span><i class="ri-tools-line me-1"></i>{{ $purchaseOrder->mobileAsset->name }}{{ $purchaseOrder->mobileAsset->folio ? ' — ' . $purchaseOrder->mobileAsset->folio : '' }}</span>
                            </p>
                        @endif

                        <div class="mt-2">
                            @include('layouts.partials._process_map', ['purchaseOrder' => $purchaseOrder])
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-22 fw-semibold text-primary" aria-label="Importe Total de la OC">
                            {{ $purchaseOrder->currency }} {{ number_format($importeTotal, 2) }}
                        </div>
                        <div class="text-muted fs-13">
                            Cubierto: <strong>{{ number_format($totalCubierto, 2) }}</strong>
                        </div>
                        <div class="mt-1" style="min-width: 160px;">
                            <div class="progress" style="height: 8px;">º
                                <div class="progress-bar bg-success" style="width: {{ $progressTotal }}%"></div>
                            </div>
                            <small class="text-muted">{{ $progressTotal }}% cubierto</small>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#modalPdfAnnexes">
                            <i class="ri-file-pdf-2-line me-1"></i> Generar PDF
                        </button>
                        @hasanyrole('admin|Solmat|Orden de compra')
                        <button type="button" class="btn btn-sm btn-outline-success"
                                data-bs-toggle="modal" data-bs-target="#modalDeliveryStatus">
                            <i class="ri-truck-line me-1"></i> Cambiar entrega
                        </button>
                        @endhasanyrole
                        @hasanyrole('admin|Orden de compra')
                        @if ($canModifyPurchaseOrder)
                        <a href="{{ route('purchase_orders.edit', $purchaseOrder) }}" class="btn btn-sm btn-outline-primary">
                            <i class="ri-edit-line me-1"></i> Editar OC
                        </a>
                        @else
                        <span class="badge bg-success-subtle text-success border border-success py-2 px-3 fs-12">
                            <i class="ri-lock-line me-1"></i>OC Autorizada
                        </span>
                        @endif
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateMilestone">
                            <i class="ri-add-line me-1"></i> Agregar condición de pago
                        </button>
                        @endhasanyrole
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL Subir Evidencia --}}
@hasanyrole('admin|Solmat|Pagos|Orden de compra')
<div class="modal fade" id="modalCreateEvidence" tabindex="-1" aria-labelledby="modalCreateEvidenceLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('evidences.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateEvidenceLabel">
                        <i class="ri-image-add-line me-1 text-info"></i> Subir evidencia — OC #{{ $purchaseOrder->folio ?? $purchaseOrder->id }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Archivo de evidencia <span class="text-danger">*</span></label>
                            <input type="file"
                                   class="form-control @error('evidence_file') is-invalid @enderror"
                                   name="evidence_file"
                                   accept=".pdf,.jpg,.jpeg,.png,.webp"
                                   required>
                            <div class="form-text">Formatos permitidos: PDF, JPG, PNG, WEBP. Máximo 10 MB.</div>
                            @error('evidence_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-medium">Hito relacionado</label>
                            <select name="purchase_order_milestone_id" class="form-select @error('purchase_order_milestone_id') is-invalid @enderror">
                                <option value="">Sin hito específico</option>
                                @foreach ($orderedMilestones as $m)
                                    <option value="{{ $m->id }}">
                                        Hito #{{ $loop->iteration }} — {{ $m->concept ?: ($m->type === 'anticipo' ? 'Anticipo' : 'Regular') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('purchase_order_milestone_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-medium">Descripción</label>
                            <input type="text"
                                   name="description"
                                   maxlength="255"
                                   class="form-control @error('description') is-invalid @enderror"
                                   placeholder="Ej. Entrada de almacén y evidencia de recepción">
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info">
                        <i class="ri-upload-2-line me-1"></i> Subir evidencia
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endhasanyrole

{{-- MODAL Cambiar Estatus de Entrega --}}
@hasanyrole('admin|Solmat|Orden de compra')
<div class="modal fade" id="modalDeliveryStatus" tabindex="-1" aria-labelledby="modalDeliveryStatusLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('purchase_orders.delivery_status.update', $purchaseOrder) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDeliveryStatusLabel">
                        <i class="ri-truck-line me-1 text-success"></i> Estatus de entrega
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13 mb-3">
                        Define manualmente el estatus de entrega para la OC #{{ $purchaseOrder->folio ?? $purchaseOrder->id }}.
                    </p>
                    <label for="is_delivered" class="form-label fw-medium">Estatus de entrega <span class="text-danger">*</span></label>
                    <select id="is_delivered" name="is_delivered" class="form-select" required>
                        <option value="0" {{ $isDelivered ? '' : 'selected' }}>Por entregar</option>
                        <option value="1" {{ $isDelivered ? 'selected' : '' }}>Entregado</option>
                    </select>
                    <div class="form-text">Este estatus es independiente del estatus de la OC (Emitida, Pendiente, Autorizada).</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="ri-save-line me-1"></i> Guardar estatus de entrega
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endhasanyrole

{{-- ── CONCEPTOS ── --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center py-3">
                <h5 class="card-title mb-0">
                    <i class="ri-list-check me-1 text-primary"></i> Conceptos
                    <span id="oc_items_badge" class="badge bg-primary-subtle text-primary ms-1 fs-12">{{ $purchaseOrder->items->count() }}</span>
                </h5>
                @hasanyrole('admin|Orden de compra')
                @if ($canModifyPurchaseOrder)
                <button type="button" class="btn btn-sm btn-primary" id="btn_toggle_add_concept">
                    <i class="ri-add-line me-1"></i> Agregar concepto
                </button>
                @endif
                @endhasanyrole
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    @php $ocLocked = $purchaseOrder->status === 'autorizada' && !$isAdmin; @endphp

                    <table class="table align-middle table-hover mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th class="text-muted fs-12">#</th>
                                <th>Concepto</th>
                                <th>Unidad</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">P/U</th>
                                <th class="text-end fw-semibold">Importe</th>
                                <th>Fecha Entrega</th>
                                @if (!$ocLocked)
                                <th></th>
                                @endif
                            </tr>
                        </thead>
                        
                        <tbody id="oc_items_tbody">
                            @forelse ($purchaseOrder->items as $idx => $item)
                                <tr class="oc-item-row" data-item-id="{{ $item->id }}">
                                    <td class="oc-row-num text-muted fs-12">{{ $idx + 1 }}</td>
                                    <td>{{ $item->description }}</td>
                                    <td class="fs-12">{{ $item->unit }}</td>
                                    <td class="text-end" style="min-width:120px;">
                                        @hasanyrole('admin|Orden de compra')
                                        @if (!$ocLocked)
                                        <input type="number" step="0.01" min="0"
                                               class="form-control form-control-sm text-end oc-qty-input"
                                               style="max-width:100px;display:inline-block;"
                                               value="{{ $item->quantity }}"
                                               data-item-id="{{ $item->id }}"
                                               data-field="quantity"
                                               data-original="{{ $item->quantity }}">
                                        @else
                                        {{ number_format($item->quantity, 2) }}
                                        @endif
                                        @else
                                        {{ number_format($item->quantity, 2) }}
                                        @endhasanyrole
                                    </td>
                                    <td class="text-end" style="min-width:140px;">
                                        @hasanyrole('admin|Orden de compra')
                                        @if (!$ocLocked)
                                        <div class="input-group input-group-sm" style="max-width:130px;display:inline-flex;">
                                            <span class="input-group-text py-0 px-2">$</span>
                                              <input type="text" inputmode="decimal" autocomplete="off"
                                                  class="form-control form-control-sm text-end oc-qty-input oc-price-input"
                                                   value="{{ $item->unit_price }}"
                                                   data-item-id="{{ $item->id }}"
                                                   data-field="unit_price"
                                                   data-original="{{ $item->unit_price }}">
                                        </div>
                                        @else
                                        ${{ $item->unit_price }}
                                        @endif
                                        @else
                                        ${{ $item->unit_price }}
                                        @endhasanyrole
                                    </td>
                                    <td class="text-end fw-semibold oc-importe">
                                        ${{ number_format($item->total, 2) }}
                                    </td>
                                    <td style="min-width:150px;">
                                        @hasanyrole('admin|Orden de compra')
                                        @if (!$ocLocked)
                                        <input type="text" maxlength="80"
                                               class="form-control form-control-sm oc-delivery-input"
                                               value="{{ $item->delivery_date ?? '' }}"
                                               data-item-id="{{ $item->id }}"
                                               data-original="{{ $item->delivery_date ?? '' }}"
                                               placeholder="Ej. 4 SEMANAS">
                                        @else
                                        {{ $item->delivery_date ?? '—' }}
                                        @endif
                                        @else
                                        {{ $item->delivery_date ?? '—' }}
                                        @endhasanyrole
                                    </td>
                                    @if (!$ocLocked)
                                    <td>
                                        @hasanyrole('admin|Orden de compra')
                                        <button type="button" class="btn btn-soft-danger btn-sm oc-item-delete"
                                                data-item-id="{{ $item->id }}" title="Eliminar">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                        @endhasanyrole
                                    </td>
                                    @endif
                                </tr>
                            @empty
                                <tr id="oc_empty_row">
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="ri-inbox-line fs-4 d-block mb-1 opacity-50"></i>
                                        Sin conceptos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Tabla de totales --}}
                <div class="d-flex justify-content-end px-3 py-2 border-top">
                    <table class="table table-sm mb-0" style="width:300px;">
                        <tbody>
                            <tr>
                                <td class="text-muted fs-12">Subtotal</td>
                                <td class="text-end fw-medium" id="oc_subtotal">${{ number_format($purchaseOrder->subtotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted fs-12" id="oc_iva_label">
                                    @if (is_null($purchaseOrder->tax_rate))
                                        Impuesto (Exento)
                                    @else
                                        IVA ({{ rtrim(rtrim(number_format((float)$purchaseOrder->tax_rate, 2), '0'), '.') }}%)
                                    @endif
                                </td>
                                <td class="text-end fw-medium" id="oc_iva">${{ number_format($purchaseOrder->iva, 2) }}</td>
                            </tr>
                            <tr id="oc_isr_row" @if (is_null($purchaseOrder->isr_rate)) style="display:none;" @endif>
                                <td class="text-muted fs-12" id="oc_isr_label">
                                    ISR ({{ rtrim(rtrim(number_format((float) ($purchaseOrder->isr_rate ?? 0), 4), '0'), '.') }}%)
                                </td>
                                <td class="text-end fw-medium" id="oc_isr">${{ number_format($purchaseOrder->isr_amount, 2) }}</td>
                            </tr>
                            <tr id="oc_retention_iva_row" @if (is_null($purchaseOrder->retention_iva_rate)) style="display:none;" @endif>
                                <td class="text-muted fs-12" id="oc_retention_iva_label">
                                    Retenciones IVA ({{ rtrim(rtrim(number_format((float) ($purchaseOrder->retention_iva_rate ?? 0), 4), '0'), '.') }}%)
                                </td>
                                <td class="text-end fw-medium" id="oc_retention_iva">${{ number_format($purchaseOrder->retention_iva_amount, 2) }}</td>
                            </tr>
                            <tr id="oc_retention_isr_row" @if (is_null($purchaseOrder->retention_isr_rate)) style="display:none;" @endif>
                                <td class="text-muted fs-12" id="oc_retention_isr_label">
                                    Retenciones ISR ({{ rtrim(rtrim(number_format((float) ($purchaseOrder->retention_isr_rate ?? 0), 4), '0'), '.') }}%)
                                </td>
                                <td class="text-end fw-medium" id="oc_retention_isr">${{ number_format($purchaseOrder->retention_isr_amount, 2) }}</td>
                            </tr>
                            <tr class="border-top">
                                <td class="fw-bold fs-14">Total</td>
                                <td class="text-end fw-bold fs-14 text-primary" id="oc_total">${{ number_format($purchaseOrder->total_with_iva, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Panel agregar concepto --}}
                @hasanyrole('admin|Orden de compra')
                @if ($canModifyPurchaseOrder)
                <div id="oc_add_panel" class="border-top px-3 py-3" style="display:none;">
                    <p class="text-muted fs-12 fw-medium mb-2">
                        <i class="ri-add-circle-line me-1 text-primary"></i>Nuevo concepto
                    </p>

                    {{-- Estado búsqueda --}}
                    <div id="oc_state_search">
                        <div class="position-relative">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="ri-search-line text-muted"></i>
                                </span>
                                <input type="text" id="oc_search_input"
                                       class="form-control border-start-0 ps-0"
                                       placeholder="Buscar concepto por código o descripción…"
                                       autocomplete="off">
                            </div>
                            <ul id="oc_search_dropdown"
                                class="list-group position-absolute w-100 shadow d-none"
                                style="top:100%;left:0;max-height:260px;overflow-y:auto;z-index:1050;"></ul>
                        </div>
                        <div class="mt-2 d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="oc_btn_add_manual">
                                <i class="ri-pencil-line me-1"></i>Ingresar manualmente
                            </button>
                        </div>
                    </div>

                    {{-- Estado concepto seleccionado --}}
                    <div id="oc_state_selected" class="d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-success fs-13 fw-medium">
                                <i class="ri-checkbox-circle-line me-1"></i>Concepto seleccionado
                            </span>
                            <button type="button" id="oc_btn_change" class="btn btn-link btn-sm p-0 text-muted text-decoration-none">
                                <i class="ri-close-line me-1"></i>Cambiar
                            </button>
                        </div>
                        <div class="rounded-2 border bg-primary-subtle p-3 mb-3">
                            <p class="fw-bold mb-1" id="oc_preview_code"></p>
                            <p class="mb-1 text-body-secondary" id="oc_preview_desc"></p>
                            <span class="badge bg-white text-dark border" id="oc_preview_unit"></span>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fs-12 fw-medium mb-1">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" id="oc_inp_qty" class="form-control" step="0.01" min="0.01" placeholder="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs-12 fw-medium mb-1">P/U <span class="text-danger">*</span></label>
                                <input type="number" id="oc_inp_price" class="form-control" step="any" min="0" placeholder="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs-12 fw-medium mb-1">Fecha Entrega</label>
                                <input type="text" id="oc_inp_delivery" class="form-control" maxlength="80" placeholder="Ej. 4 SEMANAS">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div id="oc_add_error" class="text-danger fs-12 mb-2 d-none"></div>
                                <button type="button" id="oc_btn_save_item" class="btn btn-primary w-100">
                                    <i class="ri-add-line me-1"></i>Agregar
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Estado manual (sin concepto del catálogo) --}}
                    <div id="oc_state_manual" class="d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted fs-13 fw-medium">
                                <i class="ri-pencil-line me-1"></i>Concepto libre (sin catálogo)
                            </span>
                            <button type="button" id="oc_btn_back_search" class="btn btn-link btn-sm p-0 text-muted text-decoration-none">
                                <i class="ri-arrow-left-line me-1"></i>Buscar catálogo
                            </button>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fs-12 fw-medium mb-1">Descripción <span class="text-danger">*</span></label>
                                <input type="text" id="oc_inp_manual_desc" class="form-control" maxlength="255" placeholder="Descripción del concepto">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fs-12 fw-medium mb-1">Unidad <span class="text-danger">*</span></label>
                                <input type="text" id="oc_inp_manual_unit" class="form-control" maxlength="50" placeholder="PZA">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fs-12 fw-medium mb-1">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" id="oc_inp_manual_qty" class="form-control" step="0.01" min="0.01" placeholder="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fs-12 fw-medium mb-1">P/U <span class="text-danger">*</span></label>
                                <input type="number" id="oc_inp_manual_price" class="form-control" step="any" min="0" placeholder="0.00">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fs-12 fw-medium mb-1">Fecha Entrega</label>
                                <input type="text" id="oc_inp_manual_delivery" class="form-control" maxlength="80" placeholder="4 SEMANAS">
                            </div>
                        </div>
                        <button type="button" id="oc_btn_save_manual" class="btn btn-primary">
                            <i class="ri-add-line me-1"></i>Agregar concepto
                        </button>
                    </div>
                </div>
                @endif
                @endhasanyrole
            </div>
        </div>
    </div>
</div>

{{-- ── HITOS (CONDICIONES DE PAGO) ── --}}
<div class="row" id="hitos-container">
    @forelse ($orderedMilestones as $milestone)
        @php
            $milestonePosition = $loop->iteration;
            $percent     = $milestone->progress_percent;
            $isComplete  = $milestone->is_complete;
            $hasInvoice  = $milestone->invoices->isNotEmpty();
            $progressClass = $isComplete ? 'bg-success' : ($percent >= 50 ? 'bg-warning' : 'bg-danger');
            $tipoHitoMap = [
                'anticipo' => 'Anticipo',
                'regular'  => 'Pago Regular',
                'contado'  => 'Contado',
                'credito'  => 'Crédito',
            ];
            $tipoCondicionMap = ['contado' => 'Contado', 'credito' => 'Crédito'];
            $tipoValorMap = ['fijo' => 'Monto Fijo', 'porcentaje' => 'Porcentaje'];

            $today      = \Carbon\Carbon::today();
            $urgentDate = $today->copy()->addDays(7);
            $hasDueDate = !is_null($milestone->due_date);
            $isOverdue  = $hasDueDate && $milestone->due_date->lt($today);
            $isNearlyDue = $hasDueDate && !$isOverdue && $milestone->due_date->lte($urgentDate);

            // Semáforo: misma lógica que milestones/index (negro > rojo > amarillo > verde)
            if ($isComplete && !$hasInvoice) {
                $semBorder   = 'border-dark';
                $semHeader   = 'bg-dark text-white';
                $semDotColor = '#212529';
                $semLabel    = 'Sin factura';
                $semIcon     = 'ri-file-unknow-line';
                $semBadge    = 'bg-dark text-white';
            } elseif (!$isComplete && $isOverdue) {
                $semBorder   = 'border-danger';
                $semHeader   = 'bg-danger-subtle';
                $semDotColor = '#dc3545';
                $semLabel    = 'Vencido';
                $semIcon     = 'ri-alarm-warning-line';
                $semBadge    = 'bg-danger-subtle text-danger';
            } elseif (!$isComplete && $isNearlyDue) {
                $semBorder   = 'border-warning';
                $semHeader   = 'bg-warning-subtle';
                $semDotColor = '#ffc107';
                $semLabel    = 'Próximo a vencer';
                $semIcon     = 'ri-time-line';
                $semBadge    = 'bg-warning-subtle text-warning';
            } elseif ($isComplete) {
                $semBorder   = 'border-success';
                $semHeader   = 'bg-success-subtle';
                $semDotColor = '#28a745';
                $semLabel    = 'Completado';
                $semIcon     = 'ri-check-double-line';
                $semBadge    = 'bg-success-subtle text-success';
            } else {
                $semBorder   = 'border-success';
                $semHeader   = '';
                $semDotColor = '#28a745';
                $semLabel    = 'Al día';
                $semIcon     = 'ri-checkbox-circle-line';
                $semBadge    = 'bg-success-subtle text-success';
            }

            // Tiempo relativo al vencimiento (legible para humanos)
            $dueLabel = null;
            $dueLabelClass = 'text-muted';
            if ($hasDueDate) {
                $diffDays = $today->diffInDays($milestone->due_date, false); // negativo = pasado
                if ($diffDays === 0) {
                    $dueLabel = 'Vence hoy';
                    $dueLabelClass = 'text-danger fw-semibold';
                } elseif ($diffDays < 0) {
                    $abs = abs($diffDays);
                    $dueLabel = 'Hace ' . $abs . ' ' . ($abs === 1 ? 'día' : 'días');
                    $dueLabelClass = $isComplete ? 'text-muted' : 'text-danger fw-semibold';
                } elseif ($diffDays <= 7) {
                    $dueLabel = 'En ' . $diffDays . ' ' . ($diffDays === 1 ? 'día' : 'días');
                    $dueLabelClass = 'text-warning fw-semibold';
                } elseif ($diffDays <= 30) {
                    $weeks = (int) ceil($diffDays / 7);
                    $dueLabel = 'En ~' . $weeks . ' ' . ($weeks === 1 ? 'semana' : 'semanas');
                    $dueLabelClass = 'text-body';
                } else {
                    $months = (int) ceil($diffDays / 30);
                    $dueLabel = 'En ~' . $months . ' ' . ($months === 1 ? 'mes' : 'meses');
                    $dueLabelClass = 'text-muted';
                }
            }

            $paymentsCount = $milestone->payments->count();
            $canDeleteMilestone = $paymentsCount === 0
                || ($paymentsCount === 1 && $milestone->payments->first()?->status !== 'pagado');
        @endphp

        <div class="col-md-6 col-xl-6 mb-3">
            <div class="card h-100 {{ $semBorder }} border-2 overflow-visible">
                <div class="card-header d-flex justify-content-between align-items-center py-2 {{ $semHeader }}">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        {{-- Indicador semáforo --}}
                        <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $semDotColor }};flex-shrink:0;"></span>
                        <span class="fw-semibold fs-14">Hito #{{ $milestonePosition }}</span>
                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2 fs-11">
                            {{ $tipoCondicionMap[$milestone->payment_condition ?? 'credito'] ?? ($tipoHitoMap[$milestone->type] ?? $milestone->type) }}
                        </span>
                        @if ($milestone->is_advance)
                        <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-11">
                            <i class="ri-bank-line me-1"></i>Anticipo
                        </span>
                        @endif
                        <span class="badge {{ $semBadge }} py-1 px-2 fs-11">
                            <i class="{{ $semIcon }} me-1"></i>{{ $semLabel }}
                        </span>
                    </div>
                    <div class="d-flex gap-1">
                        @hasanyrole('admin|Orden de compra')
                        @if ($canModifyPurchaseOrder)
                            <button type="button" class="btn btn-xs btn-soft-primary btn-sm"
                                    title="Editar hito"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditMilestone{{ $milestone->id }}">
                                <i class="ri-edit-line fs-13"></i>
                            </button>
                        @if ($canDeleteMilestone)
                            <form action="{{ route('milestones.destroy', $milestone) }}" method="POST"
                                  onsubmit="return confirm('¿Eliminar este hito y todos sus pagos?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-soft-danger btn-sm" title="Eliminar hito">
                                    <i class="ri-delete-bin-line fs-13"></i>
                                </button>
                            </form>
                        @else
                            <button type="button" class="btn btn-xs btn-soft-secondary btn-sm"
                                    title="No se puede eliminar: el hito tiene pagos pagados o múltiples pagos registrados" disabled>
                                <i class="ri-lock-line fs-13"></i>
                            </button>
                        @endif
                        @else
                            <button type="button" class="btn btn-xs btn-soft-secondary btn-sm"
                                    title="OC autorizada: solo admin puede editar" disabled>
                                <i class="ri-lock-line fs-13"></i>
                            </button>
                        @endif
                        @endhasanyrole
                    </div>
                </div>

                <div class="card-body pb-2">
                    {{-- Info principal --}}
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted fs-12">Concepto:</span>
                        <span class="fs-12 fw-medium text-end">{{ $milestone->concept ?: '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted fs-12">Tipo valor:</span>
                        <span class="fs-12 fw-medium">{{ $tipoValorMap[$milestone->value_type] ?? $milestone->value_type }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted fs-12">Cubierto:</span>
                        <span class="fs-12 fw-semibold text-success">
                            {{ $purchaseOrder->currency }} {{ number_format($milestone->covered_amount, 2) }}
                        </span>
                    </div>
                    @if ($milestone->invoice_date)
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted fs-12">Fecha factura:</span>
                            <span class="fs-12">{{ $milestone->invoice_date->format('d/m/Y') }}</span>
                        </div>
                    @endif
                    @if ($milestone->due_date)
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted fs-12">Vencimiento:</span>
                            <span class="fs-12 text-end">
                                <span class="{{ $isOverdue && !$isComplete ? 'text-danger fw-semibold' : '' }}">
                                    {{ $milestone->due_date->format('d/m/Y') }}
                                </span>
                                @if ($dueLabel)
                                    <span class="d-block fs-11 {{ $dueLabelClass }}">{{ $dueLabel }}</span>
                                @endif
                            </span>
                        </div>
                    @endif

                    {{-- Barra de progreso --}}
                    <div class="mt-2 mb-1">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Progreso</small>
                            <small class="fw-semibold">{{ $percent }}%</small>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar {{ $progressClass }}" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                </div>

                {{-- Pagos del hito --}}
                <div class="card-body pt-0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="fw-semibold text-muted text-uppercase fs-11">Pagos ({{ $milestone->payments->count() }})</small>
                    </div>

                    @if ($milestone->payments->count() > 0)
                        <div class="table-responsive overflow-visible">
                            <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th>Estatus</th>
                                        <th>Folio</th>
                                        <th>Monto</th>
                                        @if (($milestone->payment_condition ?? 'credito') === 'credito')
                                        <th>Fecha Factura</th>
                                        @endif
                                        <th>Fecha Pago</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($milestone->payments as $payment)
                                        @php
                                            $payStatusMap = [
                                                'por_autorizar' => ['label' => 'Por autorizar', 'class' => 'bg-warning-subtle text-warning'],
                                                'autorizado'    => ['label' => 'Autorizado',    'class' => 'bg-info-subtle text-info'],
                                                'pagado'        => ['label' => 'Pagado',        'class' => 'bg-success-subtle text-success'],
                                                'rechazado'     => ['label' => 'Rechazado',     'class' => 'bg-danger-subtle text-danger'],
                                            ];
                                            $ps = $payStatusMap[$payment->status] ?? ['label' => $payment->status, 'class' => 'bg-secondary-subtle text-secondary'];
                                            $statusRowClassMap = [
                                                'por_autorizar' => 'table-warning',
                                                'autorizado'    => 'table-info',
                                                'pagado'        => 'table-success',
                                                'rechazado'     => 'table-danger',
                                            ];
                                            $statusRowClass = $statusRowClassMap[$payment->status] ?? '';
                                            $isAdminUser = auth()->user()?->hasRole('admin');
                                            $paymentDaysDiff = $today->diffInDays($payment->payment_date, false);
                                            if ($paymentDaysDiff === 0) {
                                                $paymentDateLabel = 'Vence hoy';
                                                $paymentDateLabelClass = 'text-danger fw-semibold';
                                            } elseif ($paymentDaysDiff < 0) {
                                                $paymentDateLabel = 'Hace ' . abs($paymentDaysDiff) . ' ' . (abs($paymentDaysDiff) === 1 ? 'día' : 'días');
                                                $paymentDateLabelClass = $payment->status === 'pagado' ? 'text-muted' : 'text-danger fw-semibold';
                                            } elseif ($paymentDaysDiff <= 7) {
                                                $paymentDateLabel = 'En ' . $paymentDaysDiff . ' ' . ($paymentDaysDiff === 1 ? 'día' : 'días');
                                                $paymentDateLabelClass = 'text-warning fw-semibold';
                                            } else {
                                                $paymentDateLabel = 'En ~' . (int) ceil($paymentDaysDiff / 7) . ' ' . ((int) ceil($paymentDaysDiff / 7) === 1 ? 'semana' : 'semanas');
                                                $paymentDateLabelClass = 'text-muted';
                                            }

                                            // Transiciones permitidas por rol y estatus
                                            $transitions = $isAdminUser
                                                ? match ($payment->status) {
                                                    'por_autorizar' => [
                                                        'autorizado' => ['label' => 'Autorizar',  'icon' => 'ri-check-line',           'btn' => 'btn-soft-info',    'confirm' => '¿Autorizar este pago?'],
                                                        'rechazado'  => ['label' => 'Rechazar',   'icon' => 'ri-close-circle-line',    'btn' => 'btn-soft-danger',  'confirm' => '¿Rechazar este pago?'],
                                                    ],
                                                    'autorizado' => [
                                                        'pagado' => ['label' => 'Marcar pagado', 'icon' => 'ri-money-dollar-circle-line', 'btn' => 'btn-soft-success', 'confirm' => '¿Marcar como PAGADO? Esto actualizará el saldo del hito.'],
                                                        'por_autorizar' => ['label' => 'Revertir', 'icon' => 'ri-arrow-go-back-line', 'btn' => 'btn-soft-warning', 'confirm' => '¿Revertir a «Por autorizar»?'],
                                                    ],
                                                    'rechazado' => [
                                                        'por_autorizar' => ['label' => 'Reactivar', 'icon' => 'ri-arrow-go-back-line', 'btn' => 'btn-soft-warning', 'confirm' => '¿Reactivar este pago a «Por autorizar»?'],
                                                    ],
                                                    default => [],
                                                }
                                                : match ($payment->status) {
                                                    'autorizado' => [
                                                        'pagado' => ['label' => 'Marcar pagado', 'icon' => 'ri-money-dollar-circle-line', 'btn' => 'btn-soft-success', 'confirm' => '¿Marcar como PAGADO? Esto actualizará el saldo del hito.'],
                                                    ],
                                                    default => [],
                                                };
                                        @endphp
                                        <tr class="{{ $statusRowClass }}" data-bs-theme="light">
                                            <td>
                                                <span class="badge {{ $ps['class'] }} py-1 px-1 fs-10">{{ $ps['label'] }}</span>
                                            </td>
                                            <td class="fw-medium">{{ $payment->folio }}</td>
                                            <td>{{ $purchaseOrder->currency }} ${{ number_format($payment->amount, 2) }}</td>
                                            @if (($milestone->payment_condition ?? 'credito') === 'credito')
                                            <td>
                                                {{ $payment->invoice_date ? $payment->invoice_date->format('d/m/Y') : '—' }}
                                            </td>
                                            @endif
                                            <td>
                                                <span class="d-flex align-items-center gap-1 fs-12">
                                                    <i class="ri-calendar-check-line text-primary"></i>
                                                    {{ $payment->payment_date->format('d/m/Y') }}
                                                </span>
                                                <small class="d-block fs-11 {{ $paymentDateLabelClass }}">{{ $paymentDateLabel }}</small>
                                            </td>
                                            <td class="text-end">
                                                <div class="dropdown">
                                                    <button class="btn btn-light btn-sm" type="button"
                                                            data-bs-toggle="dropdown" aria-expanded="false"
                                                            title="Acciones">
                                                        <i class="ri-more-2-fill"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        {{-- Transiciones de estatus --}}
                                                        @hasanyrole('admin|Pagos')
                                                        @foreach ($transitions as $newStatus => $transition)
                                                            <li>
                                                                <form action="{{ route('payments.update', $payment) }}" method="POST">
                                                                    @csrf @method('PATCH')
                                                                    <input type="hidden" name="status" value="{{ $newStatus }}">
                                                                    <button type="submit" class="dropdown-item"
                                                                            onclick="return confirm('{{ $transition['confirm'] }}')">
                                                                        <i class="{{ $transition['icon'] }} me-1"></i>{{ $transition['label'] }}
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        @endforeach
                                                        @endhasanyrole

                                                        {{-- Contrarecibo PDF (solo hitos crédito) --}}
                                                        @if (($milestone->payment_condition ?? 'credito') === 'credito')
                                                        @hasanyrole('admin|Pagos|Orden de compra')
                                                        <li>
                                                            <a href="{{ route('payments.contrarecibo', $payment) }}"
                                                               target="_blank" class="dropdown-item">
                                                                <i class="ri-file-download-line me-1"></i>Descargar contrarecibo
                                                            </a>
                                                        </li>
                                                        @endhasanyrole
                                                        @endif

                                                        {{-- SPEI: subir/reemplazar --}}
                                                        @hasanyrole('admin|Pagos')
                                                        <li>
                                                            <button type="button" class="dropdown-item"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#modalSpeiReceipt{{ $payment->id }}">
                                                                <i class="ri-file-upload-line me-1"></i>
                                                                {{ $payment->spei_receipt_path ? 'Reemplazar SPEI' : 'Subir SPEI' }}
                                                            </button>
                                                        </li>
                                                        @endhasanyrole

                                                        {{-- SPEI: ver --}}
                                                        @if ($payment->spei_receipt_path)
                                                        @hasanyrole('admin|Pagos|Orden de compra')
                                                        @php
                                                            $speiExt = strtolower(pathinfo($payment->spei_receipt_name ?: $payment->spei_receipt_path, PATHINFO_EXTENSION));
                                                            $speiIsImage = in_array($speiExt, ['jpg', 'jpeg', 'png', 'webp'], true);
                                                        @endphp
                                                        <li>
                                                            <button type="button"
                                                                    class="dropdown-item js-open-spei-modal"
                                                                    data-spei-preview-url="{{ route('payments.spei_receipt.download', ['payment' => $payment, 'disposition' => 'inline']) }}"
                                                                    data-spei-download-url="{{ route('payments.spei_receipt.download', $payment) }}"
                                                                    data-spei-name="{{ $payment->spei_receipt_name ?: ('SPEI-' . $payment->folio) }}"
                                                                    data-spei-is-image="{{ $speiIsImage ? '1' : '0' }}">
                                                                <i class="ri-eye-line me-1"></i>Ver SPEI
                                                            </button>
                                                        </li>
                                                        @endhasanyrole
                                                        @endif

                                                        {{-- Eliminar --}}
                                                        @hasanyrole('admin|Pagos')
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form action="{{ route('payments.destroy', $payment) }}" method="POST"
                                                                  onsubmit="return confirm('¿Eliminar este pago?')">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="ri-delete-bin-line me-1"></i>Eliminar pago
                                                                </button>
                                                            </form>
                                                        </li>
                                                        @endhasanyrole
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @hasanyrole('admin|Pagos')
                        @foreach ($milestone->payments as $payment)
                        <div class="modal fade" id="modalSpeiReceipt{{ $payment->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form action="{{ route('payments.update', $payment) }}" method="POST" enctype="multipart/form-data">
                                        @csrf @method('PATCH')
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="ri-bank-card-line me-1"></i>
                                                {{ $payment->spei_receipt_path ? 'Reemplazar' : 'Subir' }} comprobante SPEI — Pago {{ $payment->folio }}
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-0">
                                                <label class="form-label fw-medium">
                                                    Archivo comprobante SPEI <span class="text-danger">*</span>
                                                    <small class="text-muted fw-normal">(PDF, JPG, PNG, WEBP - máx. 10MB)</small>
                                                </label>
                                                <input type="file"
                                                       name="spei_receipt_file"
                                                       class="form-control @error('spei_receipt_file') is-invalid @enderror"
                                                       accept=".pdf,.jpg,.jpeg,.png,.webp"
                                                       required>
                                                @error('spei_receipt_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="ri-upload-2-line me-1"></i>
                                                {{ $payment->spei_receipt_path ? 'Reemplazar archivo' : 'Subir archivo' }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endforeach
                        @endhasanyrole
                    @else
                        <p class="text-muted fs-12 mb-0 text-center">Sin pagos registrados.</p>
                    @endif
                </div>
            </div>
        </div>

    @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="ri-flag-line fs-36 d-block mb-2"></i>
                    No hay hitos configurados. Agrega el primero con el botón <strong>"Agregar hito"</strong>.
                </div>
            </div>
        </div>
    @endforelse
</div>

{{-- ── FACTURAS ── --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center py-2">
                <h5 class="card-title mb-0">
                    <i class="ri-file-pdf-line me-1 text-danger"></i> Facturas
                    <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $purchaseOrder->invoices->count() }}</span>
                </h5>
                @hasanyrole('admin|Pagos')
                <button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="modal" data-bs-target="#modalCreateInvoice">
                    <i class="ri-upload-2-line me-1"></i> Subir factura
                </button>
                @endhasanyrole
            </div>

            @if ($purchaseOrder->invoices->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Estatus</th>
                                <th>Folio</th>
                                <th>Archivo</th>
                                <th>Fecha</th>
                                <th>Moneda</th>
                                <th class="text-end">Importe</th>
                                <th class="text-end">Nota credito</th>
                                <th class="text-end">Alcance liquido</th>
                                <th>Vencimiento</th>
                                <th>Hitos vinculados</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchaseOrder->invoices as $invoice)
                                @php
                                    $statusMap = [
                                        'en_proceso' => ['label' => 'En Proceso', 'class' => 'bg-warning-subtle text-warning'],
                                        'aceptada' => ['label' => 'Aceptada', 'class' => 'bg-success-subtle text-success'],
                                        'rechazada' => ['label' => 'Rechazada', 'class' => 'bg-danger-subtle text-danger'],
                                    ];
                                    $statusMeta = $statusMap[$invoice->status] ?? $statusMap['en_proceso'];
                                @endphp
                                <tr>
                                    <td>
                                        <span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
                                        @hasanyrole('admin|Orden de compra')
                                            <form action="{{ route('invoices.status.update', $invoice) }}" method="POST" class="mt-1">
                                                @csrf
                                                @method('PATCH')
                                                <select name="status" class="form-select form-select-sm">
                                                    <option value="en_proceso" {{ $invoice->status === 'en_proceso' ? 'selected' : '' }}>En Proceso</option>
                                                    <option value="aceptada" {{ $invoice->status === 'aceptada' ? 'selected' : '' }}>Aceptada</option>
                                                    <option value="rechazada" {{ $invoice->status === 'rechazada' ? 'selected' : '' }}>Rechazada</option>
                                                </select>
                                                <select name="purchase_order_milestone_id" class="form-select form-select-sm mt-1">
                                                    <option value="">Seleccionar hito...</option>
                                                    @foreach ($purchaseOrder->milestones as $milestoneOption)
                                                        <option value="{{ $milestoneOption->id }}" {{ optional($invoice->milestones->first())->id === $milestoneOption->id ? 'selected' : '' }}>
                                                            {{ $milestoneOption->concept ?: $milestoneOption->payment_condition ?: ('Hito #' . $milestoneOption->id) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-xs btn-primary mt-1 w-100" style="padding: 2px 8px;">
                                                    Guardar
                                                </button>
                                            </form>
                                        @endhasanyrole
                                    </td>
                                    <td class="fw-medium fs-12">
                                        {{ $invoice->folio ?: '—' }}
                                    </td>
                                    <td>
                                        @if ($invoice->file_name)
                                            <i class="ri-file-pdf-2-line text-danger me-1"></i>
                                            <span class="fw-medium">{{ $invoice->file_name }}</span>
                                        @else
                                            <span class="text-muted fs-12">Sin archivo</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap fs-12">
                                        {{ $invoice->attached_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border py-1 px-2 fs-12">
                                            {{ $invoice->currency }}
                                        </span>
                                    </td>
                                    <td class="text-end fw-semibold">
                                        {{ number_format($invoice->amount, 2) }}
                                    </td>
                                    <td class="text-end">
                                        {{ $invoice->credit_note_amount !== null ? number_format((float) $invoice->credit_note_amount, 2) : '—' }}
                                    </td>
                                    <td class="text-end fw-semibold text-primary">
                                        {{ number_format((float) ($invoice->net_scope ?? $invoice->amount), 2) }}
                                    </td>
                                    <td class="text-nowrap fs-12">
                                        {{ optional($invoice->due_date)->format('d/m/Y') ?: '—' }}
                                    </td>
                                    <td>
                                        @forelse ($invoice->milestones as $im)
                                            <span class="badge bg-info-subtle text-info py-1 px-2 fs-11 me-1">
                                                Hito #{{ $milestonePositionMap[$im->id] ?? $im->id }}
                                            </span>
                                        @empty
                                            <span class="text-muted fs-12">—</span>
                                        @endforelse
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            @hasanyrole('admin|Pagos')
                                            @if ($invoice->file_path)
                                            <a href="{{ route('invoices.download', $invoice) }}"
                                               target="_blank"
                                               class="btn btn-xs btn-soft-primary" style="padding: 2px 8px;"
                                               title="Ver / Descargar PDF">
                                                <i class="ri-download-2-line"></i>
                                            </a>
                                            @endif
                                            <form action="{{ route('invoices.destroy', $invoice) }}" method="POST"
                                                  onsubmit="return confirm('¿Eliminar la factura {{ $invoice->folio ?: ($invoice->file_name ?: '#' . $invoice->id) }}? Esta acción no se puede deshacer.')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-soft-danger"
                                                        style="padding: 2px 8px;" title="Eliminar factura">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                            @endhasanyrole
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end fw-semibold fs-13">Total facturado (solo aceptadas):</td>
                                <td class="text-end fw-bold text-primary">
                                    {{ number_format((float) $purchaseOrder->invoices->where('status', 'aceptada')->sum(function ($invoice) { return (float) ($invoice->net_scope ?? $invoice->amount ?? 0); }), 2) }}
                                </td>
                                <td colspan="5"></td>
            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="card-body text-center text-muted py-4">
                    <i class="ri-file-pdf-line fs-36 d-block mb-2 text-danger opacity-50"></i>
                    No hay facturas registradas. Usa el botón <strong>"Subir factura"</strong> para agregar la primera.
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ── EVIDENCIAS ── --}}
@hasanyrole('admin|Solmat|Pagos|Orden de compra')
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center py-2">
                <h5 class="card-title mb-0">
                    <i class="ri-image-add-line me-1 text-info"></i> Evidencias
                    <span class="badge bg-info-subtle text-info ms-1">{{ $purchaseOrder->evidences->count() }}</span>
                </h5>
                <button type="button" class="btn btn-sm btn-info"
                        data-bs-toggle="modal" data-bs-target="#modalCreateEvidence">
                    <i class="ri-upload-2-line me-1"></i> Subir evidencia
                </button>
            </div>

            @if ($purchaseOrder->evidences->count() > 0)
                @foreach ($groupedEvidences as $groupDate => $evidences)
                    <div class="card-body {{ !$loop->first ? 'border-top' : '' }}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 fw-semibold text-muted">
                                <i class="ri-calendar-line me-1"></i>
                                {{ $groupDate === 'sin-fecha' ? 'Sin fecha' : \Carbon\Carbon::parse($groupDate)->format('d/m/Y') }}
                            </h6>
                            <span class="badge bg-light text-dark border">{{ $evidences->count() }} archivo(s)</span>
                        </div>

                        <div class="row g-3">
                            @foreach ($evidences as $evidence)
                                @php
                                    $isImage = \Illuminate\Support\Str::startsWith((string) $evidence->mime_type, 'image/');
                                    $previewUrl = route('evidences.download', ['evidence' => $evidence, 'disposition' => 'inline']);
                                @endphp
                                <div class="col-sm-6 col-lg-4 col-xl-3">
                                    <div class="card h-100 border oc-evidence-card">
                                        <button type="button"
                                                class="btn oc-evidence-preview p-0 text-start js-open-evidence-modal"
                                                data-evidence-preview-url="{{ $previewUrl }}"
                                                data-evidence-download-url="{{ route('evidences.download', $evidence) }}"
                                                data-evidence-name="{{ $evidence->file_name }}"
                                                data-evidence-is-image="{{ $isImage ? 1 : 0 }}"
                                                title="Previsualizar evidencia">
                                            @if ($isImage)
                                                <img src="{{ $previewUrl }}" alt="{{ $evidence->file_name }}" class="oc-evidence-thumb">
                                            @else
                                                <div class="oc-evidence-doc d-flex flex-column align-items-center justify-content-center">
                                                    <i class="ri-file-pdf-2-line text-danger fs-32"></i>
                                                    <span class="fs-11 text-muted mt-1">Documento</span>
                                                </div>
                                            @endif
                                        </button>
                                        <div class="card-body p-2">
                                            <p class="mb-1 fs-12 fw-semibold text-truncate" title="{{ $evidence->file_name }}">
                                                {{ $evidence->file_name }}
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge {{ $evidence->source === 'supplier_portal' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success' }} fs-10">
                                                    {{ $evidence->source === 'supplier_portal' ? 'Proveedor' : 'Interno' }}
                                                </span>
                                                <small class="text-muted fs-11">{{ $evidence->created_at?->format('H:i') }}</small>
                                            </div>
                                            <div class="text-muted fs-11 mb-2 text-truncate" title="{{ $evidence->uploader?->name ?? 'Sistema' }}">
                                                <i class="ri-user-line me-1"></i>{{ $evidence->uploader?->name ?? 'Sistema' }}
                                            </div>
                                            @if ($evidence->description)
                                                <div class="text-muted fs-11 mb-2 text-truncate" title="{{ $evidence->description }}">
                                                    {{ $evidence->description }}
                                                </div>
                                            @endif
                                            <div class="d-flex gap-1">
                                                <button type="button"
                                                        class="btn btn-xs btn-soft-info flex-fill js-open-evidence-modal"
                                                        data-evidence-preview-url="{{ $previewUrl }}"
                                                        data-evidence-download-url="{{ route('evidences.download', $evidence) }}"
                                                        data-evidence-name="{{ $evidence->file_name }}"
                                                        data-evidence-is-image="{{ $isImage ? 1 : 0 }}"
                                                        title="Previsualizar">
                                                    <i class="ri-eye-line"></i>
                                                </button>
                                                <a href="{{ route('evidences.download', $evidence) }}"
                                                   target="_blank"
                                                   class="btn btn-xs btn-soft-primary flex-fill"
                                                   title="Descargar">
                                                    <i class="ri-download-2-line"></i>
                                                </a>
                                                <form action="{{ route('evidences.destroy', $evidence) }}" method="POST"
                                                      onsubmit="return confirm('¿Eliminar la evidencia {{ $evidence->file_name }}? Esta acción no se puede deshacer.');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-soft-danger" title="Eliminar">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @else
                <div class="card-body text-center text-muted py-4">
                    <i class="ri-image-line fs-36 d-block mb-2 text-info opacity-50"></i>
                    No hay evidencias registradas. Usa el botón <strong>"Subir evidencia"</strong> para agregar la primera.
                </div>
            @endif
        </div>
    </div>
</div>
@endhasanyrole

{{-- ── OBSERVACIONES ── --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-chat-3-line me-1 text-primary"></i> Observaciones</h5>
            </div>
            <div class="card-body" id="oc_obs_container">
                @forelse ($purchaseOrder->observations ?? [] as $noteIndex => $note)
                    <div class="d-flex gap-3 mb-3">
                        <div class="avatar-sm">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-14 fw-bold">
                                {{ strtoupper(substr($note['user_name'] ?? '?', 0, 1)) }}
                            </span>
                        </div>
                        <div class="">
                            <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
                                <span class="fw-semibold fs-13">{{ $note['user_name'] ?? 'Usuario' }}</span>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted fs-12">{{ \Carbon\Carbon::parse($note['created_at'])->format('d/m/Y H:i') }}</span>
                                    @hasanyrole('admin|Orden de compra')
                                    @if ($canModifyPurchaseOrder)
                                    <button type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#oc_note_edit_{{ $noteIndex }}"
                                            aria-expanded="false"
                                            aria-controls="oc_note_edit_{{ $noteIndex }}">
                                        Editar
                                    </button>
                                    <form action="{{ route('purchase_orders.notes.destroy', [$purchaseOrder, $noteIndex]) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('¿Eliminar esta observación?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm p-0 text-danger text-decoration-none">
                                            Eliminar
                                        </button>
                                    </form>
                                    @endif
                                    @endhasanyrole
                                </div>
                            </div>
                            <p class="mb-0 text-body-secondary fs-13">{{ $note['text'] }}</p>

                            @hasanyrole('admin|Orden de compra')
                            @if ($canModifyPurchaseOrder)
                            <div class="collapse mt-2" id="oc_note_edit_{{ $noteIndex }}">
                                <form action="{{ route('purchase_orders.notes.update', [$purchaseOrder, $noteIndex]) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <div class="input-group input-group-sm">
                                        <textarea name="text" rows="2" class="form-control" required>{{ $note['text'] }}</textarea>
                                        <button type="submit" class="btn btn-primary">Guardar</button>
                                    </div>
                                </form>
                            </div>
                            @endif
                            @endhasanyrole
                        </div>
                    </div>
                    <hr class="my-2">
                @empty
                    <p class="text-muted fs-13 mb-3" id="oc_obs_empty">Sin observaciones registradas.</p>
                @endforelse
            </div>
            @hasanyrole('admin|Orden de compra')
            @if ($canModifyPurchaseOrder)
            <div class="card-footer bg-transparent">
                <form action="{{ route('purchase_orders.notes.store', $purchaseOrder) }}" method="POST">
                    @csrf
                    <div class="mb-2">
                        <textarea name="text" rows="3"
                                  class="form-control @error('text') is-invalid @enderror"
                                  placeholder="Escribe una observación…" required>{{ old('text') }}</textarea>
                        @error('text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ri-send-plane-line me-1"></i>Agregar observación
                        </button>
                    </div>
                </form>
            </div>
            @endif
            @endhasanyrole
        </div>
    </div>
</div>

{{-- ── MODALES DE HITOS (fuera del row para posicionamiento correcto) ── --}}
@foreach ($orderedMilestones as $milestone)
    @php
        $milestonePosition = $loop->iteration;
    @endphp

    {{-- MODAL Editar Hito --}}
    <div class="modal fade" id="modalEditMilestone{{ $milestone->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('milestones.update', $milestone) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ri-edit-line me-1"></i> Editar Hito #{{ $milestonePosition }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-medium">Concepto</label>
                                <input type="text" class="form-control"
                                       name="concept" value="{{ $milestone->concept }}"
                                       maxlength="255" placeholder="Ej. Anticipo de fabricación">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Condición de pago <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_condition" required>
                                    <option value="contado" {{ ($milestone->payment_condition ?? 'credito') === 'contado' ? 'selected' : '' }}>Contado</option>
                                    <option value="credito" {{ ($milestone->payment_condition ?? 'credito') === 'credito' ? 'selected' : '' }}>Crédito</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Tipo de valor <span class="text-danger">*</span></label>
                                <select class="form-select" name="value_type" required>
                                    <option value="fijo"       {{ $milestone->value_type === 'fijo'       ? 'selected' : '' }}>Fijo</option>
                                    <option value="porcentaje" {{ $milestone->value_type === 'porcentaje' ? 'selected' : '' }}>Porcentaje</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Valor <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" class="form-control"
                                       name="value" value="{{ $milestone->value }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Fecha vencimiento <span class="text-danger">*</span></label>
                                <input type="date" class="form-control"
                                       name="due_date" value="{{ $milestone->due_date?->format('Y-m-d') }}" min="{{ $minDueDate }}" required>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_advance" value="1"
                                           id="is_advance_edit_{{ $milestone->id }}"
                                           {{ $milestone->is_advance ? 'checked' : '' }}>
                                    <label class="form-check-label fw-medium" for="is_advance_edit_{{ $milestone->id }}">
                                        <i class="ri-bank-line me-1 text-warning"></i>Esto es un anticipo
                                    </label>
                                </div>
                                <div class="form-text">Marca si este hito corresponde a un pago anticipado (antes de la entrega).</div>
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

@endforeach

{{-- MODAL Subir Factura --}}
<div class="modal fade" id="modalCreateInvoice" tabindex="-1" aria-labelledby="modalCreateInvoiceLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="adminInvoiceForm" action="{{ route('invoices.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateInvoiceLabel">
                        <i class="ri-file-pdf-line me-1 text-danger"></i> Subir Factura — OC #{{ $purchaseOrder->id }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        {{-- Archivo PDF --}}
                        <div class="col-12">
                            <label class="form-label fw-medium">
                                Archivo PDF
                                <small class="text-muted fw-normal">(opcional, máx. 10 MB)</small>
                            </label>
                            <input type="file" class="form-control @error('pdf_file') is-invalid @enderror"
                                   name="pdf_file" accept=".pdf">
                            @error('pdf_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Archivo XML --}}
                        <div class="col-12">
                            <label class="form-label fw-medium">
                                Archivo XML
                                <small class="text-muted fw-normal">(CFDI timbrado)</small>
                            </label>
                            <input id="adminInvoiceXmlFile" type="file" class="form-control @error('xml_file') is-invalid @enderror"
                                   name="xml_file" accept=".xml,text/xml">
                            <div class="form-text">Al cargar el XML se autocompletará el folio fiscal (UUID).</div>
                            @error('xml_file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- Folio --}}
                        <div class="col-12">
                            <label class="form-label fw-medium">Folio fiscal (UUID)</label>
                            <input id="adminInvoiceFolio" type="text" class="form-control @error('folio') is-invalid @enderror"
                                   name="folio" maxlength="100"
                                   placeholder="Se autocompleta con el XML (puedes editarlo si aplica)">
                            <div class="form-text">Se toma del nodo TimbreFiscalDigital UUID del XML.</div>
                            @error('folio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Importe y Moneda --}}
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Importe <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" max="{{ $purchaseOrder->amount }}"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   name="amount" placeholder="0.00" required>
                            <div class="form-text">Máximo: {{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->amount, 2) }}</div>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-medium">Moneda</label>
                            <input type="text" class="form-control bg-light" value="{{ $purchaseOrder->currency }}" disabled>
                            <input type="hidden" name="currency" value="{{ $purchaseOrder->currency }}">
                            <div class="form-text">Dato heredado de la Orden de Compra.</div>
                        </div>

                        {{-- Hitos relacionados --}}
                        @if ($purchaseOrder->milestones->count() > 0)
                            <div class="col-12">
                                <label class="form-label fw-medium">Hitos relacionados</label>
                                <div class="border rounded p-3 bg-light">
                                    <div class="row g-2">
                                        @foreach ($orderedMilestones as $m)
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="milestone_ids[]" value="{{ $m->id }}"
                                                           id="inv_milestone_{{ $m->id }}">
                                                    <label class="form-check-label fs-13" for="inv_milestone_{{ $m->id }}">
                                                        <strong>Hito #{{ $loop->iteration }}</strong>
                                                        <span class="text-muted">
                                                            — {{ $m->concept ?: ($m->type === 'anticipo' ? 'Anticipo' : 'Regular') }}
                                                            · {{ $purchaseOrder->currency }} {{ number_format($m->effective_amount, 2) }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="form-text">Selecciona los hitos que cubre esta factura (opcional).</div>
                                @error('milestone_ids')<div class="text-danger fs-12 mt-1">{{ $message }}</div>@enderror
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-upload-2-line me-1"></i> Subir factura
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL Crear Hito --}}
@hasanyrole('admin|Orden de compra')
@if ($canModifyPurchaseOrder)
<div class="modal fade" id="modalCreateMilestone" tabindex="-1" aria-labelledby="modalCreateMilestoneLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('milestones.store') }}" method="POST">
                @csrf
                <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateMilestoneLabel">
                        <i class="ri-flag-line me-1"></i> Nuevo Hito
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Concepto</label>
                            <input type="text" class="form-control"
                                   name="concept" maxlength="255"
                                   placeholder="Ej. Anticipo de fabricación">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Condición de pago <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_condition" required>
                                <option value="credito" selected>Crédito</option>
                                <option value="contado">Contado</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Tipo de valor <span class="text-danger">*</span></label>
                            <select class="form-select" name="value_type" id="milestoneValueType" required>
                                <option value="fijo" selected>Fijo</option>
                                <option value="porcentaje">Porcentaje</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Valor <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01"
                                max="{{ $importeTotal }}"
                                   class="form-control" id="milestoneValue"
                                   name="value" placeholder="Ej. 5000.00" required>
                            <div class="invalid-feedback" id="milestoneValueFeedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Fecha vencimiento <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="due_date" min="{{ $minDueDate }}" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_advance" value="1"
                                       id="is_advance_create">
                                <label class="form-check-label fw-medium" for="is_advance_create">
                                    <i class="ri-bank-line me-1 text-warning"></i>Esto es un anticipo
                                </label>
                            </div>
                            <div class="form-text">Marca si este hito corresponde a un pago anticipado (antes de la entrega).</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Crear hito
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endhasanyrole

{{-- Modal único para visualizar comprobante SPEI --}}
<div class="modal fade" id="modalViewSpeiReceipt" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewSpeiTitle">
                    <i class="ri-image-2-line me-1"></i> Comprobante SPEI
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light-subtle">
                <div id="speiImageWrap" class="text-center d-none">
                    <img id="speiImagePreview" src="" alt="Comprobante SPEI" class="img-fluid rounded border" style="max-height: 72vh; object-fit: contain;">
                </div>
                <div id="speiDocWrap" class="d-none">
                    <iframe id="speiDocPreview" src="" title="Comprobante SPEI" style="width: 100%; height: 72vh; border: 1px solid var(--bs-border-color); border-radius: .5rem;"></iframe>
                </div>
            </div>
            <div class="modal-footer">
                <a id="speiDownloadAction" href="#" target="_blank" class="btn btn-primary">
                    <i class="ri-download-2-line me-1"></i> Descargar
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal único para visualizar evidencias --}}
<div class="modal fade" id="modalViewEvidence" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewEvidenceTitle">
                    <i class="ri-image-2-line me-1"></i> Evidencia
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light-subtle">
                <div id="evidenceImageWrap" class="text-center d-none">
                    <img id="evidenceImagePreview" src="" alt="Evidencia" class="img-fluid rounded border" style="max-height: 72vh; object-fit: contain;">
                </div>
                <div id="evidenceDocWrap" class="d-none">
                    <iframe id="evidenceDocPreview" src="" title="Evidencia" style="width: 100%; height: 72vh; border: 1px solid var(--bs-border-color); border-radius: .5rem;"></iframe>
                </div>
            </div>
            <div class="modal-footer">
                <a id="evidenceDownloadAction" href="#" target="_blank" class="btn btn-primary">
                    <i class="ri-download-2-line me-1"></i> Descargar
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@php
    $annexRecord    = $purchaseOrder->annex;
    $annexUpdatedAt = $annexRecord?->updated_at;
    $annexAllowed   = '<p><strong><em><u><s><br><ul><ol><li><h1><h2><h3><h4><h5><h6><span><a><blockquote>';
    $contratoInitHtml = $annexRecord?->contrato_html
        ?: strip_tags(
            \Illuminate\Support\Str::markdown(file_get_contents(public_path('document_templates/CONTRATO.docx.md'))),
            $annexAllowed
        );
    $dossierInitHtml = $annexRecord?->dossier_html
        ?: strip_tags(
            \Illuminate\Support\Str::markdown(file_get_contents(public_path('document_templates/INDICE DE DOSSIER DE CALIDAD.md'))),
            $annexAllowed
        );
@endphp

{{-- ── Modal: Generar PDF con Anexos ── --}}
<div class="modal fade" id="modalPdfAnnexes" tabindex="-1" aria-labelledby="modalPdfAnnexesLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="modalPdfAnnexesLabel">
                        <i class="ri-file-pdf-2-line me-1"></i> Generar PDF &mdash; OC #{{ $purchaseOrder->folio ?? $purchaseOrder->id }}
                    </h5>
                    @if ($annexUpdatedAt)
                    <small class="text-muted">Configuración guardada el {{ $annexUpdatedAt->translatedFormat('d/m/Y \a \l\a\s H:i') ?? $annexUpdatedAt->format('d/m/Y H:i') }}</small>
                    @else
                    <small class="text-muted">Sin configuración guardada aún.</small>
                    @endif
                </div>
                <button type="button" class="btn-close ms-3" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formPdfAnnexes" method="POST">
                @csrf
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" id="annexTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabCondiciones" type="button" role="tab">
                                <i class="ri-file-text-line me-1"></i> Condiciones Generales
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabContrato" type="button" role="tab">
                                <i class="ri-file-list-3-line me-1"></i> Contrato
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDossier" type="button" role="tab">
                                <i class="ri-archive-line me-1"></i> Índice Dossier
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content">

                        {{-- Tab 1: Condiciones Generales --}}
                        <div class="tab-pane fade show active" id="tabCondiciones" role="tabpanel">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="annex_condiciones" name="annex_condiciones" value="1"
                                        {{ $annexRecord?->annex_condiciones ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="annex_condiciones">
                                        Incluir Condiciones Generales de Compra en el PDF
                                    </label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="client_name">Nombre de la Contratante</label>
                                    <input type="text" class="form-control" id="client_name" name="client_name"
                                        value="{{ $annexRecord?->client_name ?? 'ELECTRO SERVICIOS HR, S.A. DE C.V.' }}"
                                        placeholder="Ej. ELECTRO SERVICIOS HR, S.A. DE C.V.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="provider_name">Nombre de la Contratada (Proveedor)</label>
                                    <input type="text" class="form-control" id="provider_name" name="provider_name"
                                        value="{{ $annexRecord?->provider_name ?? ($purchaseOrder->supplier->rfc_name ?? $purchaseOrder->supplier->commercial_name ?? '') }}"
                                        placeholder="Nombre del proveedor">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="penalidad_porcentaje">Penalidad (porcentaje, ej. &ldquo;10%&rdquo;)</label>
                                    <input type="text" class="form-control" id="penalidad_porcentaje" name="penalidad_porcentaje"
                                        value="{{ $annexRecord?->penalidad_porcentaje ?? '' }}"
                                        placeholder="Ej. 10%">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="penalidad_numero">Penalidad (en letras/monto)</label>
                                    <input type="text" class="form-control" id="penalidad_numero" name="penalidad_numero"
                                        value="{{ $annexRecord?->penalidad_numero ?? '' }}"
                                        placeholder="Ej. DIEZ">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="nombre_aceptacion">Nombre para aceptación / firma</label>
                                    <input type="text" class="form-control" id="nombre_aceptacion" name="nombre_aceptacion"
                                        value="{{ $annexRecord?->nombre_aceptacion ?? '' }}"
                                        placeholder="Nombre del firmante">
                                </div>
                            </div>
                        </div>

                        {{-- Tab 2: Contrato --}}
                        <div class="tab-pane fade" id="tabContrato" role="tabpanel">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="annex_contrato" name="annex_contrato" value="1"
                                        {{ $annexRecord?->annex_contrato ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="annex_contrato">
                                        Incluir Contrato / Cláusulas Particulares en el PDF
                                    </label>
                                </div>
                            </div>
                            <input type="hidden" id="contrato_html_input" name="contrato_html">
                            <div id="editorContrato" style="height: 420px;"></div>
                        </div>

                        {{-- Tab 3: Índice Dossier --}}
                        <div class="tab-pane fade" id="tabDossier" role="tabpanel">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="annex_dossier" name="annex_dossier" value="1"
                                        {{ $annexRecord?->annex_dossier ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="annex_dossier">
                                        Incluir Índice Dossier de Calidad en el PDF
                                    </label>
                                </div>
                            </div>
                            <input type="hidden" id="dossier_html_input" name="dossier_html">
                            <div id="editorDossier" style="height: 420px;"></div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-outline-secondary" id="btnSaveAnnex">
                        <i class="ri-save-line me-1"></i> Guardar configuración
                    </button>
                    <button type="button" class="btn btn-danger" id="btnPdfAnnexes">
                        <i class="ri-file-pdf-2-line me-1"></i> Guardar y Generar PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
{{-- Contenido inicial para los editores Quill (sanitizado) --}}
<template id="contrato-init-html">{!! $contratoInitHtml !!}</template>
<template id="dossier-init-html">{!! $dossierInitHtml !!}</template>

@push('styles')
<link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css">
<style>
.oc-qty-input,.oc-delivery-input {
    border-color: var(--bs-border-color);
    transition: border-color .15s, box-shadow .15s;
}
.oc-qty-input:focus,.oc-delivery-input:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb),.15);
}
.oc-qty-input.is-saved,.oc-delivery-input.is-saved {
    border-color: var(--bs-success) !important;
    box-shadow: 0 0 0 .2rem rgba(var(--bs-success-rgb),.15);
}
.oc-qty-input.is-error { border-color: var(--bs-danger) !important; }
.oc-qty-input::-webkit-outer-spin-button,
.oc-qty-input::-webkit-inner-spin-button { -webkit-appearance:none; }
.oc-qty-input[type=number] { -moz-appearance:textfield; }
@keyframes oc-flash {
    0%   { background-color: rgba(var(--bs-primary-rgb), .12); }
    100% { background-color: transparent; }
}
.oc-item-new { animation: oc-flash .9s ease-out forwards; }
.oc-evidence-card {
    transition: transform .15s ease, box-shadow .15s ease;
}
.oc-evidence-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 .4rem .9rem rgba(0,0,0,.08);
}
.oc-evidence-preview {
    border: 0;
    background: transparent;
    width: 100%;
}
.oc-evidence-thumb {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-top-left-radius: .5rem;
    border-top-right-radius: .5rem;
}
.oc-evidence-doc {
    width: 100%;
    height: 150px;
    background: var(--bs-light);
    border-top-left-radius: .5rem;
    border-top-right-radius: .5rem;
    border-bottom: 1px solid var(--bs-border-color);
}
.oc-approve-alert {
    position: relative;
    overflow: hidden;
}
.oc-approve-inline-progress {
    position: absolute;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, .56);
    z-index: 4;
    pointer-events: none;
}
.oc-approve-alert.is-processing .oc-approve-inline-progress {
    display: flex;
}
.oc-approve-inline-progress-bar {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 28%;
    left: -28%;
    background: linear-gradient(90deg, rgba(var(--bs-success-rgb), .08), rgba(var(--bs-success-rgb), .26), rgba(var(--bs-primary-rgb), .08));
    animation: oc-approve-slide 1.15s ease-in-out infinite;
}
.oc-approve-inline-progress-text {
    position: relative;
    z-index: 1;
    font-size: .86rem;
    font-weight: 600;
    color: var(--bs-dark);
    background: rgba(255, 255, 255, .95);
    border: 1px solid rgba(var(--bs-success-rgb), .2);
    border-radius: 999px;
    padding: .3rem .75rem;
    box-shadow: 0 .2rem .7rem rgba(0,0,0,.08);
}
@keyframes oc-approve-slide {
    to { transform: translateX(460%); }
}
</style>
@endpush

@endsection

@push('scripts')
<script>
(function () {
    var csrf     = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var tbody    = document.getElementById('oc_items_tbody');
    var badge    = document.getElementById('oc_items_badge');
    var addPanel = document.getElementById('oc_add_panel');
    var btnToggle = document.getElementById('btn_toggle_add_concept');
    var approveForm = document.getElementById('approvePurchaseOrderForm');
    var approveAlert = document.getElementById('purchaseOrderApproveAlert');
    var approveInlineProgress = document.getElementById('approveInlineProgress');
    var approveInlineProgressText = document.getElementById('approveInlineProgressText');
    var approveBtn = document.getElementById('approvePurchaseOrderBtn');
    var approveSubmitArmed = false;

    function startApproveInlineEffect(onComplete) {
        if (approveInlineProgressText) {
            var hasNextPending = !!document.querySelector('[href*="/purchase_orders/"][title="Ir a la siguiente OC pendiente"]');
            approveInlineProgressText.textContent = hasNextPending
                ? 'Avanzando a la siguiente...'
                : 'Finalizando autorización...';
        }

        if (approveAlert) {
            approveAlert.classList.add('is-processing');
        }
        if (approveInlineProgress) {
            approveInlineProgress.setAttribute('aria-hidden', 'false');
        }

        window.setTimeout(function () {
            if (typeof onComplete === 'function') onComplete();
        }, 480);
    }

    if (approveForm) {
        approveForm.addEventListener('submit', function (e) {
            if (approveSubmitArmed) {
                return;
            }

            e.preventDefault();
            var submitBtn = approveForm.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            if (approveBtn) {
                approveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Autorizando...';
            }

            startApproveInlineEffect(function () {
                approveSubmitArmed = true;
                approveForm.submit();
            });
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    function fmtMoney(n) { return parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

    function parsePrice(val) {
        var clean = String(val == null ? '' : val).replace(/,/g, '').trim();
        if (clean === '') return NaN;
        return parseFloat(clean);
    }

    function fmtPriceInput(n) {
        var raw = String(n == null ? '' : n).replace(/,/g, '').trim();
        if (raw === '') return '';
        var num = Number(raw);
        if (!isFinite(num)) return '';
        var decimalPart = raw.split('.')[1] || '';
        var decimals = decimalPart.length;
        return num.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: Math.max(decimals, 8)
        });
    }

    function formatPriceInput(input) {
        if (!input || input.dataset.field !== 'unit_price') return;
        var n = parsePrice(input.value);
        input.value = isNaN(n) ? '' : fmtPriceInput(n);
    }

    function unformatPriceInput(input) {
        if (!input || input.dataset.field !== 'unit_price') return;
        input.value = String(input.value || '').replace(/,/g, '');
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str != null ? String(str) : ''));
        return d.innerHTML;
    }

    function renumber() {
        tbody.querySelectorAll('tr.oc-item-row').forEach(function (row, i) {
            var td = row.querySelector('.oc-row-num');
            if (td) td.textContent = i + 1;
        });
    }

    function updateBadge() {
        var n = tbody.querySelectorAll('tr.oc-item-row').length;
        if (badge) badge.textContent = n;
    }

    function updateTotals(data) {
        var el = function (id) { return document.getElementById(id); };
        var fmtRate = function (rate) {
            var n = parseFloat(rate);
            if (!isFinite(n)) return '0';
            return String(rate).replace(/\.0+$/, '').replace(/(\.\d*[1-9])0+$/, '$1');
        };
        if (data.subtotal !== undefined && el('oc_subtotal')) el('oc_subtotal').textContent = '$' + fmtMoney(data.subtotal);
        if (data.iva      !== undefined && el('oc_iva'))      el('oc_iva').textContent      = '$' + fmtMoney(data.iva);
        // Actualizar label de impuesto si el servidor devuelve tax_rate
        if (data.tax_rate !== undefined && el('oc_iva_label')) {
            if (data.tax_rate === null) {
                el('oc_iva_label').textContent = 'Impuesto (Exento)';
            } else {
                el('oc_iva_label').textContent = 'IVA (' + fmtRate(data.tax_rate) + '%)';
            }
        }

        if (data.isr_amount !== undefined && el('oc_isr')) {
            el('oc_isr').textContent = '$' + fmtMoney(data.isr_amount);
        }
        if (data.isr_rate !== undefined) {
            var isrRow = el('oc_isr_row');
            if (isrRow) isrRow.style.display = data.isr_rate === null ? 'none' : '';
            if (data.isr_rate !== null && el('oc_isr_label')) {
                el('oc_isr_label').textContent = 'ISR (' + fmtRate(data.isr_rate) + '%)';
            }
        }

        if (data.retention_iva_amount !== undefined && el('oc_retention_iva')) {
            el('oc_retention_iva').textContent = '$' + fmtMoney(data.retention_iva_amount);
        }
        if (data.retention_iva_rate !== undefined) {
            var retIvaRow = el('oc_retention_iva_row');
            if (retIvaRow) retIvaRow.style.display = data.retention_iva_rate === null ? 'none' : '';
            if (data.retention_iva_rate !== null && el('oc_retention_iva_label')) {
                el('oc_retention_iva_label').textContent = 'Retenciones IVA (' + fmtRate(data.retention_iva_rate) + '%)';
            }
        }

        if (data.retention_isr_amount !== undefined && el('oc_retention_isr')) {
            el('oc_retention_isr').textContent = '$' + fmtMoney(data.retention_isr_amount);
        }
        if (data.retention_isr_rate !== undefined) {
            var retIsrRow = el('oc_retention_isr_row');
            if (retIsrRow) retIsrRow.style.display = data.retention_isr_rate === null ? 'none' : '';
            if (data.retention_isr_rate !== null && el('oc_retention_isr_label')) {
                el('oc_retention_isr_label').textContent = 'Retenciones ISR (' + fmtRate(data.retention_isr_rate) + '%)';
            }
        }

        var tot = data.total_with_iva !== undefined ? data.total_with_iva : data.total;
        if (tot !== undefined && el('oc_total')) el('oc_total').textContent = '$' + fmtMoney(tot);

        // Notificar a otros módulos (ej. modal de hitos) que el total cambió.
        var totalNumber = parseFloat(tot);
        if (isFinite(totalNumber)) {
            document.dispatchEvent(new CustomEvent('oc:totals-updated', {
                detail: { total: totalNumber }
            }));
        }
    }

    function removeEmptyRow() {
        var er = document.getElementById('oc_empty_row');
        if (er) er.remove();
    }

    function addEmptyRowIfNeeded() {
        if (tbody.querySelectorAll('tr.oc-item-row').length === 0) {
            var tr = document.createElement('tr');
            tr.id = 'oc_empty_row';
            tr.innerHTML = '<td colspan="8" class="text-center text-muted py-4">'
                + '<i class="ri-inbox-line fs-4 d-block mb-1 opacity-50"></i>'
                + 'Sin conceptos registrados.</td>';
            tbody.appendChild(tr);
        }
    }

    // ── Toggle panel agregar ──────────────────────────────────────────────
    if (btnToggle && addPanel) {
        btnToggle.addEventListener('click', function () {
            addPanel.style.display = addPanel.style.display === 'none' ? '' : 'none';
        });
    }

    // ── Guardar cantidad/precio inline ───────────────────────────────────
    function saveField(input) {
        var val = input.dataset.field === 'unit_price'
            ? parsePrice(input.value)
            : parseFloat(input.value);
        if (isNaN(val) || val < 0) {
            input.value = input.dataset.original;
            formatPriceInput(input);
            return;
        }
        var originalVal = input.dataset.field === 'unit_price'
            ? parsePrice(input.dataset.original)
            : parseFloat(input.dataset.original);
        var deltaTolerance = input.dataset.field === 'unit_price' ? 1e-9 : 1e-4;
        if (Math.abs(val - originalVal) < deltaTolerance) {
            formatPriceInput(input);
            return;
        }

        var itemId = input.dataset.itemId;
        var field  = input.dataset.field;
        var url    = '{{ route("purchase_orders.items.store", $purchaseOrder) }}/' + itemId;

        var body = {};
        body[field] = val;
        input.disabled = true;

        fetch(url, { method: 'PATCH', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) })
        .then(function (r) { if (!r.ok) throw r; return r.json(); })
        .then(function (data) {
            if (field === 'unit_price' && data.unit_price !== undefined) {
                input.dataset.original = String(data.unit_price);
                input.value = String(data.unit_price);
            } else {
                input.dataset.original = val;
            }
            input.disabled = false;
            formatPriceInput(input);
            input.classList.add('is-saved');
            setTimeout(function () { input.classList.remove('is-saved'); }, 1200);
            // actualizar importe de la fila
            var row = input.closest('tr.oc-item-row');
            if (row) {
                var qty   = parseFloat(row.querySelector('[data-field="quantity"]')?.value || 0);
                var price = parsePrice(row.querySelector('[data-field="unit_price"]')?.value || 0);
                var imp   = row.querySelector('.oc-importe');
                if (imp) imp.textContent = '$' + fmtMoney(qty * price);
            }
            updateTotals(data);
            Toastify({ text: 'Valor guardado', duration: 2000, gravity: 'bottom', position: 'right', className: 'bg-success', stopOnFocus: false }).showToast();
        })
        .catch(function () {
            input.value = input.dataset.original;
            input.disabled = false;
            formatPriceInput(input);
            input.classList.add('is-error');
            setTimeout(function () { input.classList.remove('is-error'); }, 2000);
            Toastify({ text: 'Error al guardar. Intenta de nuevo.', duration: 3000, gravity: 'bottom', position: 'right', className: 'bg-danger', stopOnFocus: false }).showToast();
        });
    }

    // Guardar fecha entrega
    function saveDelivery(input) {
        var val  = input.value.trim();
        var orig = input.dataset.original || '';
        if (val === orig) return;

        var itemId = input.dataset.itemId;
        var url    = '{{ route("purchase_orders.items.store", $purchaseOrder) }}/' + itemId;

        input.disabled = true;

        fetch(url, { method: 'PATCH', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ delivery_date: val }) })
        .then(function (r) { if (!r.ok) throw r; return r.json(); })
        .then(function () {
            input.dataset.original = val;
            input.disabled = false;
            input.classList.add('is-saved');
            setTimeout(function () { input.classList.remove('is-saved'); }, 1200);
            Toastify({ text: 'Fecha de entrega guardada', duration: 2000, gravity: 'bottom', position: 'right', className: 'bg-success', stopOnFocus: false }).showToast();
        })
        .catch(function () {
            input.value = orig;
            input.disabled = false;
            Toastify({ text: 'Error al guardar fecha', duration: 3000, gravity: 'bottom', position: 'right', className: 'bg-danger', stopOnFocus: false }).showToast();
        });
    }

    if (tbody) {
        tbody.querySelectorAll('.oc-price-input').forEach(formatPriceInput);
        tbody.addEventListener('focusin', function (e) {
            var pi = e.target.closest('.oc-price-input');
            if (pi) unformatPriceInput(pi);
        });
        tbody.addEventListener('blur', function (e) {
            var qi = e.target.closest('.oc-qty-input');
            if (qi) saveField(qi);
            var di = e.target.closest('.oc-delivery-input');
            if (di) saveDelivery(di);
        }, true);
        tbody.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            var qi = e.target.closest('.oc-qty-input, .oc-delivery-input');
            if (qi) { e.preventDefault(); qi.blur(); }
        });
    }

    // ── Eliminar concepto ─────────────────────────────────────────────────
    if (tbody) {
        tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('.oc-item-delete');
            if (!btn) return;
            if (!confirm('¿Eliminar este concepto?')) return;
            btn.disabled = true;
            var itemId = btn.dataset.itemId;
            var url    = '{{ route("purchase_orders.items.store", $purchaseOrder) }}/' + itemId;
            fetch(url, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
            .then(function (r) { if (!r.ok) throw r; return r.json(); })
            .then(function (data) {
                var tr = btn.closest('tr.oc-item-row');
                tr.style.transition = 'opacity .25s';
                tr.style.opacity = '0';
                setTimeout(function () {
                    tr.remove();
                    renumber();
                    updateBadge();
                    addEmptyRowIfNeeded();
                    updateTotals(data);
                }, 280);
            })
            .catch(function () {
                btn.disabled = false;
                Toastify({ text: 'Error al eliminar concepto', duration: 3000, gravity: 'bottom', position: 'right', className: 'bg-danger', stopOnFocus: false }).showToast();
            });
        });
    }

    // ── Agregar concepto: búsqueda ────────────────────────────────────────
    var searchInput    = document.getElementById('oc_search_input');
    var searchDropdown = document.getElementById('oc_search_dropdown');
    var stateSearch    = document.getElementById('oc_state_search');
    var stateSelected  = document.getElementById('oc_state_selected');
    var stateManual    = document.getElementById('oc_state_manual');
    var selectedData   = {};

    if (searchInput) {
        var searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            var q = this.value.trim();
            if (q.length < 2) { searchDropdown.classList.add('d-none'); return; }
            searchTimer = setTimeout(function () {
                fetch('{{ route("concepts.search") }}' + '?type={{ $conceptSearchType }}&q=' + encodeURIComponent(q), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                .then(function (r) { return r.json(); })
                .then(function (list) {
                    searchDropdown.innerHTML = '';
                    if (!list.length) {
                        searchDropdown.innerHTML =
                            '<li class="list-group-item text-center text-muted py-3 fs-13">'
                            + '<i class="ri-search-line me-1"></i>Sin resultados para «' + escHtml(q) + '»</li>';
                        searchDropdown.classList.remove('d-none');
                        return;
                    }
                    list.forEach(function (c) {
                        var li = document.createElement('li');
                        li.className = 'list-group-item list-group-item-action py-3 px-3';
                        li.style.cursor = 'pointer';
                        li.innerHTML =
                            '<div class="d-flex justify-content-between align-items-start gap-2">'
                            + '<div class=" overflow-hidden">'
                            + '<span class="fw-bold d-block">' + escHtml(c.code) + '</span>'
                            + '<span class="text-muted fs-13 d-block text-truncate">' + escHtml(c.description) + '</span>'
                            + '</div>'
                            + '<span class="badge bg-light text-dark border align-self-center">'
                            + escHtml(c.unit) + '</span>'
                            + '</div>';
                        // pointerdown evita que el input pierda foco antes del click en móvil
                        li.addEventListener('pointerdown', function (e) {
                            e.preventDefault();
                            selectConcept(c);
                            searchDropdown.classList.add('d-none');
                        });
                        searchDropdown.appendChild(li);
                    });
                    searchDropdown.classList.remove('d-none');
                });

            }, 350);
        });

        document.addEventListener('click', function (e) {
            if (!searchDropdown.contains(e.target) && e.target !== searchInput) {
                searchDropdown.classList.add('d-none');
            }
        });
    }

    function selectConcept(c) {
        selectedData = c;
        document.getElementById('oc_preview_code').textContent = c.code || '';
        document.getElementById('oc_preview_desc').textContent = c.description || '';
        document.getElementById('oc_preview_unit').textContent = c.unit || '';
        document.getElementById('oc_inp_price').value = c.unit_price || '';
        stateSearch.classList.add('d-none');
        stateSelected.classList.remove('d-none');
        if (stateManual) stateManual.classList.add('d-none');
    }

    var btnChange = document.getElementById('oc_btn_change');
    if (btnChange) btnChange.addEventListener('click', function () {
        stateSelected.classList.add('d-none');
        stateSearch.classList.remove('d-none');
        if (searchInput) { searchInput.value = ''; searchInput.focus(); }
        selectedData = {};
    });

    var btnManual = document.getElementById('oc_btn_add_manual');
    if (btnManual) btnManual.addEventListener('click', function () {
        stateSearch.classList.add('d-none');
        if (stateManual) stateManual.classList.remove('d-none');
    });

    var btnBackSearch = document.getElementById('oc_btn_back_search');
    if (btnBackSearch) btnBackSearch.addEventListener('click', function () {
        if (stateManual) stateManual.classList.add('d-none');
        stateSearch.classList.remove('d-none');
    });

    // ── Guardar concepto seleccionado ─────────────────────────────────────
    function addItem(payload) {
        var url = '{{ route("purchase_orders.items.store", $purchaseOrder) }}';
        var fd  = new FormData();
        fd.append('_token', csrf);
        Object.keys(payload).forEach(function (k) { fd.append(k, payload[k]); });

        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd
        })
        .then(function (r) { if (!r.ok) throw r; return r.json(); })
        .then(function (data) {
            removeEmptyRow();
            var tr = document.createElement('tr');
            tr.className      = 'oc-item-row oc-item-new';
            tr.dataset.itemId = data.id;
            tr.innerHTML =
                '<td class="oc-row-num text-muted fs-12">' + (tbody.querySelectorAll('tr.oc-item-row').length) + '</td>'
                + '<td>' + escHtml(data.description || '') + '</td>'
                + '<td class="fs-12">' + escHtml(data.unit || '') + '</td>'
                + '<td class="text-end"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end oc-qty-input" style="max-width:100px;display:inline-block;" value="' + escHtml(String(data.quantity)) + '" data-item-id="' + escHtml(String(data.id)) + '" data-field="quantity" data-original="' + escHtml(String(data.quantity)) + '"></td>'
                + '<td class="text-end" style="min-width:140px;"><div class="input-group input-group-sm" style="max-width:130px;display:inline-flex;"><span class="input-group-text py-0 px-2">$</span><input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm text-end oc-qty-input oc-price-input" value="' + escHtml(String(data.unit_price)) + '" data-item-id="' + escHtml(String(data.id)) + '" data-field="unit_price" data-original="' + escHtml(String(data.unit_price)) + '"></div></td>'
                + '<td class="text-end fw-semibold oc-importe">$' + fmtMoney(data.quantity * data.unit_price) + '</td>'
                + '<td><input type="text" maxlength="80" class="form-control form-control-sm oc-delivery-input" value="' + escHtml(data.delivery_date || '') + '" data-item-id="' + escHtml(String(data.id)) + '" data-original="' + escHtml(data.delivery_date || '') + '" placeholder="Ej. 4 SEMANAS"></td>'
                + '<td><button type="button" class="btn btn-soft-danger btn-sm oc-item-delete" data-item-id="' + escHtml(String(data.id)) + '" title="Eliminar"><i class="ri-delete-bin-line"></i></button></td>';
            tbody.appendChild(tr);
            formatPriceInput(tr.querySelector('.oc-price-input'));
            updateBadge();
            updateTotals(data);
            Toastify({ text: 'Concepto agregado', duration: 2000, gravity: 'bottom', position: 'right', className: 'bg-success', stopOnFocus: false }).showToast();
        });
    }

    var btnSaveItem = document.getElementById('oc_btn_save_item');
    if (btnSaveItem) btnSaveItem.addEventListener('click', function () {
        var qty   = document.getElementById('oc_inp_qty').value;
        var price = document.getElementById('oc_inp_price').value;
        var del   = document.getElementById('oc_inp_delivery').value;
        var errEl = document.getElementById('oc_add_error');
        if (!qty || !price) { errEl.textContent = 'Cantidad y P/U son requeridos.'; errEl.classList.remove('d-none'); return; }
        errEl.classList.add('d-none');
        addItem({
            concept_id:    selectedData.id || '',
            description:   selectedData.description || '',
            unit:          selectedData.unit || '',
            quantity:      qty,
            unit_price:    price,
            delivery_date: del,
        })
        .then(function () {
            stateSelected.classList.add('d-none');
            stateSearch.classList.remove('d-none');
            document.getElementById('oc_inp_qty').value = '';
            document.getElementById('oc_inp_price').value = '';
            document.getElementById('oc_inp_delivery').value = '';
            if (searchInput) searchInput.value = '';
            selectedData = {};
        })
        .catch(function () {
            Toastify({ text: 'Error al agregar concepto', duration: 3000, gravity: 'bottom', position: 'right', className: 'bg-danger', stopOnFocus: false }).showToast();
        });
    });

    var btnSaveManual = document.getElementById('oc_btn_save_manual');
    if (btnSaveManual) btnSaveManual.addEventListener('click', function () {
        var desc  = document.getElementById('oc_inp_manual_desc').value.trim();
        var unit  = document.getElementById('oc_inp_manual_unit').value.trim();
        var qty   = document.getElementById('oc_inp_manual_qty').value;
        var price = document.getElementById('oc_inp_manual_price').value;
        var del   = document.getElementById('oc_inp_manual_delivery').value;
        if (!desc || !unit || !qty || !price) { alert('Descripción, Unidad, Cantidad y P/U son requeridos.'); return; }
        addItem({ description: desc, unit: unit, quantity: qty, unit_price: price, delivery_date: del })
        .then(function () {
            stateManual.classList.add('d-none');
            stateSearch.classList.remove('d-none');
            ['oc_inp_manual_desc','oc_inp_manual_unit','oc_inp_manual_qty','oc_inp_manual_price','oc_inp_manual_delivery']
                .forEach(function (id) { document.getElementById(id).value = ''; });
        })
        .catch(function () {
            Toastify({ text: 'Error al agregar concepto', duration: 3000, gravity: 'bottom', position: 'right', className: 'bg-danger', stopOnFocus: false }).showToast();
        });
    });
})();

$(function () {
    // Abrir modal de hito si hay errores de validación relacionados
    @if ($errors->hasBag('default') && old('purchase_order_id') && $canModifyPurchaseOrder)
        var modal = new bootstrap.Modal(document.getElementById('modalCreateMilestone'));
        modal.show();
    @endif

    // ── Validación dinámica del campo Valor en Nuevo Hito ──
    var ocAmount = {{ $importeTotal }};

    function syncMilestoneAmountFromDom() {
        var totalText = ($('#oc_total').text() || '').replace(/[^\d.,-]/g, '').replace(/,/g, '');
        var parsed = parseFloat(totalText);
        if (isFinite(parsed)) {
            ocAmount = parsed;
        }
    }

    document.addEventListener('oc:totals-updated', function (e) {
        var nextTotal = parseFloat(e?.detail?.total);
        if (isFinite(nextTotal)) {
            ocAmount = nextTotal;
            updateMilestoneValueConstraints();
        }
    });

    function updateMilestoneValueConstraints() {
        syncMilestoneAmountFromDom();
        var type  = $('#milestoneValueType').val();
        var $input = $('#milestoneValue');

        if (type === 'porcentaje') {
            $input.attr('max', 100).attr('placeholder', 'Ej. 30 (máx. 100%)');
        } else {
            $input.attr('max', ocAmount).attr('placeholder', 'Ej. 5000.00');
        }

        // Re-validar si ya hay un valor capturado
        if ($input.val() !== '') {
            validateMilestoneValue();
        }
    }

    function validateMilestoneValue() {
        syncMilestoneAmountFromDom();
        var type   = $('#milestoneValueType').val();
        var val    = parseFloat($('#milestoneValue').val());
        var $input = $('#milestoneValue');
        var $fb    = $('#milestoneValueFeedback');
        var valid  = true;
        var msg    = '';

        if (type === 'porcentaje' && val > 100) {
            valid = false;
            msg   = 'El porcentaje no puede ser mayor a 100%.';
        } else if (type === 'fijo' && val > ocAmount) {
            valid = false;
            msg   = 'El valor fijo no puede superar el importe total de la OC (' + ocAmount.toLocaleString('es-MX', {minimumFractionDigits:2}) + ').';
        }

        if (!valid) {
            $input.addClass('is-invalid').removeClass('is-valid');
            $fb.text(msg);
        } else {
            $input.removeClass('is-invalid').addClass('is-valid');
            $fb.text('');
        }

        return valid;
    }

    $('#milestoneValueType').on('change', updateMilestoneValueConstraints);
    $('#milestoneValue').on('input blur', validateMilestoneValue);

    // Bloquear submit si hay error
    $('#modalCreateMilestone form').on('submit', function (e) {
        if ($('#milestoneValue').val() !== '' && !validateMilestoneValue()) {
            e.preventDefault();
        }
    });

    // Limpiar estado al cerrar el modal
    $('#modalCreateMilestone').on('hidden.bs.modal', function () {
        $('#milestoneValue').val('').removeClass('is-invalid is-valid');
        $('#milestoneValueFeedback').text('');
        $('#milestoneValueType').val('fijo').trigger('change');
    });

    // Inicializar
    updateMilestoneValueConstraints();

    // ── Visualización de comprobante SPEI ──
    var speiModalEl = document.getElementById('modalViewSpeiReceipt');
    var speiImageWrap = document.getElementById('speiImageWrap');
    var speiDocWrap = document.getElementById('speiDocWrap');
    var speiImagePreview = document.getElementById('speiImagePreview');
    var speiDocPreview = document.getElementById('speiDocPreview');
    var speiDownloadAction = document.getElementById('speiDownloadAction');
    var speiTitle = document.getElementById('viewSpeiTitle');

    $(document).on('click', '.js-open-spei-modal', function () {
        var $btn = $(this);
        var previewUrl = $btn.data('speiPreviewUrl');
        var downloadUrl = $btn.data('speiDownloadUrl');
        var fileName = $btn.data('speiName') || 'Comprobante SPEI';
        var isImage = String($btn.data('speiIsImage')) === '1';

        if (speiTitle) {
            speiTitle.innerHTML = '<i class="ri-image-2-line me-1"></i> Comprobante SPEI - ' + fileName;
        }

        if (speiDownloadAction) {
            speiDownloadAction.setAttribute('href', downloadUrl || '#');
        }

        if (isImage) {
            speiDocWrap.classList.add('d-none');
            speiImageWrap.classList.remove('d-none');
            speiImagePreview.setAttribute('src', previewUrl || '');
            speiDocPreview.setAttribute('src', '');
        } else {
            speiImageWrap.classList.add('d-none');
            speiDocWrap.classList.remove('d-none');
            speiDocPreview.setAttribute('src', previewUrl || '');
            speiImagePreview.setAttribute('src', '');
        }

        if (speiModalEl) {
            bootstrap.Modal.getOrCreateInstance(speiModalEl).show();
        }
    });

    if (speiModalEl) {
        speiModalEl.addEventListener('hidden.bs.modal', function () {
            if (speiImagePreview) speiImagePreview.setAttribute('src', '');
            if (speiDocPreview) speiDocPreview.setAttribute('src', '');
            if (speiImageWrap) speiImageWrap.classList.add('d-none');
            if (speiDocWrap) speiDocWrap.classList.add('d-none');
            if (speiTitle) speiTitle.innerHTML = '<i class="ri-image-2-line me-1"></i> Comprobante SPEI';
            if (speiDownloadAction) speiDownloadAction.setAttribute('href', '#');
        });
    }

    // ── Visualización de evidencias ──
    var evidenceModalEl = document.getElementById('modalViewEvidence');
    var evidenceImageWrap = document.getElementById('evidenceImageWrap');
    var evidenceDocWrap = document.getElementById('evidenceDocWrap');
    var evidenceImagePreview = document.getElementById('evidenceImagePreview');
    var evidenceDocPreview = document.getElementById('evidenceDocPreview');
    var evidenceDownloadAction = document.getElementById('evidenceDownloadAction');
    var evidenceTitle = document.getElementById('viewEvidenceTitle');

    $(document).on('click', '.js-open-evidence-modal', function () {
        var $btn = $(this);
        var previewUrl = $btn.data('evidencePreviewUrl');
        var downloadUrl = $btn.data('evidenceDownloadUrl');
        var fileName = $btn.data('evidenceName') || 'Evidencia';
        var isImage = String($btn.data('evidenceIsImage')) === '1';

        if (evidenceTitle) {
            evidenceTitle.innerHTML = '<i class="ri-image-2-line me-1"></i> Evidencia - ' + fileName;
        }

        if (evidenceDownloadAction) {
            evidenceDownloadAction.setAttribute('href', downloadUrl || '#');
        }

        if (isImage) {
            evidenceDocWrap.classList.add('d-none');
            evidenceImageWrap.classList.remove('d-none');
            evidenceImagePreview.setAttribute('src', previewUrl || '');
            evidenceDocPreview.setAttribute('src', '');
        } else {
            evidenceImageWrap.classList.add('d-none');
            evidenceDocWrap.classList.remove('d-none');
            evidenceDocPreview.setAttribute('src', previewUrl || '');
            evidenceImagePreview.setAttribute('src', '');
        }

        if (evidenceModalEl) {
            bootstrap.Modal.getOrCreateInstance(evidenceModalEl).show();
        }
    });

    if (evidenceModalEl) {
        evidenceModalEl.addEventListener('hidden.bs.modal', function () {
            if (evidenceImagePreview) evidenceImagePreview.setAttribute('src', '');
            if (evidenceDocPreview) evidenceDocPreview.setAttribute('src', '');
            if (evidenceImageWrap) evidenceImageWrap.classList.add('d-none');
            if (evidenceDocWrap) evidenceDocWrap.classList.add('d-none');
            if (evidenceTitle) evidenceTitle.innerHTML = '<i class="ri-image-2-line me-1"></i> Evidencia';
            if (evidenceDownloadAction) evidenceDownloadAction.setAttribute('href', '#');
        });
    }
});
</script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('adminInvoiceForm');
    if (!form) return;

    var xmlInput = document.getElementById('adminInvoiceXmlFile');
    var folioInput = document.getElementById('adminInvoiceFolio');
    if (!xmlInput || !folioInput) return;

    function readFiscalFolioFromXmlText(xmlText) {
        try {
            var parser = new DOMParser();
            var xmlDoc = parser.parseFromString(xmlText, 'application/xml');
            if (xmlDoc.querySelector('parsererror')) return null;

            var nodes = xmlDoc.getElementsByTagName('*');
            for (var i = 0; i < nodes.length; i++) {
                var node = nodes[i];
                if (node.localName !== 'TimbreFiscalDigital') continue;

                var uuid = (node.getAttribute('UUID') || node.getAttribute('Uuid') || node.getAttribute('uuid') || '').trim();
                if (!uuid) return null;

                var normalized = uuid.toUpperCase();
                var uuidRegex = /^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/;
                return uuidRegex.test(normalized) ? normalized : null;
            }
        } catch (err) {
            return null;
        }

        return null;
    }

    xmlInput.addEventListener('change', function () {
        var file = this.files && this.files.length ? this.files[0] : null;
        if (!file) return;

        var fileName = String(file.name || '').toLowerCase();
        if (!fileName.endsWith('.xml')) return;

        var reader = new FileReader();
        reader.onload = function (e) {
            var xmlText = String((e.target && e.target.result) || '');
            var uuid = readFiscalFolioFromXmlText(xmlText);

            if (!uuid) {
                xmlInput.classList.add('is-invalid');
                return;
            }

            xmlInput.classList.remove('is-invalid');
            folioInput.value = uuid;
        };
        reader.onerror = function () {
            xmlInput.classList.add('is-invalid');
        };
        reader.readAsText(file);
    });
});
</script>
@endpush

@push('scripts')
<script src="/assets/vendor/quill/quill.min.js"></script>
<script>
(function () {
    var modal = document.getElementById('modalPdfAnnexes');
    if (!modal) return;

    var form          = document.getElementById('formPdfAnnexes');
    var quillContrato = null;
    var quillDossier  = null;
    var toolbarOpts   = [
        ['bold', 'italic', 'underline', 'strike'],
        [{ header: [1, 2, 3, false] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['clean']
    ];

    function initQuillContrato() {
        if (quillContrato) return;
        quillContrato = new Quill('#editorContrato', { theme: 'snow', modules: { toolbar: toolbarOpts } });
        var tpl = document.getElementById('contrato-init-html');
        if (tpl && tpl.innerHTML.trim()) quillContrato.clipboard.dangerouslyPasteHTML(tpl.innerHTML);
    }

    function initQuillDossier() {
        if (quillDossier) return;
        quillDossier = new Quill('#editorDossier', { theme: 'snow', modules: { toolbar: toolbarOpts } });
        var tpl = document.getElementById('dossier-init-html');
        if (tpl && tpl.innerHTML.trim()) quillDossier.clipboard.dangerouslyPasteHTML(tpl.innerHTML);
    }

    // Initialize editor for the active tab when modal is first shown
    modal.addEventListener('shown.bs.modal', function () {
        var active = modal.querySelector('.tab-pane.active');
        if (!active) return;
        if (active.id === 'tabContrato') initQuillContrato();
        if (active.id === 'tabDossier')  initQuillDossier();
    });

    // Initialize editor when switching tabs
    modal.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function (e) {
            var target = e.target.getAttribute('data-bs-target');
            if (target === '#tabContrato') initQuillContrato();
            if (target === '#tabDossier')  initQuillDossier();
        });
    });

    function collectHtml() {
        if (quillContrato) document.getElementById('contrato_html_input').value = quillContrato.root.innerHTML;
        if (quillDossier)  document.getElementById('dossier_html_input').value  = quillDossier.root.innerHTML;
    }

    document.getElementById('btnSaveAnnex').addEventListener('click', function () {
        collectHtml();
        form.action = '{{ route('purchase_orders.annex.save', $purchaseOrder) }}';
        form.target = '_self';
        form.submit();
    });

    document.getElementById('btnPdfAnnexes').addEventListener('click', function () {
        collectHtml();
        form.action = '{{ route('purchase_orders.pdfWithAnnexes', $purchaseOrder) }}';
        form.target = '_blank';
        form.submit();
        setTimeout(function () { form.target = '_self'; }, 500);
    });
})();
</script>
@endpush
