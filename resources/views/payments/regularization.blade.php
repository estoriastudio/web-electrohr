@extends('layouts.app')

@section('page_title', 'Regularización documental')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Regularización documental</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h4 class="card-title mb-0">Pagos pendientes de regularización documental</h4>
                    <p class="text-muted fs-13 mb-0">
                        Pagos ya realizados cuyo hito aún no tiene una factura aceptada. Al aceptar y ligar la factura al hito, el pago se regulariza sin generar un pago nuevo.
                    </p>
                </div>
                <a href="{{ route('payments.alta_facturas') }}" class="btn btn-sm btn-outline-primary">
                    <i class="ri-file-add-line me-1"></i> Alta de facturas
                </a>
            </div>

            @if ($totalsByCurrency->isNotEmpty())
                <div class="alert alert-warning rounded-0 mb-0 fs-13">
                    <i class="ri-file-warning-line me-1"></i>
                    @foreach ($totalsByCurrency as $row)
                        <span class="me-3"><strong>{{ $row->payments_count }}</strong> pago(s) por {{ $row->currency }} {{ number_format((float) $row->total, 2) }}</span>
                    @endforeach
                </div>
            @endif

            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('payments.regularization') }}" class="row g-2">
                    <div class="col-md-6">
                        <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm"
                               placeholder="Folio de pago, OC o proveedor…" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <select name="currency" class="form-select form-select-sm" aria-label="Filtrar por moneda">
                            <option value="">Todas las monedas</option>
                            @foreach (['MXN', 'USD', 'EUR'] as $code)
                                <option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrar</button>
                        @if ($search || $currency)
                            <a href="{{ route('payments.regularization') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros"><i class="ri-close-line"></i></a>
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
                                <th>Hito</th>
                                <th>Monto</th>
                                <th>Fecha de pago</th>
                                <th>Sin factura desde</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                @php
                                    $milestone = $payment->milestone;
                                    $order = $milestone->purchaseOrder;
                                    $supplier = $order->supplier;
                                    $supplierName = $supplier?->rfc_name ?? $supplier?->commercial_name ?? '—';
                                    $days = $payment->payment_date ? (int) $payment->payment_date->diffInDays(today()) : null;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $payment->folio }}</td>
                                    <td><a href="{{ route('purchase_orders.show', $order) }}" class="text-dark fw-medium">#{{ $order->folio ?? $order->id }}</a></td>
                                    <td>{{ $supplierName }}</td>
                                    <td>
                                        {{ $milestone->concept ?: 'Hito #' . $milestone->id }}
                                        <small class="d-block text-muted">{{ ($milestone->payment_condition ?? 'credito') === 'contado' ? 'Contado' : 'Crédito' }}{{ $milestone->is_advance ? ' · Anticipo' : '' }}</small>
                                    </td>
                                    <td class="fw-semibold">{{ $order->currency }} {{ number_format($payment->amount, 2) }}</td>
                                    <td>{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td>
                                        @if ($days !== null)
                                            <span class="badge {{ $days > 30 ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning' }}">{{ $days }} día(s)</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('purchase_orders.show', $order) }}" class="btn btn-sm btn-outline-primary" title="Ver OC y ligar factura">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="ri-check-double-line fs-36 d-block mb-2 text-success"></i>
                                        No hay pagos pendientes de regularización documental.
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
