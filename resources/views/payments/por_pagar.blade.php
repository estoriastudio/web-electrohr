@extends('layouts.app')

@section('page_title', 'Por Pagar')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item"><a href="{{ route('payments.index') }}">Autorización de Pagos</a></li>
    <li class="breadcrumb-item active">Por Pagar</li>
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
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Pagos autorizados por liquidar</h4>
                    <p class="text-muted fs-13 mb-0">Aquí solo aparecen pagos en estatus autorizado para marcarlos como pagados.</p>
                </div>
                <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-shield-check-line me-1"></i> Volver a Autorización
                </a>
            </div>

            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('payments.payable') }}" class="d-flex gap-2">
                    <div class="input-group input-group-sm">
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
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap table-hover table-centered mb-0">
                        <thead class="bg-light-subtle">
                            <tr>
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
                                    $isUrgent = $milestone->due_date
                                        && $milestone->due_date->lte($urgentDate);
                                @endphp
                                <tr class="{{ $isUrgent ? 'table-warning' : '' }}">
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
                                        <a href="{{ route('suppliers.show', $supplier) }}" class="text-dark">
                                            {{ $supplier->rfc_name ?? $supplier->commercial_name ?? '—' }}
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
                                    <td colspan="8" class="text-center text-muted py-5">
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
@endsection
