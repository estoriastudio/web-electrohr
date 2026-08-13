@extends('layouts.app')

@section('page_title', 'Por Pagar')

@push('styles')
<style>
.spei-dropzone {
    min-height: 160px;
    border: 1px dashed #c4ced9;
    border-radius: .5rem;
    background: #f8fafc;
    transition: border-color .2s ease, background .2s ease;
}
.spei-dropzone:hover,
.spei-dropzone.dz-drag-hover {
    border-color: var(--bs-primary);
    background: #eef5ff;
}
.spei-dropzone .dz-message {
    margin: 0;
    padding: 1.5rem 1rem;
    color: var(--bs-secondary-color);
    text-align: center;
}
.spei-dropzone .dz-preview {
    margin: .75rem;
}
.spei-dropzone .dz-error-message {
    color: var(--bs-danger);
    font-size: .75rem;
}
</style>
@endpush

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('payments.index') }}">Autorización de Pagos</a></li>
    <li class="breadcrumb-item active">Por Pagar</li>
@endsection

@section('content')
@php
    $persistedSelectedPaymentIds = collect(data_get($selectionState ?? [], 'selected_ids', []))
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->all();
    $persistedSelectedPaymentCount = (int) data_get($selectionState ?? [], 'selected_count', count($persistedSelectedPaymentIds));
