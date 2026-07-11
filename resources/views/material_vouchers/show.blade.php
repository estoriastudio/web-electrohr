@extends('layouts.app')

@section('page_title', 'Vale ' . $materialVoucher->folio)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('material_vouchers.index') }}">Vales de Material</a></li>
    <li class="breadcrumb-item active">{{ $materialVoucher->folio }}</li>
@endsection

@push('styles')
<link href="{{ asset('assets/vendor/signature-pad-main/assets/jquery.signaturepad.css') }}" rel="stylesheet" type="text/css" />
<style>
    .sigPad {
        width: 100%;
    }
    .sigPad .sigWrapper {
        width: 100%;
        height: 220px;
        border: 1px dashed #ced4da;
        border-radius: 0.375rem;
        background: #fff;
    }
    .sigPad .pad {
        width: 100%;
        height: 210px;
        touch-action: none;
    }
    @media (min-width: 768px) {
        .sigPad .sigWrapper {
            height: 180px;
        }
        .sigPad .pad {
            height: 170px;
        }
    }
    @keyframes mv-item-flash {
        0% { background-color: rgba(var(--bs-primary-rgb), .12); }
        100% { background-color: transparent; }
    }
    .mv-item-new {
        animation: mv-item-flash .9s ease-out forwards;
    }
</style>
@endpush

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
        'emitido'    => ['label' => 'Emitido', 'class' => 'bg-warning-subtle text-warning'],
        'autorizado' => ['label' => 'Autorizado', 'class' => 'bg-info-subtle text-info'],
        'completado' => ['label' => 'Completado', 'class' => 'bg-primary-subtle text-primary'],
        'facturado'  => ['label' => 'Facturado', 'class' => 'bg-secondary-subtle text-secondary'],
        'pagado'     => ['label' => 'Pagado', 'class' => 'bg-success-subtle text-success'],
    ];
    $s = $statusMap[$materialVoucher->status] ?? ['label' => ucfirst($materialVoucher->status), 'class' => 'bg-secondary-subtle text-secondary'];
    $supplierName = $materialVoucher->supplier?->commercial_name ?: $materialVoucher->supplier?->rfc_name;
    $canEditVoucher = auth()->user()?->hasRole('admin') || $materialVoucher->status === 'emitido';
