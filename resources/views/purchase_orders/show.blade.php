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
@endphp

{{-- ── ALERTA DE AUTORIZACIÓN ── --}}
@if (in_array($purchaseOrder->status, ['emitida', 'pendiente']))
    <div class="alert alert-warning alert-dismissible d-flex align-items-start gap-3 mb-3" role="alert">
        <i class="ri-shield-check-line fs-22 flex-shrink-0 mt-1 text-warning"></i>
        <div class="flex-grow-1">
            <h6 class="alert-heading mb-1 fw-semibold">Orden de compra pendiente de autorización</h6>
            <p class="mb-0 fs-13">
                Esta OC se encuentra en estatus
                <span class="badge {{ $s['class'] }} py-1 px-2 fs-12 ms-1">{{ $s['label'] }}</span>.
                Requiere revisión y aprobación de un administrador antes de proceder con los pagos.
            </p>
        </div>
        @role('admin')
        <form action="{{ route('purchase_orders.approve', $purchaseOrder) }}" method="POST" class="flex-shrink-0 align-self-center">
            @csrf @method('PATCH')
            <button type="submit"
                    class="btn btn-success btn-sm"
                    onclick="return confirm('¿Autorizar la OC #{{ $purchaseOrder->id }}? El estatus cambiará a Autorizada.')">
                <i class="ri-check-double-line me-1"></i> Autorizar OC
            </button>
        </form>
        @endrole
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">{{ $purchaseOrder->currency }}</span>
                            @if ($purchaseOrder->recurrence_type === 'recurrente')
                                <span class="badge bg-purple-subtle text-purple py-1 px-2 fs-12">
                                    Recurrente · {{ ucfirst($purchaseOrder->recurrence_frequency) }}
                                </span>
                            @else
                                <span class="badge bg-light text-dark border py-1 px-2 fs-12">Pago único</span>
                            @endif
                        </div>
                        @if ($purchaseOrder->project || $purchaseOrder->site)
                            <p class="card-text text-muted fs-13 mb-0 mt-1">
                                @if ($purchaseOrder->project)<span><i class="ri-building-2-line me-1"></i>{{ $purchaseOrder->project }}</span>@endif
                                @if ($purchaseOrder->project && $purchaseOrder->site) &nbsp;·&nbsp; @endif
                                @if ($purchaseOrder->site)<span><i class="ri-tools-line me-1"></i>{{ $purchaseOrder->site }}</span>@endif
                            </p>
                        @endif

                        <div class="mt-2">
                            @include('purchase_orders.partials._process_map')
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-22 fw-semibold text-primary">
                            {{ $purchaseOrder->currency }} {{ number_format($importeTotal, 2) }}
                        </div>
                        <div class="text-muted fs-13">
                            Cubierto: <strong>{{ number_format($totalCubierto, 2) }}</strong>
                        </div>
                        <div class="mt-1" style="min-width: 160px;">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: {{ $progressTotal }}%"></div>
                            </div>
                            <small class="text-muted">{{ $progressTotal }}% cubierto</small>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('purchase_orders.pdf', $purchaseOrder) }}"
                           class="btn btn-sm btn-outline-danger" target="_blank">
                            <i class="ri-file-pdf-2-line me-1"></i> Descargar PDF
                        </a>
                        @hasanyrole('admin|Orden de compra')
                        @if ($purchaseOrder->status !== 'autorizada')
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
                @if ($purchaseOrder->status !== 'autorizada')
                <button type="button" class="btn btn-sm btn-primary" id="btn_toggle_add_concept">
                    <i class="ri-add-line me-1"></i> Agregar concepto
                </button>
                @endif
                @endhasanyrole
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    @php $ocLocked = $purchaseOrder->status === 'autorizada'; @endphp

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
                                        ${{ number_format($item->unit_price, 2) }}
                                        @endif
                                        @else
                                        ${{ number_format($item->unit_price, 2) }}
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
                            <tr class="border-top">
                                <td class="fw-bold fs-14">Total</td>
                                <td class="text-end fw-bold fs-14 text-primary" id="oc_total">${{ number_format($purchaseOrder->total_with_iva, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Panel agregar concepto --}}
                @hasanyrole('admin|Orden de compra')
                @if ($purchaseOrder->status !== 'autorizada')
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
                                <input type="number" id="oc_inp_price" class="form-control" step="0.01" min="0" placeholder="0.00">
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
                                <input type="number" id="oc_inp_manual_price" class="form-control" step="0.01" min="0" placeholder="0.00">
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

    @forelse ($purchaseOrder->milestones as $milestone)
        @php
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
        @endphp

        <div class="col-md-6 col-xl-6 mb-3">
            <div class="card h-100 {{ $semBorder }} border-2">
                <div class="card-header d-flex justify-content-between align-items-center py-2 {{ $semHeader }}">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        {{-- Indicador semáforo --}}
                        <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $semDotColor }};flex-shrink:0;"></span>
                        <span class="fw-semibold fs-14">Hito #{{ $milestone->id }}</span>
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
                        @if ($milestone->payments->count() === 0)
                            <button type="button" class="btn btn-xs btn-soft-primary btn-sm"
                                    title="Editar hito"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditMilestone{{ $milestone->id }}">
                                <i class="ri-edit-line fs-13"></i>
                            </button>
                            <form action="{{ route('milestones.destroy', $milestone) }}" method="POST"
                                  onsubmit="return confirm('¿Eliminar este hito y todos sus pagos?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-soft-danger btn-sm" title="Eliminar hito">
                                    <i class="ri-delete-bin-line fs-13"></i>
                                </button>
                            </form>
                        @else
                            {{-- Tiene pagos: solo edición parcial de fechas permitida --}}
                            <button type="button" class="btn btn-xs btn-soft-primary btn-sm"
                                    title="Editar fechas del hito (tiene pagos: solo fechas editables)"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditMilestone{{ $milestone->id }}">
                                <i class="ri-edit-line fs-13"></i>
                            </button>
                            <button type="button" class="btn btn-xs btn-soft-secondary btn-sm"
                                    title="No se puede eliminar: el hito tiene pagos registrados" disabled>
                                <i class="ri-lock-line fs-13"></i>
                            </button>
                        @endif
                        @endhasanyrole
                    </div>
                </div>

                <div class="card-body pb-2">
                    {{-- Info principal --}}
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted fs-12">Tipo valor:</span>
                        <span class="fs-12 fw-medium">{{ $tipoValorMap[$milestone->value_type] ?? $milestone->value_type }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted fs-12">Valor:</span>
                        <span class="fs-12 fw-semibold">
                            @if ($milestone->value_type === 'porcentaje')
                                {{ $milestone->value }}%
                            @else
                                {{ $purchaseOrder->currency }} {{ number_format($milestone->value, 2) }}
                            @endif
                        </span>
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
                        @hasanyrole('admin|Pagos')
                        @if (!$isComplete)
                            @if ($purchaseOrder->status === 'autorizada')
                                <button type="button" class="btn btn-xs btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalCreatePayment{{ $milestone->id }}">
                                    <i class="ri-add-line"></i> Pago
                                </button>
                            @else
                                <button type="button" class="btn btn-xs btn-primary btn-sm"
                                        disabled
                                        title="La OC debe estar Autorizada para registrar pagos">
                                    <i class="ri-add-line"></i> Pago
                                </button>
                            @endif
                        @endif
                        @endhasanyrole
                    </div>

                    @if ($milestone->payments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th>Folio</th>
                                        <th>Monto</th>
                                        @if (($milestone->payment_condition ?? 'credito') === 'credito')
                                        <th>Fecha Factura</th>
                                        @endif
                                        <th>Fecha Pago</th>
                                        <th>Estatus</th>
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

                                            // Transiciones permitidas por estatus
                                            $transitions = match ($payment->status) {
                                                'por_autorizar' => [
                                                    'autorizado' => ['label' => 'Autorizar',  'icon' => 'ri-check-line',           'btn' => 'btn-soft-info',    'confirm' => '¿Autorizar este pago?'],
                                                    'rechazado'  => ['label' => 'Rechazar',   'icon' => 'ri-close-circle-line',    'btn' => 'btn-soft-danger',  'confirm' => '¿Rechazar este pago?'],
                                                ],
                                                'autorizado' => [
                                                    'pagado' => ['label' => 'Marcar pagado', 'icon' => 'ri-money-dollar-circle-line', 'btn' => 'btn-soft-success', 'confirm' => '¿Marcar como PAGADO? Esto actualizará el saldo del hito.'],
                                                ],
                                                'rechazado' => [
                                                    'por_autorizar' => ['label' => 'Reactivar', 'icon' => 'ri-arrow-go-back-line', 'btn' => 'btn-soft-warning', 'confirm' => '¿Reactivar este pago a «Por autorizar»?'],
                                                ],
                                                default => [], // pagado: sin transiciones
                                            };
                                        @endphp
                                        <tr>
                                            <td class="fw-medium">{{ $payment->folio }}</td>
                                            <td>{{ number_format($payment->amount, 2) }}</td>
                                            @if (($milestone->payment_condition ?? 'credito') === 'credito')
                                            <td>
                                                {{ $payment->invoice_date ? $payment->invoice_date->format('d/m/Y') : '—' }}
                                            </td>
                                            @endif
                                            <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge {{ $ps['class'] }} py-1 px-1 fs-10">{{ $ps['label'] }}</span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    {{-- Botones de transición de estatus --}}
                                                    @foreach ($transitions as $newStatus => $transition)
                                                        @if ($newStatus === 'autorizado')
                                                            @role('admin')
                                                            <form action="{{ route('payments.update', $payment) }}" method="POST">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status" value="{{ $newStatus }}">
                                                                <button type="submit"
                                                                        class="btn btn-xs {{ $transition['btn'] }}"
                                                                        title="{{ $transition['label'] }}"
                                                                        onclick="return confirm('{{ $transition['confirm'] }}')">
                                                                    <i class="{{ $transition['icon'] }}"></i>
                                                                    {{ $transition['label'] }}
                                                                </button>
                                                            </form>
                                                            @endrole
                                                        @else
                                                            @hasanyrole('admin|Pagos')
                                                            <form action="{{ route('payments.update', $payment) }}" method="POST">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status" value="{{ $newStatus }}">
                                                                <button type="submit"
                                                                        class="btn btn-xs {{ $transition['btn'] }}"
                                                                        title="{{ $transition['label'] }}"
                                                                        onclick="return confirm('{{ $transition['confirm'] }}')">
                                                                    <i class="{{ $transition['icon'] }}"></i>
                                                                    {{ $transition['label'] }}
                                                                </button>
                                                            </form>
                                                            @endhasanyrole
                                                        @endif
                                                    @endforeach

                                                    {{-- Contrarecibo PDF (solo hitos crédito) --}}
                                                    @if (($milestone->payment_condition ?? 'credito') === 'credito')
                                                    @hasanyrole('admin|Pagos|Orden de compra')
                                                    <a href="{{ route('payments.contrarecibo', $payment) }}"
                                                       target="_blank"
                                                       class="btn btn-xs btn-soft-secondary"
                                                       title="Descargar contrarecibo PDF">
                                                        <i class="ri-file-download-line"></i> Contrarecibo
                                                    </a>
                                                    @endhasanyrole
                                                    @endif

                                                    {{-- Comprobante SPEI (subir/ver) --}}
                                                    @hasanyrole('admin|Pagos')
                                                    <button type="button"
                                                            class="btn btn-xs {{ $payment->spei_receipt_path ? 'btn-soft-primary' : 'btn-soft-warning' }}"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalSpeiReceipt{{ $payment->id }}"
                                                            title="{{ $payment->spei_receipt_path ? 'Reemplazar comprobante SPEI' : 'Subir comprobante SPEI' }}">
                                                        <i class="ri-file-upload-line"></i>
                                                        {{ $payment->spei_receipt_path ? 'SPEI' : 'Subir SPEI' }}
                                                    </button>
                                                    @endhasanyrole

                                                    @if ($payment->spei_receipt_path)
                                                    @hasanyrole('admin|Pagos|Orden de compra')
                                                    <a href="{{ route('payments.spei_receipt.download', $payment) }}"
                                                       target="_blank"
                                                       class="btn btn-xs btn-soft-success"
                                                       title="Ver comprobante SPEI">
                                                        <i class="ri-attachment-2"></i>
                                                    </a>
                                                    @endhasanyrole
                                                    @endif

                                                    {{-- Eliminar (solo admin|Pagos) --}}
                                                    @hasanyrole('admin|Pagos')
                                                    <form action="{{ route('payments.destroy', $payment) }}" method="POST"
                                                          onsubmit="return confirm('¿Eliminar este pago?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-xs btn-soft-danger" title="Eliminar pago">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </form>
                                                    @endhasanyrole
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
                                            @if ($payment->spei_receipt_path)
                                            <div class="alert alert-light border py-2 fs-13 mb-3">
                                                Comprobante actual:
                                                <a href="{{ route('payments.spei_receipt.download', $payment) }}" target="_blank" class="fw-semibold">
                                                    {{ $payment->spei_receipt_name ?? 'Ver archivo' }}
                                                </a>
                                            </div>
                                            @endif
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
                                <th>Archivo</th>
                                <th>Fecha</th>
                                <th>Moneda</th>
                                <th class="text-end">Importe</th>
                                <th>Hitos vinculados</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchaseOrder->invoices as $invoice)
                                <tr>
                                    <td>
                                        <i class="ri-file-pdf-2-line text-danger me-1"></i>
                                        <span class="fw-medium">{{ $invoice->file_name }}</span>
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
                                    <td>
                                        @forelse ($invoice->milestones as $im)
                                            <span class="badge bg-info-subtle text-info py-1 px-2 fs-11 me-1">
                                                Hito #{{ $im->id }}
                                            </span>
                                        @empty
                                            <span class="text-muted fs-12">—</span>
                                        @endforelse
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            @hasanyrole('admin|Pagos')
                                            <a href="{{ route('invoices.download', $invoice) }}"
                                               target="_blank"
                                               class="btn btn-xs btn-soft-primary" style="padding: 2px 8px;"
                                               title="Ver / Descargar PDF">
                                                <i class="ri-download-2-line"></i>
                                            </a>
                                            <form action="{{ route('invoices.destroy', $invoice) }}" method="POST"
                                                  onsubmit="return confirm('¿Eliminar la factura {{ $invoice->file_name }}? Esta acción no se puede deshacer.')">
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
                                <td colspan="3" class="text-end fw-semibold fs-13">Total facturado:</td>
                                <td class="text-end fw-bold text-primary">
                                    {{ number_format($purchaseOrder->invoices->sum('amount'), 2) }}
                                </td>
                                <td colspan="2"></td>
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

{{-- ── OBSERVACIONES ── --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-chat-3-line me-1 text-primary"></i> Observaciones</h5>
            </div>
            <div class="card-body" id="oc_obs_container">
                @forelse ($purchaseOrder->observations ?? [] as $note)
                    <div class="d-flex gap-3 mb-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-14 fw-bold">
                                {{ strtoupper(substr($note['user_name'] ?? '?', 0, 1)) }}
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold fs-13">{{ $note['user_name'] ?? 'Usuario' }}</span>
                                <span class="text-muted fs-12">{{ \Carbon\Carbon::parse($note['created_at'])->format('d/m/Y H:i') }}</span>
                            </div>
                            <p class="mb-0 text-body-secondary fs-13">{{ $note['text'] }}</p>
                        </div>
                    </div>
                    <hr class="my-2">
                @empty
                    <p class="text-muted fs-13 mb-3" id="oc_obs_empty">Sin observaciones registradas.</p>
                @endforelse
            </div>
            @hasanyrole('admin|Orden de compra')
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
            @endhasanyrole
        </div>
    </div>
</div>

{{-- ── MODALES DE HITOS (fuera del row para posicionamiento correcto) ── --}}
@foreach ($purchaseOrder->milestones as $milestone)
    @php
        $pendiente = max(0, $milestone->effective_amount - (float)$milestone->covered_amount);
    @endphp

    {{-- MODAL Editar Hito --}}
    <div class="modal fade" id="modalEditMilestone{{ $milestone->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('milestones.update', $milestone) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ri-edit-line me-1"></i> Editar Hito #{{ $milestone->id }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if ($milestone->payments->count() > 0)
                        <div class="alert alert-warning py-2 fs-13 mb-3">
                            <i class="ri-alert-line me-1"></i>
                            Este hito tiene <strong>{{ $milestone->payments->count() }} pago(s)</strong> registrados.
                            Solo se puede modificar las fechas y condiciones. El valor no es editable.
                        </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Condición de pago <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_condition" required>
                                    <option value="contado" {{ ($milestone->payment_condition ?? 'credito') === 'contado' ? 'selected' : '' }}>Contado</option>
                                    <option value="credito" {{ ($milestone->payment_condition ?? 'credito') === 'credito' ? 'selected' : '' }}>Crédito</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Tipo de valor <span class="text-danger">*</span></label>
                                <select class="form-select" name="value_type" required
                                        {{ $milestone->payments->count() > 0 ? 'disabled' : '' }}>
                                    <option value="fijo"       {{ $milestone->value_type === 'fijo'       ? 'selected' : '' }}>Fijo</option>
                                    <option value="porcentaje" {{ $milestone->value_type === 'porcentaje' ? 'selected' : '' }}>Porcentaje</option>
                                </select>
                                @if ($milestone->payments->count() > 0)
                                <input type="hidden" name="value_type" value="{{ $milestone->value_type }}">
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Valor <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" class="form-control"
                                       name="value" value="{{ $milestone->value }}" required
                                       {{ $milestone->payments->count() > 0 ? 'readonly' : '' }}>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Fecha vencimiento</label>
                                <input type="date" class="form-control"
                                       name="due_date" value="{{ $milestone->due_date?->format('Y-m-d') }}">
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

    {{-- MODAL Crear Pago para este hito --}}
    @if (!$milestone->is_complete)
    <div class="modal fade" id="modalCreatePayment{{ $milestone->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('payments.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="milestone_id" value="{{ $milestone->id }}">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ri-money-dollar-circle-line me-1"></i> Nuevo Pago — Hito #{{ $milestone->id }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if (($milestone->payment_condition ?? 'credito') === 'credito')
                        <div class="alert alert-info py-2 fs-13 mb-3">
                            <i class="ri-bank-line me-1"></i>
                            <strong>Hito Crédito:</strong> registra la fecha de factura del proveedor y la fecha en que se programará el pago.
                        </div>
                        @endif
                        <div class="alert alert-light border py-2 fs-13 mb-3">
                            Saldo pendiente del hito: <strong>{{ $purchaseOrder->currency }} {{ number_format($pendiente, 2) }}</strong>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Folio</label>
                                <input type="text" class="form-control" name="folio"
                                       placeholder="Dejar vacío para generar automático">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" max="{{ $pendiente }}"
                                       class="form-control" name="amount" required>
                            </div>
                            @if (($milestone->payment_condition ?? 'credito') === 'credito')
                            <div class="col-md-6">
                                <label class="form-label fw-medium">
                                    Fecha de factura <span class="text-danger">*</span>
                                    <small class="text-muted fw-normal">(cuando la recibe el proveedor)</small>
                                </label>
                                <input type="date" class="form-control" name="invoice_date"
                                       value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">
                                    Fecha de pago programada <span class="text-danger">*</span>
                                    <small class="text-muted fw-normal">(cuando se liquidará)</small>
                                </label>
                                <input type="date" class="form-control" name="payment_date"
                                       value="{{ date('Y-m-d') }}" required>
                            </div>
                            @else
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Fecha de pago <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="payment_date"
                                       value="{{ date('Y-m-d') }}" required>
                            </div>
                            @endif
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Estatus <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" required>
                                    <option value="por_autorizar" selected>Por autorizar</option>
                                    <option value="autorizado">Autorizado</option>
                                    <option value="pagado">Pagado</option>
                                </select>
                            </div>
                            <div class="col-{{ ($milestone->payment_condition ?? 'credito') === 'credito' ? '12' : 'md-6' }}">
                                <label class="form-label fw-medium">Número de referencia</label>
                                <input type="text" class="form-control" name="reference_number"
                                       placeholder="Ej. transferencia bancaria, cheque...">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium">
                                    Comprobante SPEI (Opcional)
                                    <small class="text-muted fw-normal">PDF, JPG, PNG, WEBP - máx. 10MB</small>
                                </label>
                                <input type="file"
                                       class="form-control @error('spei_receipt_file') is-invalid @enderror"
                                       name="spei_receipt_file"
                                       accept=".pdf,.jpg,.jpeg,.png,.webp">
                                @error('spei_receipt_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Registrar pago</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

@endforeach

{{-- MODAL Subir Factura --}}
<div class="modal fade" id="modalCreateInvoice" tabindex="-1" aria-labelledby="modalCreateInvoiceLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="{{ route('invoices.store') }}" method="POST" enctype="multipart/form-data">
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
                                Archivo PDF <span class="text-danger">*</span>
                                <small class="text-muted fw-normal">(máx. 10 MB)</small>
                            </label>
                            <input type="file" class="form-control @error('pdf_file') is-invalid @enderror"
                                   name="pdf_file" accept=".pdf" required>
                            <div class="form-text">
                                El nombre se generará automáticamente:
                                <strong>OC{{ $purchaseOrder->id }}-FACT{{ $purchaseOrder->invoices->count() + 1 }}.pdf</strong>
                            </div>
                            @error('pdf_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                                        @foreach ($purchaseOrder->milestones as $m)
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="milestone_ids[]" value="{{ $m->id }}"
                                                           id="inv_milestone_{{ $m->id }}">
                                                    <label class="form-check-label fs-13" for="inv_milestone_{{ $m->id }}">
                                                        <strong>Hito #{{ $m->id }}</strong>
                                                        <span class="text-muted">
                                                            — {{ $m->type === 'anticipo' ? 'Anticipo' : 'Regular' }}
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
                                   max="{{ $purchaseOrder->amount }}"
                                   class="form-control" id="milestoneValue"
                                   name="value" placeholder="Ej. 5000.00" required>
                            <div class="invalid-feedback" id="milestoneValueFeedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Fecha vencimiento</label>
                            <input type="date" class="form-control" name="due_date">
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
@endhasanyrole

@push('styles')
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

    // ── Helpers ──────────────────────────────────────────────────────────
    function fmtMoney(n) { return parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

    function parsePrice(val) {
        var clean = String(val == null ? '' : val).replace(/,/g, '').trim();
        if (clean === '') return NaN;
        return parseFloat(clean);
    }

    function fmtPriceInput(n) {
        var num = Number(n);
        if (!isFinite(num)) return '';
        return num.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
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
        if (data.subtotal !== undefined && el('oc_subtotal')) el('oc_subtotal').textContent = '$' + fmtMoney(data.subtotal);
        if (data.iva      !== undefined && el('oc_iva'))      el('oc_iva').textContent      = '$' + fmtMoney(data.iva);
        // Actualizar label de impuesto si el servidor devuelve tax_rate
        if (data.tax_rate !== undefined && el('oc_iva_label')) {
            if (data.tax_rate === null) {
                el('oc_iva_label').textContent = 'Impuesto (Exento)';
            } else {
                el('oc_iva_label').textContent = 'IVA (' + parseFloat(data.tax_rate) + '%)';
            }
        }
        var tot = data.total_with_iva !== undefined ? data.total_with_iva : data.total;
        if (tot !== undefined && el('oc_total')) el('oc_total').textContent = '$' + fmtMoney(tot);
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
        if (Math.abs(val - parseFloat(input.dataset.original)) < 0.0001) {
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
            input.dataset.original = val;
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
                fetch('{{ route("concepts.search") }}' + '?type={{ $purchaseOrder->type === "mantenimiento" ? "mantenimiento" : "materiales" }}&q=' + encodeURIComponent(q), {
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
                            + '<div class="flex-grow-1 overflow-hidden">'
                            + '<span class="fw-bold d-block">' + escHtml(c.code) + '</span>'
                            + '<span class="text-muted fs-13 d-block text-truncate">' + escHtml(c.description) + '</span>'
                            + '</div>'
                            + '<span class="badge bg-light text-dark border flex-shrink-0 align-self-center">'
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
    @if ($errors->hasBag('default') && old('purchase_order_id') && $purchaseOrder->status !== 'autorizada')
        var modal = new bootstrap.Modal(document.getElementById('modalCreateMilestone'));
        modal.show();
    @endif

    // ── Validación dinámica del campo Valor en Nuevo Hito ──
    var ocAmount = {{ $purchaseOrder->amount }};

    function updateMilestoneValueConstraints() {
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
});
</script>
@endpush
