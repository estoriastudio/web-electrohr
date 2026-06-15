@extends('layouts.app')

@section('page_title', 'SOLCOM #' . $purchaseRequest->folio)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchase_requests.index') }}">Solicitudes de Compra</a></li>
    <li class="breadcrumb-item active">SOLCOM #{{ $purchaseRequest->folio }}</li>
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

@php
    $statusMap = [
        'pending'           => ['label' => 'Pendiente',          'class' => 'bg-warning-subtle text-warning'],
        'linked'            => ['label' => 'Ligado',             'class' => 'bg-info-subtle text-info'],
        'sent_to_purchasing'=> ['label' => 'En Compras',         'class' => 'bg-primary-subtle text-primary'],
        'changes_requested' => ['label' => 'Cambios Solicitados','class' => 'bg-danger-subtle text-danger'],
        'completed'         => ['label' => 'Finalizado',         'class' => 'bg-success-subtle text-success'],
    ];
    $s = $statusMap[$purchaseRequest->status] ?? ['label' => $purchaseRequest->status, 'class' => 'bg-secondary-subtle text-secondary'];
@endphp

{{-- ── Header ── --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">
                            <i class="ri-shopping-cart-2-line me-2 text-primary"></i>
                            Solicitud de Compra
                            <span class="text-primary fw-bold">Folio #{{ $purchaseRequest->folio }}</span>
                        </h4>
                        @if ($purchaseRequest->code)
                            <p class="text-muted mb-1 fs-13">Código: {{ $purchaseRequest->code }}</p>
                        @endif
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>

                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">
                                <i class="ri-calendar-event-line me-1"></i>
                                Solicitud: {{ $purchaseRequest->request_date?->format('d/m/Y') }}
                            </span>
                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">
                                <i class="ri-alarm-line me-1"></i>
                                Necesidad: {{ $purchaseRequest->need_date?->format('d/m/Y') }}
                            </span>
                        </div>

                        <div class="mt-2">
                            @include('purchase_requests.partials._process_map')
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('purchase_requests.pdf', $purchaseRequest) }}"
                           class="btn btn-sm btn-outline-danger" target="_blank">
                            <i class="ri-file-pdf-2-line me-1"></i> Descargar PDF
                        </a>

                        @hasanyrole('admin|orders')
                        <a href="{{ route('purchase_requests.edit', $purchaseRequest) }}"
                           class="btn btn-soft-primary btn-sm">
                            <i class="ri-edit-line me-1"></i>Editar
                        </a>

                        {{-- ENVIAR A COMPRAS: visible si NO está en compras ni finalizada --}}
                        @if (! in_array($purchaseRequest->status, ['sent_to_purchasing', 'completed']))
                        <button type="button" class="btn btn-primary btn-sm"
                                data-bs-toggle="modal" data-bs-target="#modalSendToPurchasing">
                            <i class="ri-send-plane-2-line me-1"></i>Enviar a Compras
                        </button>
                        @endif

                        {{-- SOLICITAR CAMBIOS: visible solo cuando está en compras, para rol orders --}}
                        @if ($purchaseRequest->status === 'sent_to_purchasing')
                        <button type="button" class="btn btn-warning btn-sm"
                                data-bs-toggle="modal" data-bs-target="#modalRequestChanges">
                            <i class="ri-edit-circle-line me-1"></i>Solicitar Cambios
                        </button>
                        @endif
                        @endhasanyrole
                        <a href="{{ route('purchase_requests.index') }}" class="btn btn-light btn-sm">
                            <i class="ri-arrow-left-line me-1"></i>Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Datos Generales ── --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-information-line me-2 text-primary"></i>Datos Generales</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-md-4">
                        <p class="text-muted fs-12 mb-1">Proyecto</p>
                        <p class="fw-semibold mb-0">{{ $purchaseRequest->project?->name ?? '—' }}</p>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <p class="text-muted fs-12 mb-1">Obra</p>
                        <p class="fw-semibold mb-0">{{ $purchaseRequest->projectWork?->name ?? '—' }}</p>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <p class="text-muted fs-12 mb-1">Zona</p>
                        <p class="fw-semibold mb-0">{{ $purchaseRequest->zone }}</p>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <p class="text-muted fs-12 mb-1">Dirección de Entrega</p>
                        <p class="fw-semibold mb-0">{{ $purchaseRequest->delivery_address }}</p>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <p class="text-muted fs-12 mb-1">Descripción Corta del Proyecto-Consecutivo</p>
                        <p class="fw-semibold mb-0">{{ $purchaseRequest->short_description }}</p>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <p class="text-muted fs-12 mb-1">Solicitud Elaborada Por</p>
                        <p class="fw-semibold mb-0">{{ $purchaseRequest->requestedBy?->name ?? '—' }}</p>
                    </div>
                    @if ($purchaseRequest->assignedTo)
                    <div class="col-sm-6 col-md-4">
                        <p class="text-muted fs-12 mb-1">Asignado a Compras</p>
                        <p class="fw-semibold mb-0">
                            <span class="badge bg-primary-subtle text-primary py-1 px-2">
                                <i class="ri-user-line me-1"></i>{{ $purchaseRequest->assignedTo->name }}
                            </span>
                        </p>
                    </div>
                    @endif
                        <p class="fw-semibold mb-0">{{ $purchaseRequest->requestedBy?->name ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Conceptos ── --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="ri-list-check me-2 text-primary"></i>Conceptos</h5>
                <span id="solcom_items_count" class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                    {{ $purchaseRequest->items->count() }} ítem(s)
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th class="text-muted fs-12">#</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Unidad</th>
                                <th class="text-end text-muted fs-12">Solicitada</th>
                                <th class="text-primary fs-12">
                                    <i class="ri-pencil-line me-1"></i>A Comprar
                                </th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="solcom_items_tbody">
                            @forelse ($purchaseRequest->items as $index => $item)
                                <tr class="solcom-item-row">
                                    <td class="solcom-row-num text-muted fs-12">{{ $index + 1 }}</td>
                                    <td><span class="fw-semibold">{{ $item->code }}</span></td>
                                    <td>{{ $item->description }}</td>
                                    <td>{{ $item->unit }}</td>
                                    <td class="text-end text-muted fs-13">{{ (int) $item->requested_quantity }}</td>
                                    <td style="min-width:170px">
                                        @hasanyrole('admin|orders')
                                        <div class="input-group">
                                            <button type="button" class="btn btn-light border solcom-qty-minus px-3" title="Restar">
                                                <i class="ri-subtract-line"></i>
                                            </button>
                                            <input type="number"
                                                   class="form-control text-center fw-bold fs-15 solcom-qty-input"
                                                   value="{{ (int) $item->purchase_quantity }}"
                                                   data-item-id="{{ $item->id }}"
                                                   data-original="{{ (int) $item->purchase_quantity }}"
                                                   step="1"
                                                   min="0"
                                                   inputmode="numeric">
                                            <button type="button" class="btn btn-light border solcom-qty-plus px-3" title="Sumar">
                                                <i class="ri-add-line"></i>
                                            </button>
                                        </div>
                                        @else
                                        <span class="fw-bold fs-15">{{ (int) $item->purchase_quantity }}</span>
                                        @endhasanyrole
                                    </td>
                                    <td>
                                        @hasanyrole('admin|orders')
                                        <form action="{{ route('purchase_requests.items.destroy', [$purchaseRequest, $item]) }}"
                                              method="POST"
                                              class="solcom-delete-form">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                        @endhasanyrole
                                    </td>
                                </tr>
                            @empty
                                <tr id="solcom_empty_row">
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-inbox-line fs-4 d-block mb-1 opacity-50"></i>
                                        Sin conceptos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- ── Panel Agregar Concepto ── 
                @hasanyrole('admin|orders')
                <div class="border-top px-3 py-3" id="solcom_add_panel">

                    {{-- Estado A: Búsqueda 
                    <div id="solcom_state_search">
                        <p class="text-muted fs-12 mb-2 fw-medium">
                            <i class="ri-add-circle-line me-1 text-primary"></i>Agregar concepto
                        </p>
                        <div class="position-relative">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="ri-search-line text-muted"></i>
                                </span>
                                <input type="text"
                                       id="solcom_search_input"
                                       class="form-control border-start-0 ps-0"
                                       placeholder="Buscar por código o descripción…"
                                       autocomplete="off"
                                       inputmode="text">
                            </div>
                            <ul id="solcom_search_dropdown"
                                class="list-group position-absolute w-100 shadow d-none"
                                style="top:100%;left:0;max-height:280px;overflow-y:auto;z-index:1050"></ul>
                        </div>
                    </div>

                    {{-- Estado B: Concepto seleccionado + cantidades 
                    <div id="solcom_state_selected" class="d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-success fs-13 fw-medium">
                                <i class="ri-checkbox-circle-line me-1"></i>Concepto seleccionado
                            </span>
                            <button type="button" id="solcom_btn_change"
                                    class="btn btn-link btn-sm p-0 text-muted text-decoration-none">
                                <i class="ri-close-line me-1"></i>Cambiar
                            </button>
                        </div>

                        <div class="rounded-2 border bg-primary-subtle p-3 mb-3">
                            <p class="fw-bold mb-1 fs-15" id="solcom_preview_code"></p>
                            <p class="mb-2 text-body-secondary lh-sm" id="solcom_preview_desc"></p>
                            <span class="badge bg-white text-dark border" id="solcom_preview_unit"></span>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fs-12 text-muted fw-medium mb-1">
                                    Cant. Solicitada <span class="text-danger">*</span>
                                </label>
                                <input type="number"
                                       id="solcom_req_qty"
                                       class="form-control form-control-lg text-center"
                                       placeholder="0"
                                       step="1"
                                       min="1"
                                       inputmode="numeric">
                            </div>
                            <div class="col-6">
                                <label class="form-label fs-12 fw-medium mb-1">
                                    <i class="ri-pencil-line me-1 text-primary"></i>Cant. a Comprar
                                </label>
                                <input type="number"
                                       id="solcom_pur_qty"
                                       class="form-control form-control-lg text-center"
                                       placeholder="0"
                                       step="1"
                                       min="0"
                                       inputmode="numeric">
                            </div>
                        </div>

                        <div id="solcom_add_error" class="text-danger fs-12 mb-2 d-none"></div>
                        <div class="d-grid">
                            <button type="button" id="solcom_btn_add" class="btn btn-primary btn-lg">
                                <i class="ri-add-line me-1"></i>Agregar a la solicitud
                            </button>
                        </div>
                    </div>

                </div>
                @endhasanyrole
                --}}
            </div>
        </div>
    </div>
</div>

{{-- ── Solicitudes de Cambios ── --}}
@if ($purchaseRequest->changeNotes->count() > 0)
<div class="row mb-3">
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-warning">
                    <i class="ri-edit-circle-line me-2"></i>Solicitudes de Cambios
                </h5>
                <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">
                    {{ $purchaseRequest->changeNotes->whereNull('resolved_at')->count() }} pendiente(s)
                </span>
            </div>
            <div class="card-body">
                @foreach ($purchaseRequest->changeNotes as $note)
                <div class="d-flex gap-3 mb-3 {{ $note->isResolved() ? 'opacity-50' : '' }}">
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title {{ $note->isResolved() ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }} rounded-circle fs-14 fw-bold">
                            {{ strtoupper(substr($note->requestedBy?->name ?? '?', 0, 1)) }}
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <span class="fw-semibold fs-13">{{ $note->requestedBy?->name ?? 'Usuario' }}</span>
                                <span class="text-muted fs-12 ms-2">{{ $note->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            @if ($note->isResolved())
                                <span class="badge bg-success-subtle text-success py-1 px-2 fs-12">
                                    <i class="ri-check-line me-1"></i>Resuelto por {{ $note->resolvedBy?->name }}
                                    · {{ $note->resolved_at->format('d/m/Y H:i') }}
                                </span>
                            @else
                                @hasanyrole('admin|orders')
                                <form action="{{ route('purchase_requests.change_notes.resolve', [$purchaseRequest, $note]) }}"
                                      method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="ri-check-line me-1"></i>Marcar como resuelta
                                    </button>
                                </form>
                                @endhasanyrole
                            @endif
                        </div>
                        <p class="mb-0 text-body">{{ $note->text }}</p>
                    </div>
                </div>
                @if (! $loop->last)<hr class="my-2">@endif
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Histórico de Movimientos ── --}}
@if ($history->count() > 0)
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0">
                    <i class="ri-history-line me-2 text-primary"></i>Histórico de Movimientos
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Movimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $event)
                            @php
                                $iconMap = [
                                    'create' => ['ri-add-circle-line text-success', 'bg-success-subtle'],
                                    'update' => ['ri-edit-line text-primary',       'bg-primary-subtle'],
                                    'delete' => ['ri-delete-bin-line text-danger',  'bg-danger-subtle'],
                                ];
                                [$icon, $bgClass] = $iconMap[$event->model_action] ?? ['ri-information-line text-muted', 'bg-light'];
                            @endphp
                            <tr>
                                <td class="text-muted fs-12 text-nowrap">{{ $event->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-xs {{ $bgClass }} rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                            <i class="{{ $icon }} fs-12"></i>
                                        </div>
                                        <span class="fs-13">{{ $event->user?->name ?? 'Sistema' }}</span>
                                    </div>
                                </td>
                                <td class="fs-13">{{ $event->data }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Observaciones ── --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-chat-3-line me-2 text-primary"></i>Observaciones</h5>
            </div>
            <div class="card-body">
                @forelse ($purchaseRequest->observations ?? [] as $note)
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
                            <p class="mb-0 text-muted">{{ $note['text'] }}</p>
                        </div>
                    </div>
                    <hr class="my-2">
                @empty
                    <p class="text-muted fs-13 mb-3">Sin observaciones registradas.</p>
                @endforelse

                @hasanyrole('admin|orders')
                <form action="{{ route('purchase_requests.notes.store', $purchaseRequest) }}" method="POST" class="mt-3">
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
                @endhasanyrole
            </div>
        </div>
    </div>
</div>

{{-- ── Modal: Enviar a Compras ── --}}
@hasanyrole('admin|orders')
<div class="modal fade" id="modalSendToPurchasing" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('purchase_requests.send_to_purchasing', $purchaseRequest) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ri-send-plane-2-line me-2 text-primary"></i>Enviar SOLCOM a Compras
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13 mb-3">
                        Selecciona el usuario de Compras que se encargará de esta SOLCOM <strong>#{{ $purchaseRequest->folio }}</strong>.
                        El documento aparecerá en su Pila de SOLCOM.
                    </p>
                    <label class="form-label fw-medium">Usuario de Compras <span class="text-danger">*</span></label>
                    <select name="assigned_to" class="form-select" required>
                        <option value="">— Selecciona un usuario —</option>
                        @foreach ($purchasingUsers as $user)
                            <option value="{{ $user->id }}"
                                {{ $purchaseRequest->assigned_to == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-send-plane-2-line me-1"></i>Enviar a Compras
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal: Solicitar Cambios ── --}}
<div class="modal fade" id="modalRequestChanges" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('purchase_requests.request_changes', $purchaseRequest) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ri-edit-circle-line me-2 text-warning"></i>Solicitar Cambios en SOLCOM #{{ $purchaseRequest->folio }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13 mb-3">
                        Describe los cambios que necesitas en esta SOLCOM. El documento regresará al equipo de Almacén
                        con tu nota para que realicen los ajustes.
                    </p>
                    <label class="form-label fw-medium">Descripción del cambio requerido <span class="text-danger">*</span></label>
                    <textarea name="change_text" rows="4"
                              class="form-control"
                              placeholder="Ej. Ajustar cantidades del ítem 3, agregar código de catálogo…"
                              required maxlength="2000"></textarea>
                    <div class="form-text text-end"><span id="changeTextCount">0</span>/2000 caracteres</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="ri-edit-circle-line me-1"></i>Solicitar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endhasanyrole

@endsection

@push('styles')
<style>
.solcom-qty-input {
    border-color: var(--bs-border-color);
    transition: border-color .15s, box-shadow .15s, opacity .15s;
    min-width: 90px;
}
.solcom-qty-input:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .15);
}
.solcom-qty-input.is-saved {
    border-color: var(--bs-success) !important;
    box-shadow: 0 0 0 .2rem rgba(var(--bs-success-rgb), .15);
}
.solcom-qty-input.is-error {
    border-color: var(--bs-danger) !important;
    box-shadow: 0 0 0 .2rem rgba(var(--bs-danger-rgb), .15);
}
.solcom-qty-input:disabled {
    opacity: .55;
}
/* Ocultar spinners nativos del input number */
.solcom-qty-input::-webkit-outer-spin-button,
.solcom-qty-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.solcom-qty-input[type=number] {
    -moz-appearance: textfield;
}
</style>
@endpush

@push('scripts')
<script>
// ── Contador de caracteres en modal solicitar cambios ──────────────────
(function () {
    var textarea  = document.querySelector('#modalRequestChanges textarea[name="change_text"]');
    var counter   = document.getElementById('changeTextCount');
    if (textarea && counter) {
        textarea.addEventListener('input', function () {
            counter.textContent = this.value.length;
        });
    }
}());

(function () {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var tbody     = document.getElementById('solcom_items_tbody');
    var badge     = document.getElementById('solcom_items_count');
    var storeUrl  = '{{ route('purchase_requests.items.store', $purchaseRequest) }}';

    if (!tbody) return;

    // ── Helpers ─────────────────────────────────────────────────────
    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    function itemCount() {
        return tbody.querySelectorAll('tr.solcom-item-row').length;
    }

    function updateBadge() {
        if (badge) badge.textContent = itemCount() + ' ítem(s)';
    }

    function renumber() {
        tbody.querySelectorAll('tr.solcom-item-row').forEach(function (row, i) {
            var td = row.querySelector('.solcom-row-num');
            if (td) td.textContent = i + 1;
        });
    }

    // ── Debounce para botones +/- ────────────────────────────────────
    var qtyTimers = {};
    function saveQtyDebounced(input) {
        var id = input.dataset.itemId;
        clearTimeout(qtyTimers[id]);
        qtyTimers[id] = setTimeout(function () { saveQty(input); }, 500);
    }

    // ── Botones +/- ──────────────────────────────────────────────────
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.solcom-qty-minus, .solcom-qty-plus');
        if (!btn) return;
        var input = btn.closest('.input-group').querySelector('.solcom-qty-input');
        if (!input || input.disabled) return;
        var val = parseInt(input.value, 10);
        if (isNaN(val)) val = 0;
        if (btn.classList.contains('solcom-qty-minus')) val = Math.max(0, val - 1);
        else val += 1;
        input.value = val;
        saveQtyDebounced(input);
    });

    // ── Guardar cantidad a comprar (inline, auto-save) ───────────────
    // blur usa capture porque blur no burbujea
    tbody.addEventListener('blur', function (e) {
        var input = e.target.closest('.solcom-qty-input');
        if (!input) return;
        saveQty(input);
    }, true);

    tbody.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var input = e.target.closest('.solcom-qty-input');
        if (!input) return;
        e.preventDefault();
        input.blur();
    });

    function saveQty(input) {
        var val      = parseFloat(input.value);
        var original = parseFloat(input.dataset.original);

        if (isNaN(val) || val < 0) {
            input.value = isNaN(original) ? '' : Math.round(original);
            return;
        }
        if (Math.round(val) === Math.round(original)) return; // sin cambio
        val = Math.round(val);

        var updateUrl = storeUrl + '/' + input.dataset.itemId;

        input.disabled = true;
        input.classList.remove('is-saved', 'is-error');

        var fd = new FormData();
        fd.append('_token', csrfToken);
        fd.append('_method', 'PATCH');
        fd.append('purchase_quantity', val);

        fetch(updateUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd
        })
        .then(function (r) {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        })
        .then(function (data) {
            var saved = parseFloat(data.purchase_quantity);
            input.dataset.original = Math.round(saved);
            input.value            = Math.round(saved);
            input.disabled         = false;
            input.classList.add('is-saved');
            setTimeout(function () { input.classList.remove('is-saved'); }, 1200);
            Toastify({
                text: 'Cantidad guardada',
                duration: 2000,
                gravity: 'bottom',
                position: 'right',
                className: 'bg-success',
                stopOnFocus: false,
            }).showToast();
        })
        .catch(function () {
            input.value    = Math.round(original);
            input.disabled = false;
            input.classList.add('is-error');
            setTimeout(function () { input.classList.remove('is-error'); }, 2000);
            Toastify({
                text: 'Error al guardar. Intenta de nuevo.',
                duration: 3000,
                gravity: 'bottom',
                position: 'right',
                className: 'bg-danger',
                stopOnFocus: false,
            }).showToast();
        });
    }

    // ── AJAX Delete ──────────────────────────────────────────────────
    tbody.addEventListener('submit', function (e) {
        var form = e.target.closest('.solcom-delete-form');
        if (!form) return;
        e.preventDefault();

        if (!confirm('¿Eliminar este concepto?')) return;

        var btn = form.querySelector('button[type=submit]');
        if (btn) btn.disabled = true;
        var tr = form.closest('tr');

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(form)
        })
        .then(function (r) {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        })
        .then(function () {
            tr.style.cssText = 'transition:opacity .25s;opacity:0';
            setTimeout(function () {
                tr.remove();
                renumber();
                updateBadge();
                if (itemCount() === 0) {
                    var emptyTr = document.createElement('tr');
                    emptyTr.id = 'solcom_empty_row';
                    emptyTr.innerHTML =
                        '<td colspan="7" class="text-center text-muted py-4">'
                        + '<i class="ri-inbox-line fs-4 d-block mb-1 opacity-50"></i>'
                        + 'Sin conceptos registrados.</td>';
                    tbody.appendChild(emptyTr);
                }
            }, 280);
        })
        .catch(function () {
            alert('Error al eliminar. Intenta de nuevo.');
            if (btn) btn.disabled = false;
        });
    });

    // ── Panel Agregar Concepto ───────────────────────────────────────
    var stateSearch   = document.getElementById('solcom_state_search');
    var stateSelected = document.getElementById('solcom_state_selected');

    if (!stateSearch) return; // sin permiso admin|orders

    var searchInput  = document.getElementById('solcom_search_input');
    var dropdown     = document.getElementById('solcom_search_dropdown');
    var reqQtyInput  = document.getElementById('solcom_req_qty');
    var purQtyInput  = document.getElementById('solcom_pur_qty');
    var btnAdd       = document.getElementById('solcom_btn_add');
    var btnChange    = document.getElementById('solcom_btn_change');
    var previewCode  = document.getElementById('solcom_preview_code');
    var previewDesc  = document.getElementById('solcom_preview_desc');
    var previewUnit  = document.getElementById('solcom_preview_unit');
    var addError     = document.getElementById('solcom_add_error');

    var currentConcept = null;
    var debounceTimer;

    function showSearch() {
        stateSelected.classList.add('d-none');
        stateSearch.classList.remove('d-none');
        searchInput.value  = '';
        currentConcept     = null;
        dropdown.innerHTML = '';
        dropdown.classList.add('d-none');
    }

    function showSelected(c) {
        currentConcept       = c;
        previewCode.textContent = c.code;
        previewDesc.textContent = c.description;
        previewUnit.textContent = c.unit;
        reqQtyInput.value    = '';
        purQtyInput.value    = '';
        addError.classList.add('d-none');
        stateSearch.classList.add('d-none');
        stateSelected.classList.remove('d-none');
        reqQtyInput.focus();
    }

    // Búsqueda con debounce
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        var q = this.value.trim();
        if (q.length < 2) { dropdown.classList.add('d-none'); dropdown.innerHTML = ''; return; }
        debounceTimer = setTimeout(function () {
            fetch('{{ route('concepts.search') }}?type=materiales&q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                dropdown.innerHTML = '';
                if (!data.length) {
                    dropdown.innerHTML =
                        '<li class="list-group-item list-group-item-light text-muted fs-13 py-2 px-3">Sin resultados</li>';
                } else {
                    data.forEach(function (c) {
                        var li = document.createElement('li');
                        li.className = 'list-group-item list-group-item-action py-2 px-3 fs-13';
                        li.style.cursor = 'pointer';
                        li.innerHTML =
                            '<span class="fw-semibold">' + escHtml(c.code) + '</span>'
                            + ' <span class="text-muted">— ' + escHtml(c.description) + '</span>'
                            + ' <span class="badge bg-light text-dark border ms-1">' + escHtml(c.unit) + '</span>';
                        li.addEventListener('pointerdown', function (e) {
                            e.preventDefault();
                            showSelected(c);
                        });
                        dropdown.appendChild(li);
                    });
                }
                dropdown.classList.remove('d-none');
            });
        }, 300);
    });

    // Cerrar dropdown al tocar fuera
    document.addEventListener('pointerdown', function (e) {
        if (stateSearch && !stateSearch.contains(e.target)) {
            dropdown.classList.add('d-none');
        }
    });

    btnChange.addEventListener('click', showSearch);

    // Al llenar "Solicitada", auto-rellenar "A Comprar" si está vacío
    reqQtyInput.addEventListener('input', function () {
        if (!purQtyInput.value.trim()) {
            purQtyInput.value = this.value;
        }
    });

    // Confirmar con Enter desde "A Comprar"
    purQtyInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); doAdd(); }
    });

    btnAdd.addEventListener('click', doAdd);

    function doAdd() {
        if (!currentConcept) return;

        var req = parseFloat(reqQtyInput.value);
        var pur = parseFloat(purQtyInput.value);

        addError.classList.add('d-none');

        if (isNaN(req) || req <= 0) {
            reqQtyInput.classList.add('is-invalid');
            setTimeout(function () { reqQtyInput.classList.remove('is-invalid'); }, 1500);
            reqQtyInput.focus();
            return;
        }
        if (isNaN(pur) || pur < 0) {
            purQtyInput.classList.add('is-invalid');
            setTimeout(function () { purQtyInput.classList.remove('is-invalid'); }, 1500);
            purQtyInput.focus();
            return;
        }

        btnAdd.disabled = true;

        var fd = new FormData();
        fd.append('_token', csrfToken);
        fd.append('concept_id',          currentConcept.id);
        fd.append('code',                currentConcept.code);
        fd.append('description',         currentConcept.description);
        fd.append('unit',                currentConcept.unit);
        fd.append('requested_quantity',  Math.round(req));
        fd.append('purchase_quantity',   Math.round(pur));

        fetch(storeUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd
        })
        .then(function (r) {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        })
        .then(function (item) {
            appendRow(item);
            updateBadge();
            showSearch();
        })
        .catch(function () {
            addError.textContent = 'Error al agregar. Intenta de nuevo.';
            addError.classList.remove('d-none');
        })
        .finally(function () {
            btnAdd.disabled = false;
        });
    }

    function appendRow(item) {
        // Quitar fila vacía si existe
        var emptyRow = document.getElementById('solcom_empty_row');
        if (emptyRow) emptyRow.remove();

        var num        = itemCount() + 1;
        var destroyUrl = '{{ route('purchase_requests.items.destroy', [$purchaseRequest, '__ID__']) }}'
                             .replace('__ID__', item.id);

        var tr = document.createElement('tr');
        tr.className = 'solcom-item-row';
        tr.innerHTML =
            '<td class="solcom-row-num text-muted fs-12">' + num + '</td>'
            + '<td><span class="fw-semibold">' + escHtml(item.code) + '</span></td>'
            + '<td>' + escHtml(item.description) + '</td>'
            + '<td>' + escHtml(item.unit) + '</td>'
            + '<td class="text-end text-muted fs-13">' + Math.round(parseFloat(item.requested_quantity)) + '</td>'
            + '<td style="min-width:170px">'
            +   '<div class="input-group">'
            +     '<button type="button" class="btn btn-light border solcom-qty-minus px-3" title="Restar"><i class="ri-subtract-line"></i></button>'
            +     '<input type="number" class="form-control text-center fw-bold fs-15 solcom-qty-input"'
            +     ' value="' + Math.round(parseFloat(item.purchase_quantity)) + '"'
            +     ' data-item-id="' + item.id + '"'
            +     ' data-original="' + Math.round(parseFloat(item.purchase_quantity)) + '"'
            +     ' step="1" min="0" inputmode="numeric">'
            +     '<button type="button" class="btn btn-light border solcom-qty-plus px-3" title="Sumar"><i class="ri-add-line"></i></button>'
            +   '</div>'
            + '</td>'
            + '<td>'
            +   '<form action="' + escHtml(destroyUrl) + '" method="POST" class="solcom-delete-form">'
            +     '<input type="hidden" name="_token" value="' + escHtml(csrfToken) + '">'
            +     '<input type="hidden" name="_method" value="DELETE">'
            +     '<button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">'
            +       '<i class="ri-delete-bin-line"></i>'
            +     '</button>'
            +   '</form>'
            + '</td>';

        // Flash verde al agregar
        tr.style.background = 'var(--bs-primary-bg-subtle)';
        setTimeout(function () {
            tr.style.transition = 'background 1s';
            tr.style.background = '';
        }, 50);

        tbody.appendChild(tr);
    }

}());
</script>
@endpush
