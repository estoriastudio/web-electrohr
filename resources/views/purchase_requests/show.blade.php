@extends('layouts.app')

@php use Illuminate\Support\Facades\Storage; @endphp

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
                            @include('layouts.partials._process_map', ['purchaseRequest' => $purchaseRequest])
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('purchase_requests.pdf', $purchaseRequest) }}"
                           class="btn btn-sm btn-outline-danger" target="_blank">
                            <i class="ri-file-pdf-2-line me-1"></i> Descargar PDF
                        </a>

                                @hasanyrole('admin|Solcom')
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

                        @endhasanyrole

                        {{-- SOLICITAR CAMBIOS: disponible para Solcom y Compras cuando está en compras --}}
                        @hasanyrole('admin|Solcom|Orden de compra')
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
                        <p class="text-muted fs-12 mb-1">Obras Vinculadas</p>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse ($purchaseRequest->projectWorks as $projectWork)
                                <span class="badge bg-info-subtle text-info border py-1 px-2">{{ $projectWork->name }}</span>
                            @empty
                                <span class="fw-semibold">{{ $purchaseRequest->projectWork?->name ?? '—' }}</span>
                            @endforelse
                        </div>
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
                        <p class="fw-semibold mb-0">{{ $solmatRequester }}</p>
                    </div>

                    

                    @if ($purchaseRequest->assignedTo)
                    <div class="col-sm-6 col-md-4">
                        <p class="text-muted fs-12 mb-1">Asignado a Compras</p>
                        <div class="d-flex align-items-center gap-2 flex-wrap fw-semibold mb-0">
                            <span class="badge bg-primary-subtle text-primary py-1 px-2">
                                <i class="ri-user-line me-1"></i>{{ $purchaseRequest->assignedTo->name }}
                            </span>
                            @hasanyrole('admin|Solcom|Orden de compra')
                            @if ($purchaseRequest->status === 'sent_to_purchasing')
                            <button type="button"
                                    class="btn btn-sm btn-light border"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalReassignPurchasing"
                                    title="Reasignar usuario de Compras"
                                    aria-label="Reasignar usuario de Compras">
                                <i class="ri-edit-line"></i>
                            </button>
                            @endif
                            @endhasanyrole
                        </div>
                    </div>
                    @endif
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
                <div class="d-flex align-items-center gap-2">
                    <span id="solcom_items_count" class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                        {{ $purchaseRequest->items->count() }} ítem(s)
                    </span>
                    @hasanyrole('admin|Solcom')
                    <button type="button" class="btn btn-sm btn-primary" id="solcom_btn_toggle_add_concept">
                        <i class="ri-add-line me-1"></i>Agregar concepto
                    </button>
                    @endhasanyrole
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th class="text-muted fs-12">#</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th class="text-center" style="min-width: 130px;">
                                    <span data-bs-toggle="tooltip"
                                          data-bs-placement="top"
                                          title="Documento de Especificaciones Técnicas">E.T</span>
                                </th>
                                <th>Unidad</th>
                                <th class="text-end text-muted fs-12">Solicitada</th>
                                <th class="text-primary fs-12">
                                    <i class="ri-pencil-line me-1"></i>A Comprar
                                </th>
                                <th></th>
                            </tr>
                        </thead>
                           <tbody id="solcom_items_tbody"
                               data-store-url="{{ route('purchase_requests.items.store', $purchaseRequest) }}"
                               data-destroy-url-template="{{ route('purchase_requests.items.destroy', [$purchaseRequest, '__ID__']) }}"
                               data-concepts-search-url="{{ route('concepts.search') }}">
                            @forelse ($purchaseRequest->items as $index => $item)
                                @php
                                    $breakdownJson = collect($item->selected_work_breakdown ?? [])->values();
                                @endphp
                                <tr class="solcom-item-row">
                                    <td class="solcom-row-num text-muted fs-12">{{ $index + 1 }}</td>
                                    <td><span class="fw-semibold">{{ $item->code }}</span></td>
                                    <td>{{ $item->description }}</td>
                                    <td class="text-center">
                                        @if ($item->file_path)
                                            <a href="{{ Storage::disk('s3')->temporaryUrl($item->file_path, now()->addMinutes(30)) }}"
                                               target="_blank"
                                               class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top"
                                               title="Ver Documento"
                                               aria-label="Ver Documento">
                                                <i class="ri-file-3-line"></i>
                                                <span>E.T.</span>
                                            </a>
                                        @else
                                            <span class="badge bg-light text-muted border d-inline-flex align-items-center gap-1 opacity-75"
                                                  data-bs-toggle="tooltip"
                                                  data-bs-placement="top"
                                                  title="Sin Especificaciones Técnicas">
                                                <i class="ri-file-3-line"></i>
                                                <span>Sin E.T.</span>
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $item->unit }}</td>
                                    <td class="text-end text-muted fs-13 fw-semibold">
                                        @if ($breakdownJson->isNotEmpty())
                                            <button type="button"
                                                    class="btn btn-link btn-sm p-0 text-decoration-none text-dark js-solcom-qty-popover"
                                                    data-breakdown='@json($breakdownJson)'
                                                    title="Desglose por obra">
                                                <span class="d-inline-flex align-items-center gap-1">
                                                    <span>{{ number_format((float) $item->requested_quantity, 2, '.', '') }}</span>
                                                    <i class="ri-information-line fs-13 text-primary"></i>
                                                </span>
                                            </button>
                                        @else
                                            {{ number_format((float) $item->requested_quantity, 2, '.', '') }}
                                        @endif
                                    </td>
                                    <td style="min-width:170px">
                                        @hasanyrole('admin|Solcom')
                                        <div class="input-group">
                                            <button type="button" class="btn btn-light border solcom-qty-minus px-3" title="Restar">
                                                <i class="ri-subtract-line"></i>
                                            </button>
                                            <input type="number"
                                                   class="form-control text-center fw-bold fs-15 solcom-qty-input"
                                                  value="{{ number_format((float) $item->purchase_quantity, 2, '.', '') }}"
                                                   data-item-id="{{ $item->id }}"
                                                  data-original="{{ number_format((float) $item->purchase_quantity, 2, '.', '') }}"
                                                  step="0.01"
                                                   min="0"
                                                   inputmode="numeric">
                                            <button type="button" class="btn btn-light border solcom-qty-plus px-3" title="Sumar">
                                                <i class="ri-add-line"></i>
                                            </button>
                                        </div>
                                        @else
                                        <span class="fw-bold fs-15">{{ number_format((float) $item->purchase_quantity, 2, '.', '') }}</span>
                                        @endhasanyrole
                                    </td>
                                    <td>
                                        @hasanyrole('admin|Solcom')
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
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="ri-inbox-line fs-4 d-block mb-1 opacity-50"></i>
                                        Sin conceptos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @hasanyrole('admin|Solcom')
            <div id="solcom_add_panel" class="border-top px-3 py-3" style="display:none;">
                <p class="text-muted fs-12 fw-medium mb-2">
                    <i class="ri-add-circle-line me-1 text-primary"></i>Nuevo concepto
                </p>

                <div id="solcom_state_search">
                    <div class="position-relative">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="ri-search-line text-muted"></i>
                            </span>
                            <input type="text" id="solcom_search_input"
                                   class="form-control border-start-0 ps-0"
                                   placeholder="Buscar concepto por código o descripción…"
                                   autocomplete="off">
                        </div>
                        <ul id="solcom_search_dropdown"
                            class="list-group position-absolute w-100 shadow d-none"
                            style="top:100%;left:0;max-height:260px;overflow-y:auto;z-index:1050;"></ul>
                    </div>
                </div>

                <div id="solcom_state_selected" class="d-none">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-success fs-13 fw-medium">
                            <i class="ri-checkbox-circle-line me-1"></i>Concepto seleccionado
                        </span>
                        <button type="button" id="solcom_btn_change" class="btn btn-link btn-sm p-0 text-muted text-decoration-none">
                            <i class="ri-close-line me-1"></i>Cambiar
                        </button>
                    </div>

                    <div class="rounded-2 border bg-primary-subtle p-3 mb-3">
                        <p class="fw-bold mb-1 fs-15" id="solcom_preview_code"></p>
                        <p class="mb-2 text-body-secondary lh-sm" id="solcom_preview_desc"></p>
                        <span class="badge bg-white text-dark border" id="solcom_preview_unit"></span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-medium mb-1">Cantidad a comprar <span class="text-danger">*</span></label>
                            <input type="number" id="solcom_pur_qty" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label for="solcom_spec_file" class="form-label fs-12 fw-medium mb-1">
                                Especificaciones técnicas <span class="text-muted fw-normal">(opcional)</span>
                            </label>
                            <input type="file"
                                   id="solcom_spec_file"
                                   class="form-control"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        </div>
                    </div>

                    <div id="solcom_add_error" class="text-danger fs-12 mb-2 d-none"></div>
                    <div class="d-flex justify-content-end">
                        <button type="button" id="solcom_btn_add" class="btn btn-primary">
                            <i class="ri-add-line me-1"></i>Agregar concepto
                        </button>
                    </div>
                </div>
            </div>
            @endhasanyrole
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
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning-subtle text-warning py-1 px-2 fs-12">
                        {{ $purchaseRequest->changeNotes->whereNull('resolved_at')->count() }} pendiente(s)
                    </span>
                    <a href="{{ route('purchase_request_changes.index', ['search' => $purchaseRequest->folio]) }}"
                       class="btn btn-sm btn-light">
                        <i class="ri-external-link-line me-1"></i>Ver panel
                    </a>
                </div>
            </div>
            <div class="card-body">
                @foreach ($purchaseRequest->changeNotes as $note)
                <div class="d-flex gap-3 mb-3 {{ $note->isResolved() ? 'opacity-50' : '' }}">
                    <div class="avatar-sm">
                        <span class="avatar-title {{ $note->isResolved() ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }} rounded-circle fs-14 fw-bold">
                            {{ strtoupper(substr($note->requestedBy?->name ?? '?', 0, 1)) }}
                        </span>
                    </div>
                    <div class="">
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
                                @hasanyrole('admin|Solcom')
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
                                        <div class="avatar-xs {{ $bgClass }} rounded-circle d-flex align-items-center justify-content-center">
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
                @forelse ($purchaseRequest->observations ?? [] as $noteIndex => $note)
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
                                    @hasanyrole('admin|Solcom')
                                    <button type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#solcom_note_edit_{{ $noteIndex }}"
                                            aria-expanded="false"
                                            aria-controls="solcom_note_edit_{{ $noteIndex }}">
                                        Editar
                                    </button>
                                    <form action="{{ route('purchase_requests.notes.destroy', [$purchaseRequest, $noteIndex]) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('¿Eliminar esta observación?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm p-0 text-danger text-decoration-none">
                                            Eliminar
                                        </button>
                                    </form>
                                    @endhasanyrole
                                </div>
                            </div>
                            <p class="mb-0 text-muted">{{ $note['text'] }}</p>

                            @hasanyrole('admin|Solcom')
                            <div class="collapse mt-2" id="solcom_note_edit_{{ $noteIndex }}">
                                <form action="{{ route('purchase_requests.notes.update', [$purchaseRequest, $noteIndex]) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <div class="input-group input-group-sm">
                                        <textarea name="text" rows="2" class="form-control" required>{{ $note['text'] }}</textarea>
                                        <button type="submit" class="btn btn-primary">Guardar</button>
                                    </div>
                                </form>
                            </div>
                            @endhasanyrole
                        </div>
                    </div>
                    <hr class="my-2">
                @empty
                    <p class="text-muted fs-13 mb-3">Sin observaciones registradas.</p>
                @endforelse

                @hasanyrole('admin|Solcom')
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
@hasanyrole('admin|Solcom')
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
@endhasanyrole