@endphp

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

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Pagos autorizados por liquidar</h4>
                    <p class="text-muted fs-13 mb-0">Aquí solo aparecen pagos en estatus autorizado para marcarlos como pagados.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" id="btnClearPaymentSelection" class="btn btn-sm btn-outline-secondary"
                            @disabled($persistedSelectedPaymentCount === 0)>
                        <i class="ri-delete-bin-line me-1"></i> Limpiar selección
                    </button>
                    <button type="button" id="btnRegisterMultipleSpei" class="btn btn-sm btn-success"
                            @disabled($persistedSelectedPaymentCount === 0)>
                        <i class="ri-bank-card-line me-1"></i> Asociar SPEI y pagar
                        <span class="badge bg-light text-dark ms-1" id="selectedPaymentsCount">{{ $persistedSelectedPaymentCount }}</span>
                    </button>
                    <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-shield-check-line me-1"></i> Volver a Autorización
                    </a>
                </div>
            </div>

            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('payments.payable') }}" class="d-flex flex-wrap gap-2" id="payableSearchForm">
                    <div class="input-group input-group-sm" style="min-width:260px; flex:1 1 320px;">
                        <span class="input-group-text bg-light">
                            <i class="ri-search-line text-muted"></i>
                        </span>
                        <input type="text" name="search" value="{{ $search }}"
                               class="form-control"
                               placeholder="Buscar por pago, orden o proveedor…"
                               autocomplete="off">
                        @if ($search)
                            <a href="{{ route('payments.payable') }}" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </div>
                    <select name="currency" class="form-select form-select-sm" style="max-width:170px;" aria-label="Filtrar por moneda">
                        <option value="">Todas las monedas</option>
                        <option value="MXN" @selected($currency === 'MXN')>MXN</option>
                        <option value="USD" @selected($currency === 'USD')>USD</option>
                        <option value="EUR" @selected($currency === 'EUR')>EUR</option>
                    </select>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th style="width:44px;">
                                    <input type="checkbox" class="form-check-input" id="selectAllPayments"
                                           title="Seleccionar todos los pagos">
                                </th>
                                <th>Urgencia</th>
                                <th>Folio pago</th>
                                <th>Orden de compra</th>
                                <th>Proveedor</th>
                                <th>Monto</th>
                                <th>Fecha pago</th>
                                <th>Referencia</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                @php
                                    $milestone = $payment->milestone;
                                    $order = $milestone->purchaseOrder;
                                    $supplier = $order->supplier;
                                    $supplierName = $supplier->rfc_name ?? $supplier->commercial_name ?? '—';
                                    $isUrgent = $milestone->due_date
                                        && $milestone->due_date->lte($urgentDate);
                                @endphp
                                <tr class="{{ $isUrgent ? 'table-warning' : '' }}">
                                    <td>
                                        <input type="checkbox" class="form-check-input js-payment-select"
                                               value="{{ $payment->id }}" aria-label="Seleccionar pago {{ $payment->folio }}"
                                                 @checked(in_array($payment->id, $persistedSelectedPaymentIds, true))>
                                    </td>
                                    <td>
                                        @if ($isUrgent)
                                            <span class="badge bg-danger-subtle text-danger py-1 px-2 fs-12">
                                                <i class="ri-alarm-warning-line me-1"></i> Urgente
                                            </span>
                                        @else
                                            <span class="text-muted fs-12">—</span>
                                        @endif
                                    </td>
                                    <td class="fw-semibold">{{ $payment->folio }}</td>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}" class="text-dark fw-medium">
                                            #{{ $order->folio }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('suppliers.show', $supplier) }}" class="text-dark d-block"
                                           title="{{ $supplierName }}">
                                            <span class="hover-marquee d-block">
                                                <span class="track"><span>{{ $supplierName }}</span><span aria-hidden="true">{{ $supplierName }}</span></span>
                                            </span>
                                        </a>
                                    </td>
                                    <td class="fw-semibold">{{ $order->currency }} {{ number_format($payment->amount, 2) }}</td>
                                    <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                                    <td>{{ $payment->reference_number ?? '—' }}</td>
                                    <td>
                                        <form action="{{ route('payments.update', $payment) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="pagado">
                                            <button type="submit" class="btn btn-soft-success btn-sm"
                                                    onclick="return confirm('¿Marcar este pago como PAGADO?')">
                                                <i class="ri-money-dollar-circle-line"></i> Marcar pagado
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-36 d-block mb-2 text-success"></i>
                                        No hay pagos autorizados pendientes por pagar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMultipleSpei" tabindex="-1" aria-labelledby="modalMultipleSpeiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('payments.mark_multiple_paid_with_spei') }}" method="POST"
                  enctype="multipart/form-data" id="multipleSpeiForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalMultipleSpeiLabel">
                        <i class="ri-bank-card-line me-1"></i> Asociar comprobante SPEI
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-13 mb-3">
                        El comprobante se asociará a <strong id="modalSelectedPaymentsCount">0</strong> pago(s), que se marcarán como pagados.
                    </p>
                    <input type="file" name="spei_receipt_file" id="multipleSpeiFile"
                           class="d-none" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                    <div id="multipleSpeiDropzone" class="dropzone spei-dropzone">
                        <div class="dz-message">
                            <i class="ri-upload-cloud-2-line fs-28 text-primary d-block mb-2"></i>
                            <h6 class="mb-1">Arrastra el comprobante SPEI aquí</h6>
                            <span class="fs-13">o da clic para buscarlo</span>
                        </div>
                    </div>
                    <div class="form-text mt-2">PDF, JPG, PNG o WEBP. Tamaño máximo: 10 MB.</div>
                    @error('spei_receipt_file')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitMultipleSpei">
                        <i class="ri-check-double-line me-1"></i> Asociar y marcar pagados
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
if (typeof window !== 'undefined' && window.Dropzone) {
    window.Dropzone.autoDiscover = false;
}

