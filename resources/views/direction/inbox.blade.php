@extends('layouts.app')

@section('page_title', 'Bandeja de Dirección')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
    <li class="breadcrumb-item active">Bandeja de Dirección</li>
@endsection

@section('content')

@php
    $paymentStatus = [
        'por_autorizar' => ['label' => 'Por autorizar', 'class' => 'bg-warning-subtle text-warning'],
        'pospuesto' => ['label' => 'Pospuesto', 'class' => 'bg-secondary-subtle text-secondary'],
    ];
    $payrollStatus = [
        'closed' => ['label' => 'Cerrada, por autorizar', 'class' => 'bg-warning-subtle text-warning'],
        'authorized' => ['label' => 'Autorizada, por pagar', 'class' => 'bg-info-subtle text-info'],
    ];
@endphp

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted fs-13">Pagos por autorizar</div>
            <div class="fs-22 fw-semibold">{{ $paymentsCount }}</div>
            <div class="fs-12 text-muted">Se autorizan desde Pagos: Autorizaciones.</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted fs-13">OC emitidas por autorizar</div>
            <div class="fs-22 fw-semibold">{{ $ordersCount }}</div>
            <div class="fs-12 text-muted">Más {{ $orderChangeRequests->count() }} solicitudes de cambio o cancelación.</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted fs-13">Ajustes comerciales de SOLCOM</div>
            <div class="fs-22 fw-semibold">{{ $changesCount }}</div>
            <div class="fs-12 text-muted">Esperan tu autorización.</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted fs-13">Nóminas por autorizar</div>
            <div class="fs-22 fw-semibold">{{ $payrollPeriods->where('status', 'closed')->count() }}</div>
            <div class="fs-12 text-muted">RRHH las cierra; al autorizarlas se pueden pagar.</div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
        <h4 class="card-title mb-0"><i class="ri-shield-check-line me-1"></i> Pagos por autorizar</h4>
        @can('payment_authorizations.view')
            <a href="{{ route('payments.index') }}" class="btn btn-sm btn-primary">Ir a autorizar pagos</a>
        @endcan
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead class="bg-light-subtle">
                    <tr><th>Pago</th><th>OC</th><th>Proveedor</th><th>Fecha de pago</th><th class="text-end">Monto</th><th>Estatus</th></tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        @php($order = $payment->milestone?->purchaseOrder)
                        <tr>
                            <td>{{ $payment->folio ?: '#'.$payment->id }}</td>
                            <td>
                                @if ($order)
                                    <a href="{{ route('purchase_orders.show', $order) }}">#{{ $order->folio }}</a>
                                @endif
                            </td>
                            <td>{{ $order?->supplier?->commercial_name ?: $order?->supplier?->rfc_name ?: '—' }}</td>
                            <td>{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-end">{{ $order?->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                            <td><span class="badge {{ $paymentStatus[$payment->status]['class'] ?? '' }}">{{ $paymentStatus[$payment->status]['label'] ?? $payment->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay pagos por autorizar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($orderChangeRequests->isNotEmpty())
<div class="card">
    <div class="card-header border-bottom">
        <h4 class="card-title mb-0"><i class="ri-edit-box-line me-1"></i> Cambios y cancelaciones de OC por autorizar</h4>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @foreach ($orderChangeRequests as $changeRequest)
                @php($requestedOrder = $changeRequest->purchaseOrder)
                <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <span class="badge {{ $changeRequest->type === 'cancel' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning' }}">{{ $changeRequest->typeLabel() }}</span>
                        <a href="{{ route('purchase_orders.show', $requestedOrder) }}" class="ms-1">OC #{{ $requestedOrder?->folio }}</a>
                        <span class="text-muted fs-12">· {{ $changeRequest->requestedBy?->name }} · {{ $changeRequest->created_at->format('d/m/Y') }}</span>
                        <div class="fs-13 text-muted">{{ \Illuminate\Support\Str::limit($changeRequest->reason, 160) }}</div>
                    </div>
                    <a href="{{ route('purchase_orders.show', $requestedOrder) }}" class="btn btn-sm btn-primary text-nowrap">Revisar</a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<div class="row g-3">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header border-bottom">
                <h4 class="card-title mb-0"><i class="ri-file-list-3-line me-1"></i> OC emitidas por autorizar</h4>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse ($orders as $order)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <a href="{{ route('purchase_orders.show', $order) }}">#{{ $order->folio }}</a>
                                <span class="text-muted fs-13">· {{ $order->supplier?->commercial_name ?: $order->supplier?->rfc_name }}</span>
                            </span>
                            <span class="d-flex align-items-center gap-2">
                                <span class="fs-13">{{ $order->currency }} {{ number_format((float) $order->amount, 2) }}</span>
                                <a href="{{ route('purchase_orders.show', $order) }}" class="btn btn-sm btn-success">Autorizar</a>
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-muted py-4">No hay OC emitidas.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="ri-edit-circle-line me-1"></i> Ajustes comerciales de SOLCOM</h4>
                @can('purchase_request_changes.view')
                    <a href="{{ route('purchase_request_changes.index') }}" class="btn btn-sm btn-light">Ver panel</a>
                @endcan
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse ($changes as $change)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                @if ($change->purchaseRequest)
                                    <a href="{{ route('purchase_requests.show', $change->purchaseRequest) }}">SOLCOM #{{ $change->purchaseRequest->folio }}</a>
                                @endif
                                <span class="text-muted fs-12">{{ $change->requestedBy?->name }} · {{ $change->created_at?->format('d/m/Y') }}</span>
                            </div>
                            <div class="fs-13 text-muted">{{ \Illuminate\Support\Str::limit($change->text, 140) }}</div>
                            @if ($change->purchaseRequest)
                                @include('purchase_requests.partials._commercial_change', ['note' => $change, 'purchaseRequest' => $change->purchaseRequest])
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-center text-muted py-4">No hay ajustes comerciales por autorizar.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0"><i class="ri-money-dollar-box-line me-1"></i> Nóminas por autorizar y por pagar</h4>
        @can('hr_payroll.view')
            <a href="{{ route('human_resources.payroll-periods.index') }}" class="btn btn-sm btn-light">Ver periodos</a>
        @endcan
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @forelse ($payrollPeriods as $period)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>Semana {{ $period->week_number }} / {{ $period->year }}</span>
                    <span class="d-flex align-items-center gap-2">
                        <span class="badge {{ $payrollStatus[$period->status]['class'] ?? '' }}">{{ $payrollStatus[$period->status]['label'] ?? $period->status }}</span>
                        @if ($period->status === 'closed')
                            @can('hr_payroll.authorize')
                                <form method="POST" action="{{ route('human_resources.payroll-periods.authorize', $period) }}" onsubmit="return confirm('¿Autorizar la nómina {{ $period->week_number }}/{{ $period->year }}?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">Autorizar</button>
                                </form>
                            @endcan
                        @endif
                    </span>
                </li>
            @empty
                <li class="list-group-item text-center text-muted py-4">No hay nóminas pendientes.</li>
            @endforelse
        </ul>
    </div>
</div>

@endsection
