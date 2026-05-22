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
        'pending'   => ['label' => 'Pendiente',  'class' => 'bg-warning-subtle text-warning'],
        'linked'    => ['label' => 'Ligado',     'class' => 'bg-info-subtle text-info'],
        'completed' => ['label' => 'Finalizado', 'class' => 'bg-success-subtle text-success'],
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
                            @if ($purchaseRequest->materialRequest)
                                <a href="{{ route('material_requests.show', $purchaseRequest->materialRequest) }}"
                                   class="badge bg-primary-subtle text-primary py-1 px-2 fs-12 text-decoration-none">
                                    <i class="ri-links-line me-1"></i>SOLMAT #{{ $purchaseRequest->materialRequest->folio }}
                                </a>
                            @endif
                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">
                                <i class="ri-calendar-event-line me-1"></i>
                                Solicitud: {{ $purchaseRequest->request_date?->format('d/m/Y') }}
                            </span>
                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">
                                <i class="ri-alarm-line me-1"></i>
                                Necesidad: {{ $purchaseRequest->need_date?->format('d/m/Y') }}
                            </span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        @hasanyrole('admin|orders')
                        <a href="{{ route('purchase_requests.edit', $purchaseRequest) }}"
                           class="btn btn-soft-primary btn-sm">
                            <i class="ri-edit-line me-1"></i>Editar
                        </a>
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
                <span class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                    {{ $purchaseRequest->items->count() }} ítem(s)
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Unidad</th>
                                <th class="text-end">Cant. Solicitada</th>
                                <th class="text-end">Cant. Compra</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchaseRequest->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><span class="fw-semibold">{{ $item->code }}</span></td>
                                    <td>{{ $item->description }}</td>
                                    <td>{{ $item->unit }}</td>
                                    <td class="text-end text-muted">{{ number_format($item->requested_quantity, 2) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($item->purchase_quantity, 2) }}</td>
                                    <td>
                                        @hasanyrole('admin|orders')
                                        <form action="{{ route('purchase_requests.items.destroy', [$purchaseRequest, $item]) }}"
                                              method="POST"
                                              onsubmit="return confirm('¿Eliminar este concepto?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                        @endhasanyrole
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">
                                        Sin conceptos registrados.
                                    </td>
                                </tr>
                            @endforelse

                            {{-- Fila agregar concepto --}}
                            @hasanyrole('admin|orders')
                            <tr class="table-light">
                                <form action="{{ route('purchase_requests.items.store', $purchaseRequest) }}" method="POST">
                                    @csrf
                                    <td class="text-muted fs-12">Nuevo</td>
                                    <td>
                                        <div class="position-relative" style="min-width:140px">
                                            <input type="text" id="solcom_concept_search"
                                                   class="form-control form-control-sm"
                                                   placeholder="Buscar concepto…"
                                                   autocomplete="off">
                                            <ul id="solcom_concept_dropdown"
                                                class="list-group position-absolute w-100 shadow-sm z-3 d-none"
                                                style="top:100%;left:0;max-height:220px;overflow-y:auto"></ul>
                                        </div>
                                        <input type="hidden" name="concept_id" id="solcom_concept_id">
                                        <input type="text" name="code" id="solcom_code"
                                               class="form-control form-control-sm mt-1 @error('code') is-invalid @enderror"
                                               placeholder="Código" value="{{ old('code') }}" required>
                                    </td>
                                    <td>
                                        <input type="text" name="description" id="solcom_description"
                                               class="form-control form-control-sm @error('description') is-invalid @enderror"
                                               placeholder="Descripción" value="{{ old('description') }}" required>
                                    </td>
                                    <td>
                                        <input type="text" name="unit" id="solcom_unit"
                                               class="form-control form-control-sm @error('unit') is-invalid @enderror"
                                               placeholder="Unidad" value="{{ old('unit') }}" required style="width:90px">
                                    </td>
                                    <td>
                                        <input type="number" name="requested_quantity"
                                               class="form-control form-control-sm text-end @error('requested_quantity') is-invalid @enderror"
                                               placeholder="0.00" value="{{ old('requested_quantity') }}"
                                               step="0.01" min="0.01" required style="width:100px">
                                    </td>
                                    <td>
                                        <input type="number" name="purchase_quantity"
                                               class="form-control form-control-sm text-end @error('purchase_quantity') is-invalid @enderror"
                                               placeholder="0.00" value="{{ old('purchase_quantity') }}"
                                               step="0.01" min="0" required style="width:100px">
                                    </td>
                                    <td>
                                        <button type="submit" class="btn btn-primary btn-sm" title="Agregar concepto">
                                            <i class="ri-add-line"></i>
                                        </button>
                                    </td>
                                </form>
                            </tr>
                            @endhasanyrole
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

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

@endsection

@push('scripts')
<script>
(function () {
    var searchInput    = document.getElementById('solcom_concept_search');
    var dropdown       = document.getElementById('solcom_concept_dropdown');
    var conceptIdInput = document.getElementById('solcom_concept_id');
    var codeInput      = document.getElementById('solcom_code');
    var descInput      = document.getElementById('solcom_description');
    var unitInput      = document.getElementById('solcom_unit');

    if (!searchInput) return;

    var debounceTimer;

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        var q = this.value.trim();
        if (q.length < 2) { dropdown.classList.add('d-none'); dropdown.innerHTML = ''; return; }
        debounceTimer = setTimeout(function () {
            fetch('{{ route('concepts.search') }}?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                dropdown.innerHTML = '';
                if (!data.length) {
                    dropdown.innerHTML = '<li class="list-group-item list-group-item-light text-muted fs-13 py-2 px-3">Sin resultados</li>';
                } else {
                    data.forEach(function (c) {
                        var li = document.createElement('li');
                        li.className = 'list-group-item list-group-item-action py-2 px-3 fs-13';
                        li.style.cursor = 'pointer';
                        li.innerHTML = '<span class="fw-semibold">' + c.code + '</span>'
                            + ' <span class="text-muted">— ' + c.description + '</span>'
                            + ' <span class="badge bg-light text-dark border ms-1">' + c.unit + '</span>';
                        li.addEventListener('click', function () {
                            conceptIdInput.value = c.id;
                            codeInput.value      = c.code;
                            descInput.value      = c.description;
                            unitInput.value      = c.unit;
                            searchInput.value    = c.code + ' — ' + c.description;
                            dropdown.classList.add('d-none');
                        });
                        dropdown.appendChild(li);
                    });
                }
                dropdown.classList.remove('d-none');
            });
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('d-none');
        }
    });
}());
</script>
@endpush