{{-- ── Modal: Reasignar en Compras ── --}}
@hasanyrole('admin|Solcom|Orden de compra')
@if ($purchaseRequest->assignedTo && $purchaseRequest->status === 'sent_to_purchasing')
<div class="modal fade" id="modalReassignPurchasing" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('purchase_requests.reassign_purchasing', $purchaseRequest) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ri-user-settings-line me-2 text-primary"></i>Reasignar SOLCOM en Compras
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13 mb-3">
                        Esta SOLCOM está asignada actualmente a <strong>{{ $purchaseRequest->assignedTo->name }}</strong>.
                        Selecciona otro usuario de Compras para reasignarla.
                    </p>
                    <label class="form-label fw-medium">Nuevo usuario de Compras <span class="text-danger">*</span></label>
                    <select name="assigned_to" class="form-select" required>
                        <option value="">— Selecciona un usuario —</option>
                        @foreach ($purchasingUsers as $user)
                            @continue($user->id == $purchaseRequest->assigned_to)
                            <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">La reasignación quedará registrada en el histórico de movimientos.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-check-line me-1"></i>Reasignar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endhasanyrole

{{-- ── Modal: Solicitar Cambios ── --}}
@hasanyrole('admin|Solcom|Orden de compra')
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

/* Popover "Desglose por obra": tabla compacta con nombres truncados */
.solcom-breakdown-popover { max-width: 320px; }
.solcom-breakdown-popover .popover-body { padding: .5rem .25rem; }
.solcom-breakdown-table { font-size: .8125rem; margin: 0; }
.solcom-breakdown-table th,
.solcom-breakdown-table td { padding: .2rem .5rem; }
.solcom-breakdown-table thead th {
    font-size: .6875rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    border-bottom: 1px solid var(--bs-border-color);
}
.solcom-breakdown-name { max-width: 200px; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('assets/js/purchase_request.js') }}"></script>
@endpush