document.addEventListener('DOMContentLoaded', function () {
    var initialSelectedIds = @json($persistedSelectedPaymentIds);
    var syncUrl = @json(route('payments.payable.selection.sync'));
    var clearUrl = @json(route('payments.payable.selection.clear'));
    var csrfToken = @json(csrf_token());
    var selectAll = document.getElementById('selectAllPayments');
    var checkboxes = Array.from(document.querySelectorAll('.js-payment-select'));
    var registerButton = document.getElementById('btnRegisterMultipleSpei');
    var clearButton = document.getElementById('btnClearPaymentSelection');
    var selectedCount = document.getElementById('selectedPaymentsCount');
    var modalCount = document.getElementById('modalSelectedPaymentsCount');
    var modalElement = document.getElementById('modalMultipleSpei');
    var form = document.getElementById('multipleSpeiForm');
    var searchForm = document.getElementById('payableSearchForm');
    var fileInput = document.getElementById('multipleSpeiFile');
    var dropzoneElement = document.getElementById('multipleSpeiDropzone');
    var dropzone = null;
    var selectedIds = new Set((initialSelectedIds || []).map(function (id) { return String(id); }));

    function applySelectedIdsToPage() {
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = selectedIds.has(String(checkbox.value));
        });
    }

    function updateSelection() {
        var count = selectedIds.size;
        selectedCount.textContent = count;
        registerButton.disabled = count === 0;
        clearButton.disabled = count === 0;
        var checkedOnPage = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
        selectAll.checked = checkedOnPage > 0 && checkedOnPage === checkboxes.length;
        selectAll.indeterminate = checkedOnPage > 0 && checkedOnPage < checkboxes.length;
    }

    function syncSelectionToServer() {
        return fetch(syncUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                selected_ids: Array.from(selectedIds).map(function (id) { return Number(id); })
            })
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('No se pudo sincronizar la selección.');
                }

                return response.json();
            })
            .then(function (payload) {
                selectedIds.clear();
                (payload.selected_ids || []).forEach(function (id) {
                    selectedIds.add(String(id));
                });
                applySelectedIdsToPage();
                updateSelection();
            })
            .catch(function () {
                updateSelection();
            });
    }

    function setInputFile(file) {
        var dataTransfer = new DataTransfer();
        if (file) {
            dataTransfer.items.add(file);
        }
        fileInput.files = dataTransfer.files;
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (checkbox.checked) {
                selectedIds.add(String(checkbox.value));
            } else {
                selectedIds.delete(String(checkbox.value));
            }

            syncSelectionToServer();
        });
    });

    selectAll.addEventListener('change', function () {
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = selectAll.checked;
            if (checkbox.checked) {
                selectedIds.add(String(checkbox.value));
            } else {
                selectedIds.delete(String(checkbox.value));
            }
        });
        syncSelectionToServer();
    });

    clearButton.addEventListener('click', function () {
        fetch(clearUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('No se pudo limpiar la selección.');
            }

            selectedIds.clear();
            applySelectedIdsToPage();
            updateSelection();
        }).catch(function () {
            updateSelection();
        });
    });

    registerButton.addEventListener('click', function () {
        var count = selectedIds.size;
        if (count === 0) {
            return;
        }

        modalCount.textContent = count;
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });

    if (window.Dropzone && dropzoneElement) {
        dropzone = new Dropzone(dropzoneElement, {
            url: '/',
            autoProcessQueue: false,
            maxFiles: 1,
            maxFilesize: 10,
            acceptedFiles: 'application/pdf,image/jpeg,image/jpg,image/png,image/webp,.pdf,.jpg,.jpeg,.png,.webp',
            addRemoveLinks: true,
            dictRemoveFile: 'Quitar',
            dictInvalidFileType: 'Tipo de archivo no permitido.',
            dictFileTooBig: 'El archivo es demasiado grande (@{{filesize}}MB). Máximo: @{{maxFilesize}}MB.',
        });

        dropzone.on('addedfile', function (file) {
            if (dropzone.files.length > 1) {
                dropzone.removeFile(dropzone.files[0]);
            }
            setInputFile(file);
            fileInput.classList.remove('is-invalid');
        });

        dropzone.on('removedfile', function () {
            setInputFile(null);
        });
    } else {
        fileInput.classList.remove('d-none');
        fileInput.classList.add('form-control');
    }

    form.addEventListener('submit', function (event) {
        if (!selectedIds.size || !fileInput.files.length) {
            event.preventDefault();
            fileInput.classList.add('is-invalid');
            return;
        }

        form.querySelectorAll('.js-selected-payment-id').forEach(function (input) {
            input.remove();
        });
        selectedIds.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'payment_ids[]';
            input.value = id;
            input.className = 'js-selected-payment-id';
            form.appendChild(input);
        });
    });

    modalElement.addEventListener('hidden.bs.modal', function () {
        form.reset();
        if (dropzone) {
            dropzone.removeAllFiles(true);
        }
    });

    searchForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (searchForm.dataset.submitting === '1') {
            return;
        }

        searchForm.dataset.submitting = '1';
        syncSelectionToServer().then(function () {
            searchForm.submit();
        });
    });

    applySelectedIdsToPage();
    updateSelection();

    @if ($errors->has('payment_ids') || $errors->has('spei_receipt_file'))
        modalCount.textContent = selectedIds.size;
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    @endif
});
</script>
@endpush