@endphp

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <div class="d-flex align-items-center gap-2">
            <h4 class="card-title mb-0">{{ $materialVoucher->folio }}</h4>
            <span class="badge {{ $s['class'] }} py-1 px-2 fs-12">{{ $s['label'] }}</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('material_vouchers.pdf', $materialVoucher) }}" class="btn btn-sm btn-light">
                <i class="ri-printer-line me-1"></i> Imprimir
            </a>
            @can('update')
            @if($canEditVoucher)
            <a href="{{ route('material_vouchers.edit', $materialVoucher) }}" class="btn btn-sm btn-soft-primary">
                <i class="ri-edit-line me-1"></i> Editar
            </a>
            @endif
            @endcan
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <span class="text-muted fs-12 d-block">Fecha</span>
                <span class="fw-semibold">{{ $materialVoucher->voucher_date?->format('d/m/Y') }}</span>
            </div>
            <div class="col-md-4">
                <span class="text-muted fs-12 d-block">Solicitante</span>
                <span class="fw-semibold">{{ $materialVoucher->requester ?: '—' }}</span>
            </div>
            <div class="col-md-4">
                <span class="text-muted fs-12 d-block">Vale a favor de</span>
                <span class="fw-semibold">{{ $supplierName ?: '—' }}</span>
            </div>
        </div>

        <hr>

        <div class="row g-3">
            <div class="col-md-4">
                <span class="text-muted fs-12 d-block">Creado por</span>
                <span class="fw-semibold">{{ $materialVoucher->createdBy?->name ?? '—' }}</span>
            </div>
            <div class="col-md-4">
                <span class="text-muted fs-12 d-block">Autorizado por</span>
                <span class="fw-semibold">{{ $materialVoucher->authorizedBy?->name ?? '—' }}</span>
            </div>
            <div class="col-md-4">
                <span class="text-muted fs-12 d-block">Firma de autorización</span>
                <span class="fw-semibold">{{ $materialVoucher->authorized_signature_name ?? '—' }}</span>
                @if($materialVoucher->authorized_signature)
                    <div class="mt-2 p-2 border rounded bg-white">
                        <img src="{{ $materialVoucher->authorized_signature }}" alt="Firma" style="max-height: 70px; width: auto; max-width: 100%;">
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0"><i class="ri-list-check-2 me-1"></i> Renglones del vale</h5>
                <span id="mv_items_count" class="badge bg-primary-subtle text-primary py-1 px-2 fs-12">{{ $materialVoucher->items->count() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Cant.</th>
                                <th>Unidad</th>
                                <th>Descripción</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="mvItemsTbody">
                            @forelse($materialVoucher->items as $item)
                                <tr class="mv-item-row" data-item-id="{{ $item->id }}">
                                    <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td>{{ $item->unit }}</td>
                                    <td>{{ $item->description }}</td>
                                    <td>
                                        @can('delete')
                                        @if($canEditVoucher)
                                        <form action="{{ route('material_vouchers.items.destroy', [$materialVoucher, $item]) }}" method="POST" class="mv-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-soft-danger btn-sm" type="submit"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                        @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr id="mv_empty_row">
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No hay renglones aún.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @can('create')
            @if($canEditVoucher)
            <div class="card-body border-top">
                <form id="voucherItemForm" action="{{ route('material_vouchers.items.store', $materialVoucher) }}" method="POST" class="row g-2">
                    @csrf
                    <div class="col-md-2">
                        <input id="mv_item_quantity" type="number" name="quantity" step="0.01" min="0.01" class="form-control form-control-sm" placeholder="Cant." required>
                    </div>
                    <div class="col-md-2">
                        <input id="mv_item_unit" type="text" name="unit" class="form-control form-control-sm" placeholder="Unidad" required>
                    </div>
                    <div class="col-md-6">
                        <input id="mv_item_description" type="text" name="description" class="form-control form-control-sm" placeholder="Descripción" required>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button id="mv_add_btn" type="submit" class="btn btn-primary btn-sm"><i class="ri-add-line me-1"></i> Agregar</button>
                    </div>
                    <div class="col-12">
                        <div id="mv_item_error" class="text-danger fs-12 d-none"></div>
                    </div>
                </form>
            </div>
            @else
            <div class="card-body border-top">
                <div class="alert alert-light border mb-0 fs-13">
                    Los renglones solo pueden modificarse cuando el vale está en <strong>Emitido</strong>.
                </div>
            </div>
            @endif
            @endcan
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-shield-check-line me-1"></i> Flujo de estatus</h5>
            </div>
            <div class="card-body d-grid gap-2">
                @if($materialVoucher->status === 'emitido')
                    <form id="authorizeVoucherForm" action="{{ route('material_vouchers.authorize', $materialVoucher) }}" method="POST" class="d-grid gap-2">
                        @csrf
                        <input type="text" class="form-control form-control-sm" name="signature_name" value="{{ old('signature_name', $materialVoucher->authorized_signature_name) }}" placeholder="Nombre de firma del encargado">

                        <div id="voucherSigPad" class="sigPad">
                            <div class="sigWrapper">
                                <canvas class="pad"></canvas>
                            </div>
                        </div>
                        <input type="hidden" id="signature_data" name="signature_data" value="{{ old('signature_data') }}">
                        <div class="d-flex justify-content-end">
                            <button id="clearSignatureBtn" type="button" class="btn btn-light btn-sm">Limpiar firma</button>
                        </div>

                        <button type="submit" class="btn btn-info btn-sm">Autorizar vale</button>
                    </form>
                @endif

                @if($materialVoucher->status === 'autorizado')
                    <form action="{{ route('material_vouchers.status.update', $materialVoucher) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="completado">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Marcar como Completado</button>
                    </form>
                @endif

                @if($materialVoucher->status === 'completado')
                    <form action="{{ route('material_vouchers.status.update', $materialVoucher) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="facturado">
                        <button type="submit" class="btn btn-secondary btn-sm w-100">Marcar como Facturado</button>
                    </form>
                @endif

                @if($materialVoucher->status === 'facturado')
                    <form action="{{ route('material_vouchers.status.update', $materialVoucher) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="pagado">
                        <button type="submit" class="btn btn-success btn-sm w-100">Marcar como Pagado</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ri-chat-1-line me-1"></i> Observaciones</h5>
            </div>
            <div class="card-body">
                @php $notes = $materialVoucher->observations ?? []; @endphp
                @forelse($notes as $note)
                    <div class="border rounded p-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold fs-13">{{ $note['user_name'] ?? 'Sistema' }}</span>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($note['created_at'])->format('d/m/Y H:i') }}</small>
                        </div>
                        <p class="mb-0 fs-13">{{ $note['text'] ?? '' }}</p>
                    </div>
                @empty
                    <p class="text-muted mb-2">Sin observaciones.</p>
                @endforelse

                @can('create')
                @if($canEditVoucher)
                <form action="{{ route('material_vouchers.notes.store', $materialVoucher) }}" method="POST">
                    @csrf
                    <textarea name="text" rows="3" class="form-control form-control-sm mb-2" placeholder="Escribe una observación..." required></textarea>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Agregar observación</button>
                </form>
                @else
                    <p class="text-muted fs-12 mb-0">Este vale ya fue autorizado; no se permiten nuevas modificaciones.</p>
                @endif
                @endcan
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/signature-pad-main/assets/json2.min.js') }}"></script>
<script src="{{ asset('assets/vendor/signature-pad-main/jquery.signaturepad.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('authorizeVoucherForm');
    if (!form) return;

    var sigPadRoot = $('#voucherSigPad');
    var signatureDataInput = document.getElementById('signature_data');
    var clearBtn = document.getElementById('clearSignatureBtn');
    var canvas = sigPadRoot.find('canvas.pad').get(0);
    var wrapper = sigPadRoot.find('.sigWrapper').get(0);
    var pad = null;

    function syncCanvasSize() {
        if (!canvas || !wrapper) return;

        // Evita desfase: el tamaño interno del canvas debe coincidir con su tamaño visual.
        var width = Math.max(1, Math.floor(wrapper.clientWidth));
        var height = Math.max(120, Math.floor(wrapper.clientHeight - 10));
        canvas.width = width;
        canvas.height = height;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
    }

    function initPad() {
        syncCanvasSize();
        var lineTopValue = Math.max(20, canvas.height - 10);
        pad = sigPadRoot.signaturePad({
            drawOnly: true,
            lineTop: lineTopValue,
            validateFields: false
        });
    }

    function initPadWhenReady() {
        // Inicializar cuando el layout ya quedó estable para evitar offset de coordenadas.
        if (document.readyState === 'complete') {
            requestAnimationFrame(initPad);
            return;
        }

        window.addEventListener('load', function () {
            requestAnimationFrame(initPad);
        }, { once: true });
    }

    initPadWhenReady();

    function updateSignatureData() {
        if (!canvas) return;
        signatureDataInput.value = canvas.toDataURL('image/png');
    }

    sigPadRoot.on('mouseup touchend', function () {
        updateSignatureData();
    });

    clearBtn.addEventListener('click', function () {
        if (pad) {
            pad.clearCanvas();
        }
        signatureDataInput.value = '';
    });

    window.addEventListener('resize', function () {
        if (!canvas || !wrapper) return;
        if (!pad) return;

        syncCanvasSize();
        pad.clearCanvas();
        signatureDataInput.value = '';
    });

    form.addEventListener('submit', function (event) {
        updateSignatureData();
        if (!signatureDataInput.value || signatureDataInput.value.length < 100) {
            event.preventDefault();
            alert('Debes capturar la firma para autorizar el vale.');
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('voucherItemForm');
    var tbody = document.getElementById('mvItemsTbody');
    var countBadge = document.getElementById('mv_items_count');
    var errorEl = document.getElementById('mv_item_error');
    var addBtn = document.getElementById('mv_add_btn');

    if (!form || !tbody) return;

    function itemCount() {
        return tbody.querySelectorAll('tr.mv-item-row').length;
    }

    function updateCount() {
        if (countBadge) {
            countBadge.textContent = String(itemCount());
        }
    }

    function showError(message) {
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.classList.remove('d-none');
    }

    function clearError() {
        if (!errorEl) return;
        errorEl.classList.add('d-none');
        errorEl.textContent = '';
    }

    function appendRow(item) {
        var emptyRow = document.getElementById('mv_empty_row');
        if (emptyRow) emptyRow.remove();

        var tr = document.createElement('tr');
        tr.className = 'mv-item-row mv-item-new';
        tr.dataset.itemId = item.id;

        var quantity = Number(item.quantity || 0).toFixed(2);
        var deleteUrlTemplate = '{{ route('material_vouchers.items.destroy', [$materialVoucher, '__ITEM__']) }}';
        var deleteUrl = deleteUrlTemplate.replace('__ITEM__', String(item.id));
        var csrfToken = form.querySelector('input[name="_token"]')?.value || '';

        tr.innerHTML =
            '<td>' + quantity + '</td>' +
            '<td>' + (item.unit || '') + '</td>' +
            '<td>' + (item.description || '') + '</td>' +
            '<td>' +
                '<form action="' + deleteUrl + '" method="POST" class="mv-delete-form">' +
                    '<input type="hidden" name="_token" value="' + csrfToken + '">' +
                    '<input type="hidden" name="_method" value="DELETE">' +
                    '<button class="btn btn-soft-danger btn-sm" type="submit"><i class="ri-delete-bin-line"></i></button>' +
                '</form>' +
            '</td>';

        tbody.appendChild(tr);
        updateCount();
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearError();

        var data = new FormData(form);
        addBtn.disabled = true;
        addBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Agregando...';

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: data
        })
        .then(async function (response) {
            if (!response.ok) {
                var payload = await response.json().catch(function () { return {}; });
                var firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
                throw new Error(firstError || 'No se pudo agregar el renglón.');
            }
            return response.json();
        })
        .then(function (item) {
            appendRow(item);
            form.reset();
            document.getElementById('mv_item_quantity')?.focus();
        })
        .catch(function (error) {
            showError(error.message || 'No se pudo agregar el renglón.');
        })
        .finally(function () {
            addBtn.disabled = false;
            addBtn.innerHTML = '<i class="ri-add-line me-1"></i> Agregar';
        });
    });

    tbody.addEventListener('submit', function (event) {
        var deleteForm = event.target.closest('.mv-delete-form');
        if (!deleteForm) return;

        event.preventDefault();
        if (!confirm('¿Eliminar renglón?')) return;

        var submitBtn = deleteForm.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        fetch(deleteForm.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new FormData(deleteForm)
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('No se pudo eliminar el renglón.');
            }
            return response.json();
        })
        .then(function () {
            var row = deleteForm.closest('tr');
            if (row) row.remove();

            updateCount();
            if (itemCount() === 0) {
                var empty = document.createElement('tr');
                empty.id = 'mv_empty_row';
                empty.innerHTML = '<td colspan="4" class="text-center text-muted py-4">No hay renglones aún.</td>';
                tbody.appendChild(empty);
            }
        })
        .catch(function (error) {
            showError(error.message || 'No se pudo eliminar el renglón.');
            if (submitBtn) submitBtn.disabled = false;
        });
    });
});
</script>
@endpush
