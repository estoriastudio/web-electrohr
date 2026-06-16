@extends('layouts.app')

@section('page_title', 'Alta de Facturas')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Alta de Facturas</li>
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

<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 fw-semibold">Alta de Facturas</h4>
        </div>
    </div>
</div>

<div class="row g-4">

    {{-- Panel izquierdo: selección de OC --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0 fw-semibold"><i class="ri-file-list-3-line me-1"></i> Órdenes de Compra</h6>
            </div>
            <div class="card-body p-0">
                {{-- Búsqueda --}}
                <div class="p-3 border-bottom">
                    <form method="GET" action="{{ route('payments.alta_facturas') }}">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm"
                                   name="search" value="{{ $search }}"
                                   placeholder="Buscar por folio o proveedor...">
                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                <i class="ri-search-line"></i>
                            </button>
                            @if ($search)
                                <a href="{{ route('payments.alta_facturas') }}" class="btn btn-sm btn-outline-danger" title="Limpiar">
                                    <i class="ri-close-line"></i>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Lista de OCs --}}
                <div class="list-group list-group-flush" style="max-height: 70vh; overflow-y: auto;">
                    @forelse ($orders as $order)
                        <button type="button"
                                class="list-group-item list-group-item-action px-3 py-3 oc-selector-btn"
                                data-oc-id="{{ $order->id }}"
                                data-oc-folio="{{ $order->folio }}"
                                data-oc-supplier="{{ $order->supplier->rfc_name ?? '—' }}"
                                data-oc-currency="{{ $order->currency }}"
                                data-oc-amount="{{ $order->total_with_iva }}"
                                data-oc-invoice-count="{{ $order->invoices->count() + 1 }}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-medium text-dark">{{ $order->folio ?? 'OC #' . $order->id }}</div>
                                    <div class="text-muted fs-12">{{ $order->supplier->rfc_name ?? '—' }}</div>
                                </div>
                                <span class="badge
                                    @if ($order->status === 'autorizada') bg-success-subtle text-success
                                    @elseif ($order->status === 'pendiente') bg-warning-subtle text-warning
                                    @else bg-secondary-subtle text-secondary @endif
                                    fs-10">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </div>
                            <div class="fs-11 text-muted mt-1">
                                {{ $order->currency }} {{ number_format($order->total_with_iva, 2) }}
                                · {{ $order->invoices->count() }} factura(s)
                            </div>
                        </button>
                    @empty
                        <div class="text-center text-muted py-5 fs-13">No se encontraron órdenes de compra.</div>
                    @endforelse
                </div>

                {{-- Paginación --}}
                @if ($orders->hasPages())
                    <div class="p-2 border-top">
                        {{ $orders->appends(['search' => $search])->links('pagination::simple-bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Panel derecho: formulario de alta de factura --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold" id="form-oc-title">
                    <i class="ri-upload-2-line me-1 text-danger"></i>
                    <span id="form-oc-label">Selecciona una OC para continuar</span>
                </h6>
            </div>
            <div class="card-body">

                {{-- Placeholder cuando no hay OC seleccionada --}}
                <div id="oc-placeholder" class="text-center py-5 text-muted">
                    <i class="ri-arrow-left-line ri-3x mb-2 d-block"></i>
                    <p class="mb-0">Selecciona una Orden de Compra de la lista para registrar una factura.</p>
                </div>

                {{-- Formulario (oculto hasta selección) --}}
                <form id="invoice-form" action="{{ route('invoices.store') }}" method="POST"
                      enctype="multipart/form-data" class="d-none">
                    @csrf
                    <input type="hidden" name="purchase_order_id" id="form-oc-id">
                    <input type="hidden" name="currency" id="form-currency">

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
                                El nombre se generará automáticamente: <strong id="form-filename">OC?-FACT?.pdf</strong>
                            </div>
                            @error('pdf_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Importe --}}
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Importe <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   name="amount" id="form-amount" placeholder="0.00" required>
                            <div class="form-text">
                                Máximo: <span id="form-max-amount">—</span>
                            </div>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-medium">Moneda</label>
                            <input type="text" class="form-control bg-light" id="form-currency-display" disabled>
                            <div class="form-text">Dato heredado de la Orden de Compra.</div>
                        </div>

                        {{-- Hitos relacionados --}}
                        <div class="col-12" id="milestones-wrapper">
                            <label class="form-label fw-medium">Hitos relacionados</label>
                            <div class="border rounded p-3 bg-light" id="milestones-list">
                                <div class="text-muted fs-13 text-center py-2" id="milestones-loading">
                                    <i class="ri-loader-2-line ri-spin me-1"></i> Cargando hitos…
                                </div>
                            </div>
                            <div class="form-text">Selecciona los hitos que cubre esta factura (opcional).</div>
                        </div>

                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light" id="btn-cancel-form">
                            <i class="ri-close-line me-1"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-upload-2-line me-1"></i> Registrar factura
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btns          = document.querySelectorAll('.oc-selector-btn');
    const placeholder   = document.getElementById('oc-placeholder');
    const form          = document.getElementById('invoice-form');
    const formLabel     = document.getElementById('form-oc-label');
    const formOcId      = document.getElementById('form-oc-id');
    const formCurrency  = document.getElementById('form-currency');
    const formCurrDisp  = document.getElementById('form-currency-display');
    const formMax       = document.getElementById('form-max-amount');
    const formFilename  = document.getElementById('form-filename');
    const formAmtInput  = document.getElementById('form-amount');
    const milestonesList = document.getElementById('milestones-list');
    const cancelBtn     = document.getElementById('btn-cancel-form');

    function selectOc(btn) {
        // Highlight selected
        btns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const id       = btn.dataset.ocId;
        const folio    = btn.dataset.ocFolio || 'OC #' + id;
        const currency = btn.dataset.ocCurrency;
        const amount   = parseFloat(btn.dataset.ocAmount);
        const invCount = btn.dataset.ocInvoiceCount;

        // Populate hidden inputs
        formOcId.value       = id;
        formCurrency.value   = currency;
        formCurrDisp.value   = currency;
        formAmtInput.max     = amount;
        formLabel.textContent = 'Subir Factura — ' + folio + ' (' + btn.dataset.ocSupplier + ')';
        formMax.textContent   = currency + ' ' + amount.toLocaleString('es-MX', { minimumFractionDigits: 2 });
        formFilename.textContent = 'OC' + id + '-FACT' + invCount + '.pdf';

        // Show form
        placeholder.classList.add('d-none');
        form.classList.remove('d-none');

        // Load milestones via AJAX
        milestonesList.innerHTML = '<div class="text-muted fs-13 text-center py-2"><i class="ri-loader-2-line me-1"></i> Cargando hitos…</div>';

        fetch('/ordenes-de-compra/' + id + '/hitos-json', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                milestonesList.innerHTML = '<p class="text-muted fs-13 mb-0 text-center">Esta OC no tiene hitos configurados.</p>';
                return;
            }
            let html = '<div class="row g-2">';
            data.forEach(function(m) {
                const condLabel = m.payment_condition === 'contado' ? 'Contado' : 'Crédito';
                html += `
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="milestone_ids[]" value="${m.id}"
                                   id="alta_milestone_${m.id}">
                            <label class="form-check-label fs-13" for="alta_milestone_${m.id}">
                                <strong>Hito #${m.id}</strong>
                                <span class="text-muted">— ${condLabel} · ${currency} ${parseFloat(m.effective_amount).toLocaleString('es-MX', {minimumFractionDigits:2})}</span>
                            </label>
                        </div>
                    </div>`;
            });
            html += '</div>';
            milestonesList.innerHTML = html;
        })
        .catch(() => {
            milestonesList.innerHTML = '<p class="text-danger fs-13 mb-0">No se pudieron cargar los hitos.</p>';
        });
    }

    btns.forEach(btn => {
        btn.addEventListener('click', function () {
            selectOc(this);
        });
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            btns.forEach(b => b.classList.remove('active'));
            form.classList.add('d-none');
            placeholder.classList.remove('d-none');
        });
    }

    // Si hay error de validación y hay una OC preseleccionada (via old input), restaurar
    @if (old('purchase_order_id'))
    const preselected = document.querySelector('.oc-selector-btn[data-oc-id="{{ old('purchase_order_id') }}"]');
    if (preselected) selectOc(preselected);
    @endif
});
</script>
@endpush
