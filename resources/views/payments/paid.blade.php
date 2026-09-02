@extends('layouts.app')

@section('page_title', 'Pagos Pagados')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Pagos Pagados</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Pagos pagados</h4>
                    <p class="text-muted fs-13 mb-0">Consulta los pagos liquidados y sus comprobantes SPEI.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-success dropdown-toggle" type="button" id="paidExportDropdown"
                                data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            <i class="ri-file-excel-2-line me-1"></i> Exportar a Excel
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-3" aria-labelledby="paidExportDropdown" style="min-width: 290px;">
                            <form method="GET" action="{{ route('payments.paid.export') }}" class="row g-2">
                                <div class="col-12">
                                    <h6 class="mb-1">Rango de exportación</h6>
                                    <p class="text-muted fs-13 mb-1">Selecciona las fechas de pago a exportar.</p>
                                </div>
                                <div class="col-12">
                                    <label for="paidExportStartDate" class="form-label fs-13 mb-1">Fecha de pago inicial</label>
                                    <input type="date" id="paidExportStartDate" name="start_date"
                                           value="{{ old('start_date') }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-12">
                                    <label for="paidExportEndDate" class="form-label fs-13 mb-1">Fecha de pago final</label>
                                    <input type="date" id="paidExportEndDate" name="end_date"
                                           value="{{ old('end_date') }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-12 d-flex justify-content-end mt-2">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="ri-download-2-line me-1"></i> Exportar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <a href="{{ route('payments.payable') }}" class="btn btn-sm btn-outline-success">
                        <i class="ri-money-dollar-circle-line me-1"></i> Por Pagar
                    </a>
                </div>
            </div>

            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('payments.paid') }}" class="row g-2">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light">
                                <i class="ri-search-line text-muted"></i>
                            </span>
                            <input type="text" name="search" value="{{ $search }}"
                                   class="form-control"
                                   placeholder="Folio, referencia, orden o proveedor..."
                                   autocomplete="off">
                        </div>
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
                        @if ($search || $currency || $paymentCondition)
                            <a href="{{ route('payments.paid') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                                <i class="ri-close-line"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
                                <th>Folio pago</th>
                                <th>Orden de compra</th>
                                <th>Proveedor</th>
                                <th>Monto</th>
                                <th>Fecha pago</th>
                                <th>Referencia</th>
                                <th>Comprobante</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                @php
                                    $order = $payment->milestone->purchaseOrder;
                                    $supplier = $order->supplier;
                                    $supplierName = $supplier?->rfc_name ?? $supplier?->commercial_name ?? '—';
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $payment->folio }}</td>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}" class="text-dark fw-medium">
                                            #{{ $order->folio }}
                                        </a>
                                        @if ($payment->milestone->payment_condition === 'contado')
                                            <span class="badge bg-warning-subtle text-warning-emphasis ms-1">Contado</span>
                                        @elseif ($payment->milestone->payment_condition === 'credito')
                                            <span class="badge bg-info-subtle text-info-emphasis ms-1">Crédito</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($supplier)
                                            <a href="{{ route('suppliers.show', $supplier) }}" class="text-dark" title="{{ $supplierName }}">
                                                {{ $supplierName }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="fw-semibold">{{ $order->currency }} {{ number_format($payment->amount, 2) }}</td>
                                    <td>{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ $payment->reference_number ?? '—' }}</td>
                                    <td>
                                        @if ($payment->spei_receipt_path)
                                            <a href="{{ route('payments.spei_receipt.download', $payment) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="ri-file-download-line me-1"></i> SPEI
                                            </a>
                                        @else
                                            <span class="text-muted">Sin comprobante</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-36 d-block mb-2 text-success"></i>
                                        No hay pagos pagados con los filtros seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($payments->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $payments->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
