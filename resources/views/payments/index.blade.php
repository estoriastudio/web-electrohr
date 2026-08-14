@extends('layouts.app')

@section('page_title', 'Autorización de Pagos')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Autorización de Pagos</li>
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
                    <h4 class="card-title mb-0">Pagos pendientes de autorización</h4>
                    <p class="text-muted fs-13 mb-0">Los pagos con vencimiento en los próximos 7 días aparecen primero.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @role('admin')
                    <button type="button" id="btnClearAuthorizationSelection" class="btn btn-sm btn-outline-secondary"
                            @disabled($persistedSelectedPaymentCount === 0)>
                        <i class="ri-delete-bin-line me-1"></i> Limpiar selección
                    </button>
                    <button type="button" id="btnAuthorizeMultiplePayments" class="btn btn-sm btn-primary"
                            @disabled($persistedSelectedPaymentCount === 0)>
                        <i class="ri-check-double-line me-1"></i> Autorizar seleccionados
                        <span class="badge bg-light text-dark ms-1" id="selectedAuthorizationPaymentsCount">{{ $persistedSelectedPaymentCount }}</span>
                    </button>
                    @endrole
                    <a href="{{ route('payments.interactive') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ri-file-list-3-line me-1"></i> Activar Modo Interactivo
                    </a>
                </div>
            </div>

            {{-- Búsqueda y filtros --}}
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('payments.index') }}" class="row g-2" id="authorizationSearchForm">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light">
                            <i class="ri-search-line text-muted"></i>
                        </span>
                        <input type="text" name="search" value="{{ $search }}"
                               class="form-control"
                               placeholder="Folio, referencia o proyecto…"
                               autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select name="due_date_filter" class="form-select form-select-sm" aria-label="Filtrar por vencimiento">
                            <option value="">Todos los vencimientos</option>
                            <option value="overdue" @selected($dueDateFilter === 'overdue')>Vencidos</option>
                            <option value="today" @selected($dueDateFilter === 'today')>Vence hoy</option>
                            <option value="next_7_days" @selected($dueDateFilter === 'next_7_days')>Próximos 7 días</option>
                            <option value="later" @selected($dueDateFilter === 'later')>Después de 7 días</option>
                            <option value="without_date" @selected($dueDateFilter === 'without_date')>Sin fecha de vencimiento</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="currency" class="form-select form-select-sm" aria-label="Filtrar por moneda">
                            <option value="">Todas las monedas</option>
                            <option value="MXN" @selected($currency === 'MXN')>MXN</option>
                            <option value="USD" @selected($currency === 'USD')>USD</option>
                            <option value="EUR" @selected($currency === 'EUR')>EUR</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="payment_condition" class="form-select form-select-sm" aria-label="Filtrar por condición de pago">
                            <option value="">Crédito y contado</option>
                            <option value="credito" @selected($paymentCondition === 'credito')>Crédito</option>
                            <option value="contado" @selected($paymentCondition === 'contado')>Contado</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search || $dueDateFilter || $currency || $paymentCondition)
                            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                @if ($payments->isEmpty())
                    <div class="p-4 text-center">
                        <i class="ri-file-list-3-line fs-1 text-muted"></i>
                        <p class="text-muted fs-14 mt-2 mb-0">No hay pagos pendientes de autorización.</p>  
                    </div>
                @else
                    @include('payments.utilities.table', [
                        'payments' => $payments,
                        'authorizationSelectionState' => $selectionState,
                    ])
                @endif
            </div>
        </div>
    </div>
</div>

@role('admin')
<form action="{{ route('payments.authorize_multiple') }}" method="POST" id="authorizeMultiplePaymentsForm">
    @csrf
</form>
@endrole

@endsection

@role('admin')
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var initialSelectedIds = @json($persistedSelectedPaymentIds);
    var syncUrl = @json(route('payments.authorization.selection.sync'));
    var clearUrl = @json(route('payments.authorization.selection.clear'));
    var csrfToken = @json(csrf_token());
    var checkboxes = Array.from(document.querySelectorAll('.js-authorization-payment-select'));
    var selectAll = document.getElementById('selectAllAuthorizationPayments');
    var authorizeButton = document.getElementById('btnAuthorizeMultiplePayments');
    var clearButton = document.getElementById('btnClearAuthorizationSelection');
    var selectedCount = document.getElementById('selectedAuthorizationPaymentsCount');
    var searchForm = document.getElementById('authorizationSearchForm');
    var authorizeForm = document.getElementById('authorizeMultiplePaymentsForm');
    var selectedIds = new Set((initialSelectedIds || []).map(function (id) { return String(id); }));

    function applySelectedIdsToPage() {
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = selectedIds.has(String(checkbox.value));
        });
    }

    function updateSelectionState() {
        var count = selectedIds.size;
        var checkedOnPage = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;

        selectedCount.textContent = count;
        authorizeButton.disabled = count === 0;
        clearButton.disabled = count === 0;
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
                updateSelectionState();
            })
            .catch(function () {
                updateSelectionState();
            });
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
            updateSelectionState();
        }).catch(function () {
            updateSelectionState();
        });
    });

    authorizeButton.addEventListener('click', function () {
        if (!selectedIds.size || !window.confirm('¿Autorizar los ' + selectedIds.size + ' pago(s) seleccionados?')) {
            return;
        }

        authorizeForm.querySelectorAll('.js-authorization-payment-id').forEach(function (input) {
            input.remove();
        });
        selectedIds.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'payment_ids[]';
            input.value = id;
            input.className = 'js-authorization-payment-id';
            authorizeForm.appendChild(input);
        });
        authorizeForm.submit();
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
    updateSelectionState();
});
</script>
@endpush
@endrole
