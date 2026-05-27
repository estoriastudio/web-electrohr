@extends('layouts.app')

@section('page_title', 'SOLMAT #' . $materialRequest->folio)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('material_requests.index') }}">Solicitudes de Material</a></li>
    <li class="breadcrumb-item active">SOLMAT #{{ $materialRequest->folio }}</li>
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
    $s = $statusMap[$materialRequest->status] ?? ['label' => $materialRequest->status, 'class' => 'bg-secondary-subtle text-secondary'];
@endphp

{{-- ── Header ── --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">
                            <i class="ri-file-list-3-line me-2 text-primary"></i>
                            Solicitud de Material
                            <span class="text-primary fw-bold">Folio #{{ $materialRequest->folio }}</span>
                        </h4>
                        @if ($materialRequest->code)
                            <p class="text-muted mb-1 fs-13">Código: {{ $materialRequest->code }}</p>
                        @endif
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">
                                <i class="ri-calendar-event-line me-1"></i>
                                Solicitud: {{ $materialRequest->request_date?->format('d/m/Y') }}
                            </span>
                            <span class="badge bg-light text-dark border py-1 px-2 fs-12">
                                <i class="ri-alarm-line me-1"></i>
                                Necesidad: {{ $materialRequest->need_date?->format('d/m/Y') }}
                            </span>
                        </div>

                        <div class="mt-2">
                            @include('material_requests.partials._process_map')
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('material_requests.pdf', $materialRequest) }}"
                           class="btn btn-sm btn-outline-danger" target="_blank">
                            <i class="ri-file-pdf-2-line me-1"></i> Descargar PDF
                        </a>
                        
                        @hasanyrole('admin|orders')
                        <a href="{{ route('material_requests.edit', $materialRequest) }}"
                           class="btn btn-soft-primary btn-sm">
                            <i class="ri-edit-line me-1"></i>Editar
                        </a>
                        @endhasanyrole
                        <a href="{{ route('material_requests.index') }}" class="btn btn-light btn-sm">
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
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-information-line me-2 text-primary"></i>Datos Generales</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Proyecto</p>
                        <p class="fw-semibold mb-0">{{ $materialRequest->project?->name ?? '—' }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Obra</p>
                        <p class="fw-semibold mb-0">{{ $materialRequest->projectWork?->name ?? '—' }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Zona</p>
                        <p class="fw-semibold mb-0">{{ $materialRequest->zone }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Dirección de Entrega</p>
                        <p class="fw-semibold mb-0">{{ $materialRequest->delivery_address }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Categoría de Suministros</p>
                        <p class="fw-semibold mb-0">{{ $materialRequest->supply_category }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted fs-12 mb-1">Solicitud Elaborada Por</p>
                        <p class="fw-semibold mb-0">{{ $materialRequest->requestedBy?->name ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SOLCOMs vinculadas --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-links-line me-2 text-info"></i>SOLCOMs Vinculadas</h5>
            </div>
            <div class="card-body p-0">
                @forelse ($materialRequest->purchaseRequests as $pr)
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                        <span class="fw-semibold">Folio #{{ $pr->folio }}</span>
                        <a href="{{ route('purchase_requests.show', $pr) }}" class="btn btn-light btn-sm">
                            <i class="ri-eye-line"></i>
                        </a>
                    </div>
                @empty
                    <p class="text-muted text-center py-3 mb-0 fs-13">Sin solicitudes de compra vinculadas.</p>
                @endforelse
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
                <span id="solmat_items_count" class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">
                    {{ $materialRequest->items->count() }} ítem(s)
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
                                <th class="text-end">Cantidad</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="solmat_items_tbody">
                            @forelse ($materialRequest->items as $index => $item)
                                <tr class="solmat-item-row">
                                    <td>{{ $index + 1 }}</td>
                                    <td><span class="fw-semibold">{{ $item->code }}</span></td>
                                    <td>{{ $item->description }}</td>
                                    <td>{{ $item->unit }}</td>
                                    <td class="text-end">{{ (int) $item->quantity }}</td>
                                    <td>
                                        @hasanyrole('admin|orders')
                                        <form action="{{ route('material_requests.items.destroy', [$materialRequest, $item]) }}"
                                              method="POST"
                                              class="solmat-delete-form">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                        @endhasanyrole
                                    </td>
                                </tr>
                            @empty
                                <tr id="solmat_empty_row">
                                    <td colspan="6" class="text-center text-muted py-3">
                                        Sin conceptos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
            {{-- ── Panel Agregar Concepto (siempre visible, mobile-first) ── --}}
            @hasanyrole('admin|orders')
            <div class="border-top px-3 py-3" id="solmat_add_panel">

                {{-- Estado A: Búsqueda --}}
                <div id="solmat_state_search">
                    <p class="text-muted fs-12 mb-2 fw-medium">
                        <i class="ri-add-circle-line me-1 text-primary"></i>Agregar concepto
                    </p>
                    <div class="position-relative">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="ri-search-line text-muted"></i>
                            </span>
                            <input type="text"
                                   id="solmat_concept_search"
                                   class="form-control border-start-0 ps-0"
                                   placeholder="Buscar por código o descripción…"
                                   autocomplete="off"
                                   inputmode="text">
                        </div>
                        <ul id="solmat_concept_dropdown"
                            class="list-group position-absolute w-100 shadow d-none"
                            style="top:100%;left:0;max-height:280px;overflow-y:auto;z-index:1050"></ul>
                    </div>
                </div>

                {{-- Estado B: Concepto seleccionado --}}
                <div id="solmat_state_selected" class="d-none">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-success fs-13 fw-medium">
                            <i class="ri-checkbox-circle-line me-1"></i>Concepto seleccionado
                        </span>
                        <button type="button" id="solmat_btn_change"
                                class="btn btn-link btn-sm p-0 text-muted text-decoration-none">
                            <i class="ri-close-line me-1"></i>Cambiar
                        </button>
                    </div>

                    <div class="rounded-2 border bg-primary-subtle p-3 mb-3">
                        <p class="fw-bold mb-1 fs-15" id="solmat_preview_code"></p>
                        <p class="mb-2 text-body-secondary lh-sm" id="solmat_preview_desc"></p>
                        <span class="badge bg-white text-dark border" id="solmat_preview_unit"></span>
                    </div>

                    <label for="solmat_quantity" class="form-label fw-medium">
                        Cantidad solicitada <span class="text-danger">*</span>
                    </label>
                    <input type="number"
                           id="solmat_quantity"
                           class="form-control form-control-lg text-center mb-3"
                           placeholder="0"
                           step="1"
                           min="1"
                           inputmode="numeric">
                    <div id="solmat_add_error" class="text-danger fs-12 mb-2 d-none"></div>
                    <div class="d-grid">
                        <button type="button" id="solmat_btn_add" class="btn btn-primary btn-lg">
                            <i class="ri-add-line me-1"></i>Agregar a la solicitud
                        </button>
                    </div>
                </div>

            </div>
            @endhasanyrole

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
                {{-- Lista de notas --}}
                @forelse ($materialRequest->observations ?? [] as $note)
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

                {{-- Agregar nota --}}
                @hasanyrole('admin|orders')
                <form action="{{ route('material_requests.notes.store', $materialRequest) }}" method="POST" class="mt-3">
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
<style>
@keyframes solmat-flash {
    0%   { background-color: rgba(var(--bs-primary-rgb), .12); }
    100% { background-color: transparent; }
}
.solmat-item-new { animation: solmat-flash .9s ease-out forwards; }
</style>
<script>
(function () {
    'use strict';

    // ── URLs y token CSRF ─────────────────────────────────────────
    var csrfMeta  = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.content : '';
    var storeUrl  = '{{ route('material_requests.items.store', $materialRequest) }}';
    var searchUrl = '{{ route('concepts.search') }}';

    // ── Referencias al DOM ────────────────────────────────────────
    var countBadge    = document.getElementById('solmat_items_count');
    var tbody         = document.getElementById('solmat_items_tbody');
    var addPanel      = document.getElementById('solmat_add_panel');
    var stateSearch   = document.getElementById('solmat_state_search');
    var searchInput   = document.getElementById('solmat_concept_search');
    var dropdown      = document.getElementById('solmat_concept_dropdown');
    var stateSelected = document.getElementById('solmat_state_selected');
    var previewCode   = document.getElementById('solmat_preview_code');
    var previewDesc   = document.getElementById('solmat_preview_desc');
    var previewUnit   = document.getElementById('solmat_preview_unit');
    var qtyInput      = document.getElementById('solmat_quantity');
    var btnAdd        = document.getElementById('solmat_btn_add');
    var btnChange     = document.getElementById('solmat_btn_change');
    var addError      = document.getElementById('solmat_add_error');

    if (!searchInput) return; // panel no visible (usuario sin permiso)

    var selectedConcept = null;
    var debounceTimer;

    // ── Escape HTML seguro ────────────────────────────────────────
    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str != null ? String(str) : ''));
        return d.innerHTML;
    }

    // ── Conteo e ícono del badge ──────────────────────────────────
    function itemCount() {
        return tbody.querySelectorAll('tr.solmat-item-row').length;
    }

    function updateBadge() {
        if (countBadge) countBadge.textContent = itemCount() + ' ítem(s)';
    }

    // ── Estado A: pantalla de búsqueda ────────────────────────────
    function showSearch() {
        selectedConcept    = null;
        searchInput.value  = '';
        dropdown.innerHTML = '';
        dropdown.classList.add('d-none');
        if (qtyInput)  qtyInput.value = '';
        if (addError)  { addError.classList.add('d-none'); addError.textContent = ''; }
        stateSelected.classList.add('d-none');
        stateSearch.classList.remove('d-none');
        searchInput.focus();
    }

    // ── Estado B: concepto seleccionado ───────────────────────────
    function showSelected(concept) {
        selectedConcept         = concept;
        previewCode.textContent = concept.code;
        previewDesc.textContent = concept.description;
        previewUnit.textContent = concept.unit;
        if (addError) { addError.classList.add('d-none'); addError.textContent = ''; }
        stateSearch.classList.add('d-none');
        stateSelected.classList.remove('d-none');
        qtyInput.value = '';
        qtyInput.focus();
    }

    // ── Búsqueda con debounce ─────────────────────────────────────
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        var q = this.value.trim();
        if (q.length < 2) {
            dropdown.classList.add('d-none');
            dropdown.innerHTML = '';
            return;
        }
        debounceTimer = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                dropdown.innerHTML = '';
                if (!data.length) {
                    dropdown.innerHTML =
                        '<li class="list-group-item text-center text-muted py-3 fs-13">'
                        + '<i class="ri-search-line me-1"></i>Sin resultados para «' + escHtml(q) + '»</li>';
                } else {
                    data.forEach(function (c) {
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
                            dropdown.classList.add('d-none');
                            showSelected(c);
                        });
                        dropdown.appendChild(li);
                    });
                }
                dropdown.classList.remove('d-none');
            })
            .catch(function () {
                dropdown.innerHTML =
                    '<li class="list-group-item text-danger py-2 px-3 fs-13">'
                    + '<i class="ri-error-warning-line me-1"></i>Error al buscar. Intenta de nuevo.</li>';
                dropdown.classList.remove('d-none');
            });
        }, 300);
    });

    // Cerrar dropdown al tocar fuera del panel
    document.addEventListener('pointerdown', function (e) {
        if (addPanel && !addPanel.contains(e.target)) {
            dropdown.classList.add('d-none');
        }
    });

    // ── Botón "Cambiar concepto" ───────────────────────────────────
    btnChange.addEventListener('click', showSearch);

    // ── Agregar ítem vía AJAX ─────────────────────────────────────
    function doAdd() {
        if (!selectedConcept) return;

        var qty = parseFloat(qtyInput.value);
        if (!qtyInput.value.trim() || isNaN(qty) || qty <= 0) {
            addError.textContent = 'Ingresa una cantidad válida mayor a 0.';
            addError.classList.remove('d-none');
            qtyInput.focus();
            return;
        }
        addError.classList.add('d-none');

        btnAdd.disabled = true;
        btnAdd.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Agregando…';

        var fd = new FormData();
        fd.append('_token',      csrfToken);
        fd.append('concept_id',  selectedConcept.id);
        fd.append('code',        selectedConcept.code);
        fd.append('description', selectedConcept.description);
        fd.append('unit',        selectedConcept.unit);
        fd.append('quantity',    Math.round(qty));

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
            addError.textContent = 'Error al agregar el concepto. Intenta de nuevo.';
            addError.classList.remove('d-none');
        })
        .finally(function () {
            btnAdd.disabled = false;
            btnAdd.innerHTML = '<i class="ri-add-line me-1"></i>Agregar a la solicitud';
        });
    }

    btnAdd.addEventListener('click', doAdd);
    qtyInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); doAdd(); }
    });

    // ── Insertar fila nueva en la tabla ───────────────────────────
    function appendRow(item) {
        var emptyRow = document.getElementById('solmat_empty_row');
        if (emptyRow) emptyRow.remove();

        var num  = itemCount() + 1;
        var qty  = parseFloat(item.quantity);
        var qtyF = isNaN(qty) ? item.quantity : Math.round(qty).toString();

        var tr = document.createElement('tr');
        tr.className      = 'solmat-item-row solmat-item-new';
        tr.dataset.itemId = item.id;
        tr.innerHTML =
            '<td>' + num + '</td>'
            + '<td><span class="fw-semibold">' + escHtml(item.code) + '</span></td>'
            + '<td>' + escHtml(item.description) + '</td>'
            + '<td>' + escHtml(item.unit) + '</td>'
            + '<td class="text-end">' + qtyF + '</td>'
            + '<td>'
            + '<form method="POST" action="' + storeUrl + '/' + escHtml(item.id) + '" class="solmat-delete-form">'
            + '<input type="hidden" name="_token" value="' + escHtml(csrfToken) + '">'
            + '<input type="hidden" name="_method" value="DELETE">'
            + '<button type="submit" class="btn btn-soft-danger btn-sm" title="Eliminar">'
            + '<i class="ri-delete-bin-line"></i></button>'
            + '</form>'
            + '</td>';

        tbody.appendChild(tr);
    }

    // ── Eliminar ítem vía AJAX (event delegation en tbody) ────────
    tbody.addEventListener('submit', function (e) {
        var form = e.target.closest('.solmat-delete-form');
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
                    emptyTr.id = 'solmat_empty_row';
                    emptyTr.innerHTML =
                        '<td colspan="6" class="text-center text-muted py-3">Sin conceptos registrados.</td>';
                    tbody.appendChild(emptyTr);
                }
            }, 280);
        })
        .catch(function () {
            alert('Error al eliminar. Intenta de nuevo.');
            if (btn) btn.disabled = false;
        });
    });

    // ── Renumerar filas tras eliminar ─────────────────────────────
    function renumber() {
        tbody.querySelectorAll('tr.solmat-item-row').forEach(function (row, i) {
            var td = row.querySelector('td:first-child');
            if (td) td.textContent = i + 1;
        });
    }

}());
</script>
@endpush
